<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSearchRequest;
use App\Models\AiSearchCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class XavierInsightsController extends Controller
{
    /**
     * Retorna métricas globais e estatísticas do Xavier.
     */
    public function index()
    {
        $totalRequests = AiSearchRequest::count();
        $successRequests = AiSearchRequest::where('status', 'completed')->count();
        $failedRequests = AiSearchRequest::where('status', 'failed')->count();

        $successRate = $totalRequests > 0 ? round(($successRequests / $totalRequests) * 100, 1) : 0;

        // Estatísticas de Cache
        $totalCacheEntries = AiSearchCache::count();

        // Buscas por status (últimos 7 dias)
        $historyData = AiSearchRequest::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as count'),
            DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success"),
            DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
        )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top Termos Buscados
        $topPrompts = AiSearchRequest::select('prompt', DB::raw('count(*) as total'))
            ->groupBy('prompt')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Estimativa de Economia (Latency)
        // IA costuma demorar ~3-5s. Cache L1/L2 é quase instantâneo (<500ms).
        // Vamos considerar apenas buscas "completed" que foram rápidas (inferidas via cache hit)
        // Como não logamos latência exata ainda, vamos dar um insight baseado em records de cache.

        return response()->json([
            'stats' => [
                'total_searches' => $totalRequests,
                'success_rate' => $successRate,
                'failed_count' => $failedRequests,
                'cache_entries' => $totalCacheEntries,
            ],
            'chart_data' => $historyData,
            'top_prompts' => $topPrompts,
            'recent_requests' => AiSearchRequest::with('user:id,name')->orderByDesc('created_at')->limit(10)->get()
        ]);
    }

    /**
     * Histórico detalhado de buscas.
     */
    public function history(Request $request)
    {
        $history = AiSearchRequest::with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($history);
    }
}
