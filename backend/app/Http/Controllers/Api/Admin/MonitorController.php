<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServerMetric;
use App\Services\ServerMetricService;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    /**
     * Retorna os dados em tempo real para os "Gauges" (medidores).
     */
    public function realtime(ServerMetricService $service)
    {
        $metrics = $service->collect();
        return response()->json($metrics);
    }

    /**
     * Retorna o histórico de métricas para montar os gráficos Lineares.
     */
    public function history(Request $request)
    {
        $range = $request->input('range', '24h');

        $query = ServerMetric::query();

        switch ($range) {
            case '1h':
                $query->where('created_at', '>=', now()->subHour());
                break;
            case '7d':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case '24h':
            default:
                $query->where('created_at', '>=', now()->subDay());
                break;
        }

        $data = $query->orderBy('created_at')->get();

        if ($range === '7d' && $data->count() > 1000) {
            $step = max(1, floor($data->count() / 1000));
            $data = $data->nth((int) $step);
        }

        return response()->json($data->values());
    }
}
