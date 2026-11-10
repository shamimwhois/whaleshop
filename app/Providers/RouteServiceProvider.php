<?php

namespace App\Providers;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        // Attached to the whole api group via bootstrap/app.php throttleApi().
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('security.rate_limits.api', 120))
                ->by($request->user()?->id ?: $request->ip());
        });

        // Tight limiter for unauthenticated credential endpoints: stops
        // credential stuffing, OTP brute force and account enumeration.
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute((int) config('security.rate_limits.auth_per_minute', 10))
                    ->by(strtolower((string) $request->path()).'|'.$request->ip())
                    ->response(static function (): \Illuminate\Http\JsonResponse {
                        return response()->json([
                            'success' => false,
                            'message' => 'Too many attempts. Please try again later.',
                            'code'    => 'RATE_LIMITED',
                        ], 429);
                    }),
                Limit::perHour((int) config('security.rate_limits.auth_per_hour', 60))
                    ->by('hour|'.$request->ip()),
            ];
        });

        // One OTP per identifier per minute, plus a hard per-IP ceiling.
        RateLimiter::for('otp', function (Request $request) {
            $identifier = (string) ($request->input('email') ?: $request->input('phone') ?: 'unknown');

            return [
                Limit::perMinute((int) config('security.rate_limits.otp_per_minute', 1))
                    ->by('otp-id|'.strtolower($identifier)),
                Limit::perMinute((int) config('security.rate_limits.otp_ip_per_minute', 10))
                    ->by('otp-ip|'.$request->ip()),
            ];
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function mapWebRoutes()
    {
        if (file_exists(storage_path('installed'))) {

            try {
                $files = scandir(__DIR__ . '/../Http/PaymentGateways/Routes');
                if (count($files) > 2) {
                    foreach ($files as $file) {
                        if ($file != '.' && $file != '..') {
                            Route::middleware('web')
                                ->group(__DIR__ . "/../Http/PaymentGateways/Routes/{$file}");
                        }
                    }
                }
            } catch (Exception $e) {
                Log::info($e->getMessage());
            }
        }
    }
}
