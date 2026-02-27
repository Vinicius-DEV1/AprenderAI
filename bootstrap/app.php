<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->alias([
            'check.api.key' => \App\Http\Middleware\CheckAPIKey::class,
            'check.plan.limits' => \App\Http\Middleware\CheckPlanLimits::class,
            'is.admin' => \App\Http\Middleware\IsAdmin::class,
            'check.payment.active' => \App\Http\Middleware\CheckPaymentActive::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/mercadopago',
            'webhooks/asaas',
            'api/v1/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

