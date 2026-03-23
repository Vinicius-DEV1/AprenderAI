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

    /**
     * Retorna os detalhes em tempo real de filas e trabalhos.
     */
    public function queues()
    {
        $payloadToClassName = function ($payload) {
            $data = json_decode($payload, true);
            if (!$data)
                return 'Unknown';
            if (isset($data['displayName'])) {
                return class_basename($data['displayName']);
            }
            if (isset($data['job'])) {
                return class_basename($data['job']);
            }
            return 'Unknown';
        };

        // Jobs Ativos/Pendentes (da tabela 'jobs')
        $jobs = \Illuminate\Support\Facades\DB::table('jobs')
            ->orderBy('id', 'asc') // Keep oldest first as requested
            ->limit(200) // Increased limit for better visibility
            ->get()
            ->map(function ($job) use ($payloadToClassName) {
                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'name' => $payloadToClassName($job->payload),
                    'attempts' => $job->attempts,
                    'is_processing' => $job->reserved_at !== null,
                    'created_at' => \Carbon\Carbon::createFromTimestamp($job->created_at)->toIso8601String(),
                ];
            });

        // Jobs Falhados (da tabela 'failed_jobs')
        $failedJobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($job) use ($payloadToClassName) {
                // Tenta extrair apenas a primeira linha da exception (mensagem de erro principal)
                $exceptionSummary = $job->exception;
                $exceptionLines = explode("\n", $job->exception);
                if (count($exceptionLines) > 0) {
                    $exceptionSummary = $exceptionLines[0];
                }

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'name' => $payloadToClassName($job->payload),
                    'exception' => $exceptionSummary,
                    'failed_at' => $job->failed_at,
                ];
            });

        // Lotes Concluídos (da tabela 'job_batches' + 'ai_processing_batches')
        $completedBatches = \Illuminate\Support\Facades\DB::table('job_batches')
            ->whereNotNull('finished_at')
            ->orderBy('finished_at', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'name' => 'Lote: ' . class_basename($batch->name),
                    'total_jobs' => $batch->total_jobs,
                    'failed_jobs' => $batch->failed_jobs,
                    'finished_at' => \Carbon\Carbon::createFromTimestamp($batch->finished_at)->toIso8601String(),
                ];
            });

        $completedAiBatches = \Illuminate\Support\Facades\DB::table('ai_processing_batches')
            ->whereIn('status', ['completed', 'finished', 'done', 'success'])
            ->orderBy('updated_at', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->batch_id,
                    'name' => 'IA: ' . ucfirst($batch->type),
                    'total_jobs' => $batch->total_count,
                    'failed_jobs' => $batch->error_count ?? 0,
                    'finished_at' => \Carbon\Carbon::parse($batch->updated_at)->toIso8601String(),
                ];
            });

        $allCompleted = $completedBatches->concat($completedAiBatches)
            ->sortByDesc('finished_at')
            ->take(30)
            ->values();

        $response = [
            'jobs' => $jobs,
            'failed' => $failedJobs,
            'completed' => $allCompleted,
            'recent_completed' => app(\App\Services\QueueTrackerService::class)->getRecentCompletedJobs(),
            'congestion' => app(\App\Services\AI\AIService::class)->getCongestionList(),
        ];

        \Illuminate\Support\Facades\Log::debug("[Monitor] API Queues Fetch: " . 
            count($jobs) . " pendentes, " . 
            count($response['recent_completed']) . " concluídos recentes.");

        return response()->json($response);
    }
}
