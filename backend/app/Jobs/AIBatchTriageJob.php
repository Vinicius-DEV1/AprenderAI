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

    public $timeout = 360; // 6 minutes timeout for larger batches

    protected $batchId;
    protected $questionIds;
    protected $type;
    protected $model;
    protected $reprocess;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $batchId, array $questionIds, string $type, ?string $model = null, bool $reprocess = false, ?int $userId = null)
    {
        $this->batchId = $batchId;
        $this->questionIds = $questionIds;
        $this->type = $type;
        $this->model = $model;
        $this->reprocess = $reprocess;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(AIBatchService $batchService): void
    {
        // Safety check for old/corrupted jobs in queue
        if (empty($this->batchId) || empty($this->questionIds)) {
            Log::warning("[AIBATCH] Skipping job: missing batchId or questionIds. If this is an old job retried after a deploy, it cannot be recovered.", [
                'batch_id' => $this->batchId ?? 'NULL',
                'has_questions' => !empty($this->questionIds)
            ]);
            return;
        }

        // Check if batch was cancelled before starting
        $batch = \App\Models\AiProcessingBatch::where('batch_id', $this->batchId)->first();
        if ($batch && $batch->status === 'cancelled') {
            Log::info("[AIBATCH] Batch job skipped (Cancelled)", ['batch_id' => $this->batchId]);
            return;
        }

        $questions = Question::whereIn('id', $this->questionIds)->get();

        try {
            Log::info("[AIBATCH] Starting batch job", [
                'batch_id' => $this->batchId,
                'count' => $questions->count(),
                'type' => $this->type,
                'reprocess' => $this->reprocess
            ]);

            $result = $batchService->processBatch($questions, $this->type, $this->model, $this->batchId, $this->reprocess, $this->userId);

            $this->updateProgress(
                $result['applied'],
                count($result['errors']),
                null,
                $result['errors'] ?? [],
                $result['usage']['input_tokens'] ?? 0,
                $result['usage']['output_tokens'] ?? 0,
                $result['estimated_cost'] ?? 0
            );

            Log::info("[AIBATCH] Batch job finished", [
                'batch_id' => $this->batchId,
                'applied' => $result['applied'],
                'errors' => count($result['errors'])
            ]);

        } catch (\Throwable $e) {
            Log::error("[AIBATCH] Batch job failed", [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage()
            ]);

            $this->updateProgress(0, count($this->questionIds), $e->getMessage());
        }
    }

    protected function updateProgress(
        int $applied,
        int $errors,
        ?string $errorMessage = null,
        array $detailedErrors = [],
        int $inputTokens = 0,
        int $outputTokens = 0,
        float $estimatedCost = 0
    ): void {
        $key = "batch_progress_{$this->batchId}";
        $lock = \Illuminate\Support\Facades\Cache::lock($key . "_lock", 10);

        try {
            $lock->block(5); // Wait up to 5s for lock

            // 1. Atualiza o Cache (para o SSE em tempo real ser rápido)
            $data = \Illuminate\Support\Facades\Cache::get($key);

            // Se cache sumiu (ex: redis flush), tenta recuperar o estado atual do banco
            if (!$data) {
                $dbBatch = \App\Models\AiProcessingBatch::where('batch_id', $this->batchId)->first();
                $data = [
                    'total' => $dbBatch ? $dbBatch->total_count : 0,
                    'processed' => $dbBatch ? $dbBatch->processed_count : 0,
                    'errors' => $dbBatch ? $dbBatch->error_count : 0,
                    'input_tokens' => $dbBatch ? $dbBatch->input_tokens : 0,
                    'output_tokens' => $dbBatch ? $dbBatch->output_tokens : 0,
                    'status' => $dbBatch ? $dbBatch->status : 'processing',
                    'last_error' => null,
                    'errors_log' => $dbBatch ? ($dbBatch->errors_log ?? []) : []
                ];
            }

            $data['processed'] += $applied;
            $data['errors'] += $errors;
            $data['input_tokens'] = ($data['input_tokens'] ?? 0) + $inputTokens;
            $data['output_tokens'] = ($data['output_tokens'] ?? 0) + $outputTokens;
            $data['estimated_cost'] = ($data['estimated_cost'] ?? 0) + $estimatedCost;
            $data['message'] = "Processando " . ($data['processed'] + $data['errors']) . " de " . $data['total'] . "...";

            if (!empty($detailedErrors) || $errorMessage) {
                if ($errorMessage) {
                    $entry = ['time' => now()->toDateTimeString(), 'error' => $errorMessage, 'type' => 'fatal'];
                    $data['last_error'] = $errorMessage;
                    $data['message'] = "Erro Fatal: " . $errorMessage;
                    $data['status'] = 'failed';
                    $data['errors_log'][] = $entry;
                }
                foreach ($detailedErrors as $detail) {
                    $data['errors_log'][] = ['time' => now()->toDateTimeString(), 'error' => $detail, 'type' => 'partial'];
                }
            }
            if ($data['status'] !== 'failed' && ($data['processed'] + $data['errors'] >= $data['total'])) {
                $data['status'] = 'completed';
                $data['message'] = "Concluído!";
            }

            \Illuminate\Support\Facades\Cache::put($key, $data, now()->addHours(2));

            // 2. Atualiza o Banco de Dados (Persistência para o Histórico)
            $dbBatch = \App\Models\AiProcessingBatch::where('batch_id', $this->batchId)->first();
            if ($dbBatch) {
                $dbBatch->processed_count += $applied;
                $dbBatch->error_count += $errors;
                $dbBatch->input_tokens += $inputTokens;
                $dbBatch->output_tokens += $outputTokens;
                $dbBatch->estimated_cost += $estimatedCost;
                $dbBatch->status = $data['status'];

                if (!empty($detailedErrors) || $errorMessage) {
                    $existingLogs = $dbBatch->errors_log ?? [];
                    if ($errorMessage) {
                        $existingLogs[] = [
                            'time' => now()->toDateTimeString(),
                            'error' => $errorMessage,
                            'type' => 'fatal'
                        ];
                    }
                    foreach ($detailedErrors as $detail) {
                        $existingLogs[] = [
                            'time' => now()->toDateTimeString(),
                            'error' => $detail,
                            'type' => 'partial'
                        ];
                    }
                    $dbBatch->errors_log = $existingLogs;
                }

                $dbBatch->save();
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("[AIBATCH] Failed to update progress: " . $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    /**
     * The job failed to process.
     * Captured when the worker throws a Fatal Exception outside the try-catch block (e.g Timeout, Memory Limit).
     */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error("[AIBATCH] Fatal Worker Error: " . $exception->getMessage(), [
            'batch_id' => $this->batchId,
            'trace' => $exception->getTraceAsString()
        ]);

        // Updates cache and DB to inform frontend that the remaining items in this chunk failed mortally.
        $this->updateProgress(
            0,
            count($this->questionIds),
            "FATAL WORKER ERROR: " . $exception->getMessage()
        );
    }
}
