<?php

use App\Http\Middleware\EnsureUserRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
