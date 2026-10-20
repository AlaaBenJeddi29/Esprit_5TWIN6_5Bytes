<?php

use App\Http\Controllers\Monitoring\MetricsController;
use App\Http\Middleware\CollectHttpMetrics;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
<<<<<<< Updated upstream
            Route::get('/metrics', MetricsController::class)
                ->name('monitoring.metrics');
=======
            // Plain route (no web/api group): Prometheus scrapes it without
            // creating sessions, cookies, or database-backed session rows.
            Route::get('/metrics', MetricsController::class)->name('monitoring.metrics');
>>>>>>> Stashed changes
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(CollectHttpMetrics::class);

        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);

        // Webhook Stripe uniquement : pas de jeton CSRF (authentifié par la
        // signature Stripe). Aucune autre route n'est exemptée.
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
    })
    ->withSchedule(function ($schedule) {
        $schedule->command('drought:refresh-forecasts')
            ->dailyAt('23:00')
            ->timezone(config('app.timezone'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

