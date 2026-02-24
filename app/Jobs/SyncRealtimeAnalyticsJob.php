<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\GoogleAnalyticsService;
use Illuminate\Support\Facades\Cache;

class SyncRealtimeAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function handle(GoogleAnalyticsService $gaService)
    {
        /**
         * NOTA: Realtime Data no GA4 Data API v1beta usa runRealtimeReport
         * Para simplificar, armazenaremos em cache os resultados por alguns minutos
         * para evitar rate limits.
         */
        if (!$gaService->isConfigured()) {
            return;
        }

        try {
            // Em vez de implementar $gaService->fetchRealtimeMetrics, 
            // este job pode ser chamado por um Scheduler para fazer pré-cache.
            // Para escopo deste MVP de Tempo Real, o Controller resolverá o request 
            // e fará short-lived caching para evitar hit na API.
            // Este Job pode ser implementado no futuro se a demanda do client for alta.
        } catch (\Exception $e) {
            // ...
        }
    }
}
