<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Artisan;
use App\Services\QueueTrackerService;

/**
 * WorkerMonitorController
 *
 * Provides deep observability into the worker cluster, queues, concurrency limits,
 * and failed jobs. Powers the Admin Semantic Dashboard.
 */
class WorkerMonitorController extends Controller
{
    /**
     * @var QueueTrackerService
     */
    protected $tracker;

    public function __construct(QueueTrackerService $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * GET /admin/monitor/workers
     * Returns an overview of queue health, running workers, and concurrency slots.
     */
    public function overview(Request $request)
    {
        // 1. Queue Metrics
        $queueMetrics = $this->tracker->getMetrics();

        // 2. Concurrency Slots Currently Acquired (AI Triage & Embeddings)
        $redis = Redis::connection();
        
        $concurrency = [
            'ai_triage' => $this->inspectConcurrencySlots($redis, 'ai_triage', (int) \App\Models\Configuration::get('xavier_max_concurrent_triage', config('xavier.concurrency.max_triage', 5))),
            'embeddings' => $this->inspectConcurrencySlots($redis, 'embeddings', (int) \App\Models\Configuration::get('xavier_max_concurrent_embeddings', config('xavier.concurrency.max_embeddings', 5))),
            'import' => $this->inspectConcurrencySlots($redis, 'import', (int) \App\Models\Configuration::get('xavier_max_concurrent_import', config('xavier.concurrency.max_import', 1))),
        ];

        // 3. Simple Failed Job Count
        $failedCount = DB::table('failed_jobs')->count();

        // 4. Redis Memory Usage (Optional, helps detect leaks)
        $redisInfo = $redis->info('memory');
        $memoryHuman = $redisInfo['used_memory_human'] ?? 'unknown';

        return response()->json([
            'status' => 'success',
            'data' => [
                'queues'      => $queueMetrics,
                'concurrency' => $concurrency,
                'failed_jobs' => $failedCount,
                'redis_memory' => $memoryHuman,
            ]
        ], 200);
    }

    /**
     * Inspects active Redis semaphores for a given prefix.
     */
    private function inspectConcurrencySlots(\Illuminate\Redis\Connections\Connection $redis, string $prefix, int $max): array
    {
        $activeCount = 0;
        $activeSlots = [];

        for ($i = 0; $i < $max; $i++) {
            $key = "concurrency_slot:{$prefix}:{$i}";
            if ($redis->exists($key)) {
                $activeCount++;
                $ttl = $redis->ttl($key);
                $activeSlots[] = [
                    'slot' => $i,
                    'expires_in_seconds' => $ttl > 0 ? $ttl : 0,
                ];
            }
        }

        return [
            'max_allowed' => $max,
            'active'      => $activeCount,
            'slots'       => $activeSlots,
        ];
    }

    /**
     * GET /admin/monitor/failed-jobs
     * Lists failed jobs with pagination.
     */
    public function listFailedJobs(Request $request)
    {
        $query = DB::table('failed_jobs')->orderBy('failed_at', 'desc');

        if ($request->filled('queue')) {
            $query->where('queue', $request->query('queue'));
        }

        $failedJobs = $query->paginate($request->query('per_page', 50));

        // Mask payloads if they are massive to prevent payload-too-large errors 
        // to the frontend, parsing just the class name.
        $failedJobs->getCollection()->transform(function ($job) {
            $payload = json_decode($job->payload, true);
            $job->command_name = $payload['displayName'] ?? 'Unknown Job';
            unset($job->payload); // Don't send full payload payload
            return $job;
        });

        return response()->json([
            'status' => 'success',
            'recent_completed' => app(\App\Services\QueueTrackerService::class)->getRecentCompletedJobs(),
            'failed'    => $failedJobs,
        ], 200);
    }

    /**
     * POST /admin/monitor/failed-jobs/{id}/retry
     * Retries a specific failed job.
     */
    public function retryFailedJob(string $id)
    {
        $job = DB::table('failed_jobs')->where('id', $id)->first();
        if (!$job) {
            return response()->json(['error' => 'Job not found.'], 404);
        }

        $idStr = is_array($id) ? implode(',', $id) : (string) $id;
        Artisan::call('queue:retry', ['id' => [$idStr]]);
        
        return response()->json([
            'status' => 'success',
            'message' => "Job {$idStr} retried successfully."
        ], 200);
    }

    /**
     * POST /admin/monitor/failed-jobs/retry-all
     */
    public function retryAllFailedJobs()
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'All failed jobs pushed back to the queue.'
        ], 200);
    }

    /**
     * DELETE /admin/monitor/failed-jobs
     * Clears all failed jobs.
     */
    public function clearFailedJobs()
    {
        // Try to use flush if available, otherwise just truncate
        try {
            Artisan::call('queue:flush');
        } catch (\Exception $e) {
            // Fallback or silent fail if command doesn't exist
        }

        DB::table('failed_jobs')->truncate(); // Hard truncate is the most reliable way
        
        return response()->json([
            'status' => 'success',
            'message' => 'Failed jobs queue cleared.'
        ], 200);
    }

    /**
     * DELETE /admin/monitor/pending-triage
     * Clears all pending AI triage processes by cancelling batches and clearing semaphores.
     */
    public function clearPendingTriage()
    {
        try {
            DB::beginTransaction();

            // 1. Cancel all active AI batches
            \App\Models\AiProcessingBatch::whereIn('status', ['processing', 'retrying'])
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            // 2. Clear Redis semaphores
            Redis::del('ai_triage');
            Redis::del('ai_triage_queue');

            // 3. Clear pending jobs from DB (if using database queue driver)
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                    // The instruction implies ordering, but for a delete operation,
                    // we just need to target the correct jobs.
                    // Ordering and limiting are typically for selection, not deletion.
                    DB::table('jobs')
                        ->where('payload', 'like', '%AIBatchTriageJob%')
                        ->delete();
                }
            } catch (\Exception $e) { /* Ignore if jobs table missing */ }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Processamentos de triagem cancelados e semáforos liberados."
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao limpar fila de triagem: ' . $e->getMessage()
            ], 500);
        }
    }
}
