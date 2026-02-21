<?php

namespace App\Jobs;

use App\Models\Question;
use App\Services\AI\AIBatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIBatchTriageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchId;
    protected $questionIds;
    protected $type;
    protected $model;

    /**
     * Create a new job instance.
     */
    public function __construct(string $batchId, array $questionIds, string $type, ?string $model = null)
    {
        $this->batchId = $batchId;
        $this->questionIds = $questionIds;
        $this->type = $type;
        $this->model = $model;
    }

    /**
     * Execute the job.
     */
    public function handle(AIBatchService $batchService): void
    {
        $questions = Question::whereIn('id', $this->questionIds)->get();

        try {
            Log::info("[AIBATCH] Starting batch job", [
                'batch_id' => $this->batchId,
                'count' => $questions->count(),
                'type' => $this->type
            ]);

            $result = $batchService->processBatch($questions, $this->type, $this->model);

            $this->updateProgress($result['applied'], count($result['errors']));

            Log::info("[AIBATCH] Batch job finished", [
                'batch_id' => $this->batchId,
                'applied' => $result['applied'],
                'errors' => count($result['errors'])
            ]);

        } catch (\Exception $e) {
            Log::error("[AIBATCH] Batch job failed", [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage()
            ]);
            
            $this->updateProgress(0, count($this->questionIds));
        }
    }

    protected function updateProgress(int $applied, int $errors): void
    {
        $key = "batch_progress_{$this->batchId}";
        $lock = Cache::lock($key . "_lock", 10);

        try {
            $lock->block(5); // Wait up to 5s for lock

            $data = Cache::get($key, [
                'total' => 0,
                'processed' => 0,
                'errors' => 0,
                'status' => 'processing'
            ]);

            $data['processed'] += $applied;
            $data['errors'] += $errors;

            // Mark as completed if all questions in the batch were processed
            if ($data['processed'] + $data['errors'] >= $data['total']) {
                $data['status'] = 'completed';
            }

            Cache::put($key, $data, now()->addHours(2));

        } catch (\Exception $e) {
            Log::error("[AIBATCH] Failed to update progress: " . $e->getMessage());
        } finally {
            $lock->release();
        }
    }
}
