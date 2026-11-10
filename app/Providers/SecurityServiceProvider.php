<?php

namespace App\Providers;

use App\Security\Detection\PayloadNormalizer;
use App\Security\Detection\RequestInspector;
use App\Security\Detection\SignatureSet;
use App\Security\Detection\ThreatScorer;
use App\Security\Services\AlertDispatcher;
use App\Security\Services\BehaviourMonitor;
use App\Security\Services\IpReputationService;
use App\Security\Services\ResetGrantService;
use App\Security\Services\SecurityEventRecorder;
use App\Security\Support\ClientIpResolver;
use App\Security\Support\HtmlSanitizer;
use App\Security\Support\SensitiveDataRedactor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the IDS into the container and into Laravel's authentication events.
 */
final class SecurityServiceProvider extends ServiceProvider
{
    /** @var array<int, class-string> */
    private const SINGLETONS = [
        ClientIpResolver::class,
        SensitiveDataRedactor::class,
        HtmlSanitizer::class,
        SignatureSet::class,
        PayloadNormalizer::class,
        RequestInspector::class,
        ThreatScorer::class,
        AlertDispatcher::class,
        IpReputationService::class,
        SecurityEventRecorder::class,
        BehaviourMonitor::class,
        ResetGrantService::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(config_path('security.php'), 'security');

        foreach (self::SINGLETONS as $class) {
            $this->app->singleton($class);
        }

        // RequestInspector is cheap to reuse but must not share state between
        // requests; the explicit factory keeps its construction deterministic.
        $this->app->singleton(RequestInspector::class, static function (Application $app): RequestInspector {
            return new RequestInspector($app->make(SignatureSet::class), $app->make(PayloadNormalizer::class));
        });
    }

    public function boot(): void
    {
        if (! config('security.enabled', true)) {
            return;
        }

        Event::listen(Failed::class, function (Failed $event): void {
            $request = request();
            $identity = is_array($event->credentials)
                ? ($event->credentials['email'] ?? $event->credentials['phone'] ?? null)
                : null;

            $this->app->make(BehaviourMonitor::class)->failedLogin(
                $request,
                null,
                is_string($identity) ? $identity : null
            );
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            $this->app->make(BehaviourMonitor::class)->failedLogin(request());
        });

        Event::listen(Login::class, function (Login $event): void {
            $this->audit('auth.login_success', [
                'guard'   => $event->guard,
                'user_id' => $event->user->getAuthIdentifier(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            $this->audit('auth.logout', [
                'guard'   => $event->guard,
                'user_id' => $event->user?->getAuthIdentifier(),
            ]);
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            $this->audit('auth.password_reset', [
                'user_id' => $event->user->getAuthIdentifier(),
            ]);
        });
    }

    /** Successful/neutral auth events go to the dedicated log, not the DB. */
    private function audit(string $type, array $data): void
    {
        try {
            $request = request();
            Log::channel((string) config('security.log_channel', 'security'))->info($type, array_merge($data, [
                'ip'   => $this->app->make(ClientIpResolver::class)->resolve($request),
                'path' => $request->path(),
            ]));
        } catch (\Throwable) {
            // no-op
        }
    }
}
