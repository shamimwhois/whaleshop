<?php

use App\Http\Controllers\Admin\SecurityDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Security Operations Console
|--------------------------------------------------------------------------
|
| Loaded inside the standard `api` middleware group (see bootstrap/app.php),
| so the API rate limiter and the global IDS middleware apply here too.
| Double-gated: settings permission + ADMIN role check.
|
*/

Route::prefix('admin/security')
    ->name('admin.security.')
    ->middleware([
        'auth:sanctum',
        'permission:settings',
        'securityConsole',
    ])
    ->group(function () {
        Route::get('/overview', [SecurityDashboardController::class, 'overview'])->name('overview');
        Route::get('/events', [SecurityDashboardController::class, 'events'])->name('events');
        Route::get('/ip-rules', [SecurityDashboardController::class, 'ipRules'])->name('ip-rules');
        Route::post('/ip-rules/block', [SecurityDashboardController::class, 'block'])->name('block');
        Route::post('/ip-rules/unblock', [SecurityDashboardController::class, 'unblock'])->name('unblock');
    });
