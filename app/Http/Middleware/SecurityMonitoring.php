<?php

namespace App\Http\Middleware;

use App\Security\Detection\RequestInspector;
use App\Security\Detection\ThreatScorer;
use App\Security\Services\BehaviourMonitor;
use App\Security\Services\IpReputationService;
use App\Security\Services\SecurityEventRecorder;
use App\Security\Support\ClientIpResolver;
use App\Security\Support\HtmlSanitizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * Global IDS gate. Runs for both web and API requests.
 *
 * Pipeline:
 *   1. resolve the *real* client IP (proxy headers only from trusted CIDRs)
 *   2. enforce active IP blocks
 *   3. inspect the raw request -> weighted signature matches -> threat score
 *   4. sanitise allow-listed rich-text fields before any controller sees them
 *   5. allow / log / challenge / block according to score and mode
 *   6. observe the response for behavioural anomalies
 *
 * Every detection step is wrapped so a failure in the IDS can never take the
 * application down (fail-open by design; use SECURITY_MODE=enforce for the
 * blocking path).
 */
final class SecurityMonitoring
{
    public function __construct(
        private readonly ClientIpResolver $ipResolver,
        private readonly IpReputationService $reputation,
        private readonly RequestInspector $inspector,
        private readonly ThreatScorer $scorer,
        private readonly SecurityEventRecorder $recorder,
        private readonly BehaviourMonitor $behaviour,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.enabled', true)) {
            return $next($request);
        }

        $ip = $this->ipResolver->resolve($request);
        $request->attributes->set('security_ip', $ip);

        // ---- 1. active block list -------------------------------------------------
        if (config('security.mode', 'enforce') === 'enforce'
            && ! $this->ipResolver->isAllowlisted($ip)
            && $this->reputation->isBlocked($ip)) {
            $this->safeRecord($request, 'ip.blocked_request', 'medium', 60, 'block', [
                'reason'     => 'Request from an actively blocked IP',
                'categories' => ['access_control'],
            ]);

            return $this->deny($request, 'This IP address has been temporarily blocked for security reasons.');
        }

        // ---- 2. signature inspection ---------------------------------------------
        $action = 'allow';
        if ($this->shouldScan($request, $ip)) {
            try {
                $report = $this->scorer->score($this->inspector->inspect($request), $request);
                $action = $report->action;

                if ($report->hasMatches() && $action !== 'allow') {
                    $this->recorder->recordDetection($request, $report);
                }

                if ($action === 'challenge') {
                    $request->attributes->set('security_challenge', true);
                    $this->behaviour->challenge($request, $ip);
                }
            } catch (\Throwable $e) {
                Log::channel((string) config('security.log_channel', 'security'))
                    ->warning('Security inspection failed (fail-open)', ['error' => $e->getMessage()]);
            }
        }

        // ---- 3. rich-text sanitisation -------------------------------------------
        try {
            $this->sanitizeRichText($request);
        } catch (\Throwable) {
            // Sanitisation is a defence-in-depth layer; never break the request.
        }

        // ---- 4. verdict -----------------------------------------------------------
        if ($action === 'block' && config('security.mode', 'enforce') === 'enforce'
            && ! $this->ipResolver->isAllowlisted($ip)) {
            $this->reputation->block($ip, 'Blocked by signature inspection', null, 'auto');

            return $this->deny($request, 'The request was rejected by the security policy.');
        }

        $response = $next($request);

        // ---- 5. behavioural observation ------------------------------------------
        try {
            $this->behaviour->observe($request, $response, $ip);
        } catch (\Throwable) {
            // no-op
        }

        return $response;
    }

    private function shouldScan(Request $request, string $ip): bool
    {
        if ($this->ipResolver->isAllowlisted($ip)) {
            return false;
        }

        $path = trim((string) $request->path(), '/');
        foreach ((array) config('security.detection.ignore_paths', []) as $pattern) {
            $pattern = trim((string) $pattern, '/');
            if ($pattern === '') {
                continue;
            }

            // Portable wildcard match (fnmatch() is not available on every
            // Windows PHP build).
            $regex = '#^'.str_replace('\*', '.*', preg_quote($pattern, '#')).'$#i';
            if (preg_match($regex, $path) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Allow-list HTML sanitisation for the fields the Vue front-end renders
     * with v-html. Plain text is returned byte-for-byte, so this is cheap.
     */
    private function sanitizeRichText(Request $request): void
    {
        $fields = array_flip((array) config('security.rich_text_fields', []));
        if ($fields === []) {
            return;
        }

        $bags = [];
        if ($request->isJson()) {
            $bags[] = $request->json();
        } else {
            $bags[] = $request->request;
        }

        foreach ($bags as $bag) {
            if (! is_object($bag) || ! method_exists($bag, 'all') || ! method_exists($bag, 'set')) {
                continue;
            }

            foreach ($bag->all() as $key => $value) {
                if (! isset($fields[$key]) || ! is_string($value) || ! str_contains($value, '<')) {
                    continue;
                }

                $clean = $this->sanitizer->clean($value);
                if ($clean !== $value) {
                    $bag->set($key, $clean);
                    $request->attributes->set('security_sanitised', true);
                }
            }
        }
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || str_starts_with((string) $request->path(), 'api/')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'code'    => 'SECURITY_BLOCKED',
            ], 403);
        }

        return response($message, 403, ['Content-Type' => 'text/plain']);
    }

    private function safeRecord(
        Request $request,
        string $type,
        string $severity,
        int $score,
        string $action,
        array $extra,
    ): void {
        try {
            $this->recorder->record($request, $type, $severity, $score, $action, $extra, 403);
        } catch (\Throwable) {
            // no-op
        }
    }
}
