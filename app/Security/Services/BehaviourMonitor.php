<?php

namespace App\Security\Services;

use App\Security\Support\ClientIpResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Behavioural / anomaly detection.
 *
 * Signature rules catch hostile *payloads*; this catches hostile *patterns*
 * that look perfectly benign one request at a time: credential stuffing, OTP
 * brute force, 404/403 enumeration sweeps and request floods. Counters live
 * in the cache store so they work even before the security tables exist.
 */
final class BehaviourMonitor
{
    public function __construct(
        private readonly ClientIpResolver $resolver,
        private readonly SecurityEventRecorder $recorder,
        private readonly IpReputationService $reputation,
    ) {}

    /**
     * Called once per response by the global security middleware.
     */
    public function observe(Request $request, Response $response, ?string $ip = null): void
    {
        if (! config('security.enabled', true)) {
            return;
        }

        $ip ??= (string) ($request->attributes->get('security_ip') ?: $this->resolver->resolve($request));
        $status = $response->getStatusCode();
        $path = strtolower((string) $request->path());

        try {
            $this->burst($request, $ip);

            if (in_array($status, [401, 422], true) && $this->isAuthPath($path)) {
                $this->failedLogin($request, $ip);
            }

            if ($status === 403) {
                $this->authorizationFailure($request, $ip);
            }

            if ($status === 404) {
                $this->notFound($request, $ip);
            }

            if ($status >= 500) {
                $this->serverError($request, $ip, $status);
            }
        } catch (\Throwable) {
            // Detection is best-effort.
        }
    }

    /** Explicit hook for Illuminate\Auth\Events\Failed. */
    public function failedLogin(Request $request, ?string $ip = null, ?string $identity = null): void
    {
        $ip ??= $this->resolver->resolve($request);
        $window = (int) config('security.behaviour.window_minutes', 10);
        $hits = $this->bump('failed_login', $ip, $window);
        $threshold = (int) config('security.behaviour.failed_login_threshold', 5);

        if ($hits >= $threshold) {
            $this->fire($request, $ip, 'behaviour.failed_login', 'high', 75,
                'log', $window, $hits, $threshold,
                'Repeated authentication failures'.($identity ? ' for '.$identity : ''));
        }
    }

    /** Explicit hook for OTP verification endpoints. */
    public function otpFailure(Request $request, ?string $ip = null): void
    {
        $ip ??= $this->resolver->resolve($request);
        $window = (int) config('security.behaviour.window_minutes', 10);
        $hits = $this->bump('otp_failure', $ip, $window);
        $threshold = (int) config('security.behaviour.otp_failure_threshold', 5);

        if ($hits >= $threshold) {
            $this->fire($request, $ip, 'behaviour.otp_bruteforce', 'critical', 95,
                'block', $window, $hits, $threshold, 'OTP brute-force pattern detected');
        }
    }

    public function notFound(Request $request, ?string $ip = null): void
    {
        $ip ??= $this->resolver->resolve($request);
        $window = (int) config('security.behaviour.window_minutes', 10);
        $hits = $this->bump('not_found', $ip, $window);
        $threshold = (int) config('security.behaviour.not_found_threshold', 25);

        if ($hits >= $threshold) {
            $this->fire($request, $ip, 'behaviour.enumeration_scan', 'high', 80,
                'log', $window, $hits, $threshold, 'Mass 404 responses indicate path/ID enumeration');
        }
    }

    public function authorizationFailure(Request $request, ?string $ip = null): void
    {
        $ip ??= $this->resolver->resolve($request);
        $window = (int) config('security.behaviour.window_minutes', 10);
        $hits = $this->bump('authorization', $ip, $window);
        $threshold = (int) config('security.behaviour.authorization_threshold', 10);

        if ($hits >= $threshold) {
            $this->fire($request, $ip, 'behaviour.authorization_probe', 'high', 80,
                'log', $window, $hits, $threshold, 'Repeated 403 responses indicate privilege probing');
        }
    }

    public function burst(Request $request, ?string $ip = null): void
    {
        $ip ??= $this->resolver->resolve($request);
        $window = (int) config('security.behaviour.window_minutes', 10);
        $hits = $this->bump('burst', $ip, $window);
        $threshold = (int) config('security.behaviour.request_burst_threshold', 180);

        if ($hits >= $threshold) {
            $this->fire($request, $ip, 'behaviour.request_flood', 'medium', 55,
                'log', $window, $hits, $threshold, 'Abnormal request volume from a single IP');
        }
    }

    public function serverError(Request $request, ?string $ip, int $status): void
    {
        $ip ??= $this->resolver->resolve($request);
        $window = (int) config('security.behaviour.window_minutes', 10);
        $hits = $this->bump('server_error', $ip, $window);

        if ($hits >= 10) {
            $this->fire($request, $ip, 'behaviour.error_induction', 'medium', 50,
                'log', $window, $hits, 10, 'Repeated '.$status.' responses - possible error-based probing');
        }
    }

    /**
     * A "challenge" is a mid-confidence detection. Repeated challenges from
     * the same IP become an active block even without a single decisive hit.
     */
    public function challenge(Request $request, ?string $ip = null): bool
    {
        $ip ??= $this->resolver->resolve($request);
        $window = (int) config('security.behaviour.window_minutes', 10);

        try {
            $hits = $this->bump('challenge', $ip, $window);
        } catch (\Throwable) {
            return false;
        }

        if ($hits < 3 || config('security.mode', 'enforce') !== 'enforce') {
            return false;
        }

        $this->fire($request, $ip, 'behaviour.repeat_challenge', 'critical', 100,
            'block', $window, $hits, 3, 'Three suspicious requests in the observation window');

        return $this->reputation->block(
            $ip,
            'Escalated after '.$hits.' suspicious (challenge) requests',
            (int) config('security.auto_block.duration_minutes', 60),
            'auto'
        ) !== null;
    }

    private function fire(
        Request $request,
        string $ip,
        string $type,
        string $severity,
        int $score,
        string $action,
        int $window,
        int $hits,
        int $threshold,
        string $reason,
    ): void {
        // One incident per IP/type/window, even if the counter keeps climbing.
        $guard = 'security:behaviour:guard:'.hash('sha256', $type.'|'.$ip);
        try {
            if (! Cache::add($guard, 1, now()->addMinutes($window))) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $this->recorder->record($request, $type, $severity, $score, $action, [
            'reason'     => $reason,
            'hits'       => $hits,
            'threshold'  => $threshold,
            'window'     => $window,
            'categories' => ['behaviour'],
        ]);
    }

    private function bump(string $name, string $ip, int $window): int
    {
        $key = 'security:behaviour:'.$name.':'.hash('sha256', $ip);
        if (! Cache::has($key)) {
            Cache::add($key, 0, now()->addMinutes(max(1, $window)));
        }

        return (int) Cache::increment($key);
    }

    private function isAuthPath(string $path): bool
    {
        foreach (['login', 'auth/', 'signup/', 'forgot-password', 'refresh-token', 'otp', 'verify'] as $needle) {
            if (str_contains($path, $needle)) {
                return true;
            }
        }

        return false;
    }
}
