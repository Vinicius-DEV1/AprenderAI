<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServerMetric;
use App\Services\ServerMetricService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitorController extends Controller
{
    public function index()
    {
        return view('admin.monitor.index');
    }

    public function realtime(ServerMetricService $service)
    {
        // Get instant snapshot for gauges
        $metrics = $service->collect();
        return response()->json($metrics);
    }

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

        // Optimization: For 7d, aggregate by hour to reduce data points
        if ($range === '7d') {
            // SQLite/MySQL compatible grouping
            // Assuming MySQL for production, but SQLite for local is common. 
            // Laravel's selectRaw is driver dependent. 
            // For simplicity in this iteration, we will select all rows but limit via 'take' if too many, 
            // OR use a standard grouping.
            // Let's stick to raw data for now, but limit to latest 2000 points to avoid browser crash.
            // If data points > 2000, maybe take every Nth row.
            
            // Better approach: Let's just return raw data ordered by date. 
            // If the user has 1 point/min * 60 * 24 * 7 = 10k points. That's a bit much.
            // Let's sample if > 24h.
            
            $data = $query->orderBy('created_at')->get();
            
            // Downsampling in PHP to be DB agnostic
            if ($data->count() > 1000) {
                $step = floor($data->count() / 1000);
                $data = $data->nth((int)$step);
            }
            
            return response()->json($data->values());
        }

        return response()->json($query->orderBy('created_at')->get());
    }
}
