<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        web: __DIR__ . '/../routes/web.php',
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
        $exceptions->report(function (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('GLOBAL EXCEPTION CAUGHT: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });
    })->create();

