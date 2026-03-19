<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * HealthController — Endpoint de saúde para:
 *  1. Docker healthcheck (sentinel /tmp/app_ready + este endpoint)
 *  2. Frontend deploy detection (polls este endpoint durante deploy)
 *  3. Nginx upstream readiness (usado via fastcgi_next_upstream)
 *
 * Retorna 200 {"status":"ok"} quando tudo está saudável.
 * Retorna 503 {"status":"degraded"} se DB ou Redis estiverem fora.
 *
 * IMPORTANTE: Esta rota é PÚBLICA (sem middleware auth).
 */
class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks  = [];
        $healthy = true;

        // -------------------------------
        // 1. Database connectivity check
        // -------------------------------
        try {
            DB::connection()->getPdo();
            $checks['database'] = 'ok';
        } catch (\Exception) {
            $checks['database'] = 'error';
            $healthy            = false;
        }

        // -------------------------------
        // 2. Redis connectivity check
        // -------------------------------
        try {
            Redis::ping();
            $checks['redis'] = 'ok';
        } catch (\Exception) {
            $checks['redis'] = 'error';
            $healthy          = false;
        }

        // -------------------------------
        // 3. PHP-FPM sentinel check
        //    (garante que o entrypoint terminou a inicialização)
        //
        // FIX: O arquivo /tmp/app_ready é criado pelo entrypoint Docker apenas em
        // produção. Em ambiente local (APP_ENV != production) ele nunca existe,
        // causando 503 permanente e fazendo o overlay de deploy ficar preso para sempre.
        // Solução: verificar o sentinel somente em produção.
        // -------------------------------
        if (app()->environment('production')) {
            $sentinelReady  = file_exists('/tmp/app_ready');
            $checks['boot'] = $sentinelReady ? 'ok' : 'initializing';

            if (! $sentinelReady) {
                $healthy = false;
            }
        } else {
            // Em dev/local, o sentinel não é usado — considera boot sempre ok.
            $checks['boot'] = 'ok (dev)';
        }


        $statusCode = $healthy ? 200 : 503;

        return response()->json([
            'status'  => $healthy ? 'ok' : 'degraded',
            'version' => config('app.version', '1.0.0'),
            'ts'      => now()->toIso8601String(),
            'checks'  => $checks,
        ], $statusCode);
    }
}
