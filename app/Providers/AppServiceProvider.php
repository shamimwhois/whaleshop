<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Console\ServeCommand;
use Karim007\LaravelBkashTokenize\Payment\TBPayment;
use Karim007\LaravelBkashTokenize\Payment\TBRefund;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The bKash tokenize package is excluded from provider discovery (see
        // composer.json) because its service provider registers demo routes
        // that point at a controller the application never publishes. Only the
        // pieces the payment gateway needs are registered here.
        $this->mergeConfigFrom(
            base_path('vendor/karim007/laravel-bkash-tokenize/config/bkash.php'),
            'bkash'
        );

        $this->app->bind('tbpayment', fn () => new TBPayment());
        $this->app->bind('tbrefund', fn () => new TBRefund());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $this->passPhpIniEnvironmentToServeCommand();
    }

    /**
     * `php artisan serve` only forwards a small allow-list of environment
     * variables to the PHP built-in server it spawns. Runtimes that ship PHP
     * behind a launcher (Herd, Flock, XAMPP) load php.ini through PHPRC /
     * PHP_INI_SCAN_DIR, so stripping those starts the server with no php.ini
     * and no extensions, and every request fails with an undefined function
     * error (e.g. mb_split).
     */
    protected function passPhpIniEnvironmentToServeCommand(): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        foreach (['PHPRC', 'PHP_INI_SCAN_DIR'] as $variable) {
            if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                ServeCommand::$passthroughVariables[] = $variable;
            }
        }
    }
}
