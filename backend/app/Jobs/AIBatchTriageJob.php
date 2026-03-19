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

    public $timeout = 600; // 10 minutes timeout for larger batches
    public $tries = 1;    // Do not retry automatically — manual retry available in the dashboard

    protected $batchId;
    protected $questionIds;
    protected $type;
    protected $model;
    protected $reprocess;
    protected $userId;
    protected $chunkIndex;
    protected $delaySeconds;
    protected $retryAttempt;

    /**
     * Create a new job instance.
     */
    public function __construct(string $batchId, array $questionIds, string $type, ?string $model = null, bool $reprocess = false, ?int $userId = null, int $chunkIndex = 0, int $delaySeconds = 0, int $retryAttempt = 0)
    {
        $this->batchId = $batchId;
        $this->questionIds = $questionIds;
        $this->type = $type;
        $this->model = $model;
        $this->reprocess = $reprocess;
        $this->userId = $userId;
        $this->chunkIndex = $chunkIndex;
        $this->delaySeconds = $delaySeconds;
        $this->retryAttempt = $retryAttempt;

        // Ensure the job goes to the correct AI-dedicated queue
        $this->onQueue(config('xavier.embeddings.batch_queue', 'embeddings'));
    }

    /**
     * Execute the job.
     */
    public function handle(AIBatchService $batchService): void
    {
        if (empty($this->batchId) || empty($this->questionIds)) {
            Log::warning("[AIBATCH] Skipping job: missing batchId or questionIds. If this is an old job retried after a deploy, it cannot be recovered.", [
                'batch_id' => $this->batchId ?? 'NULL',
                'has_questions' => !empty($this->questionIds)
            ]);
            return;
        }

        // Circuit Breaker: If no API keys are available for triage, release the job back to the queue
        // to wait for quota reset or manual intervention, preventing mass failures.
        $aiService = app(\App\Services\AI\AIService::class);
        if (!$aiService->hasActiveKey(\App\Models\ApiKey::CAPABILITY_TRIAGE)) {
            Log::info("[AIBATCH] No active keys for triage. Releasing batch #{$this->batchId} chunk #{$this->chunkIndex} to retry in 5 minutes.");
            $this->release(300); // 5 minutes backoff
            return;
        }

        // Check if batch was cancelled or failed before starting
        $batch = \App\Models\AiProcessingBatch::where('batch_id', $this->batchId)->first();
        if ($batch && in_array($batch->status, ['cancelled', 'failed'])) {
            Log::info("[AIBATCH] Batch job skipped ({$batch->status})", ['batch_id' => $this->batchId]);
            $this->writeChunkPhase('skipped');
            return;
        }

        // If this is not the first chunk and we have a delay, signal the delay phase
        if ($this->chunkIndex > 0 && $this->delaySeconds > 0) {
            $delayEndsAt = now()->addSeconds($this->delaySeconds)->toIso8601String();
            $this->writeChunkPhase('delay', [
                'delay_ends_at' => $delayEndsAt,
                'delay_seconds' => $this->delaySeconds,
            ]);
            sleep($this->delaySeconds);
        }

        // Signal that this chunk is now processing
        $this->writeChunkPhase('processing', [
            'chunk_index' => $this->chunkIndex + 1,
            'chunk_started_at' => now()->toIso8601String(),
            'chunk_size' => count($this->questionIds),
        ]);

        $questions = Question::with('images')->whereIn('id', $this->questionIds)->get();

        try {
            Log::info("[AIBATCH] Starting batch job", [
                'batch_id' => $this->batchId,
                'chunk_index' => $this->chunkIndex,
                'question_ids_received' => count($this->questionIds),
                'questions_found' => $questions->count(),
                'type' => $this->type,
                'reprocess' => $this->reprocess,
                'retry_attempt' => $this->retryAttempt
            ]);

            $result = $batchService->processBatch($questions, $this->type, $this->model, $this->batchId, $this->reprocess, $this->userId);

            $batchStats = $result['stats'] ?? [];
            if (isset($result['api_key_name'])) {
                $batchStats['api_usage'] = [$result['api_key_name'] => 1];
            }

            $this->updateProgress(
                $result['applied'],
                count($result['errors']),
                null,
                $result['errors'] ?? [],
                $result['usage']['input_tokens'] ?? 0,
                $result['usage']['output_tokens'] ?? 0,
                $result['estimated_cost'] ?? 0,
                $batchStats
            );

            Log::info("[AIBATCH] Batch job finished", [
                'batch_id' => $this->batchId,
                'chunk_index' => $this->chunkIndex,
                'applied' => $result['applied'],
                'errors' => count($result['errors']),
                'retry_attempt' => $this->retryAttempt
            ]);

            $this->writeChunkPhase('idle');

        } catch (\App\Exceptions\AIServiceBusyException $e) {
            // POOL BUSY: The dedicated AI keys for triage are currently locked or blacklisted.
            // We release the job back to the queue for a retry in 5 minutes.
            Log::info("[AIBATCH] AI key pool busy for batch #{$this->batchId}. Releasing chunk #{$this->chunkIndex}.");
            $aiService->registerCongestion('AIBatchTriageJob', "Batch: {$this->batchId} | Chunk: {$this->chunkIndex}");
            $this->writeChunkPhase('idle');
            $this->release(300);
            return;
        } catch (\Throwable $e) {
            Log::error("[AIBATCH] Batch job failed", [
                'batch_id' => $this->batchId,
                'chunk_index' => $this->chunkIndex,
                'error' => $e->getMessage(),
                'retry_attempt' => $this->retryAttempt
            ]);

            $this->writeChunkPhase('idle');
            $this->updateProgress(0, count($this->questionIds), $e->getMessage());
        }
    }

    /**
     * Write the current chunk phase info to cache for real-time frontend display.
     */
    protected function writeChunkPhase(string $phase, array $extra = []): void
    {
        Cache::put("batch_chunk_status_{$this->batchId}", array_merge([
            'phase' => $phase,
            'chunk_index' => $this->chunkIndex + 1,
            'updated_at' => now()->toIso8601String(),
        ], $extra), now()->addHours(2));
    }

    protected function updateProgress(
        int $applied,
        int $errors,
        ?string $errorMessage = null,
        array $detailedErrors = [],
        int $inputTokens = 0,
        int $outputTokens = 0,
        float $estimatedCost = 0,
        array $stats = []
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
                    'errors_log' => $dbBatch ? ($dbBatch->errors_log ?? []) : [],
                    'stats' => $dbBatch && $dbBatch->stats ? $dbBatch->stats : [
                        'difficulty' => 0,
                        'explanation' => 0,
                        'subjects' => 0,
                        'topics' => 0,
                        'sent_to_review' => 0,
                        'approved' => 0,
                        'low_quality' => 0,
                    ]
                ];
            }

            // Inicializa stats se não existir
            if (!isset($data['stats'])) {
                $data['stats'] = [
                    'difficulty' => 0,
                    'explanation' => 0,
                    'subjects' => 0,
                    'topics' => 0,
                    'sent_to_review' => 0,
                    'approved' => 0,
                    'low_quality' => 0,
                ];
            }

            // Incrementa os stats
            foreach ($stats as $sKey => $val) {
                if ($sKey === 'api_usage' && is_array($val)) {
                    if (!isset($data['stats'][$sKey]) || !is_array($data['stats'][$sKey])) {
                        $data['stats'][$sKey] = [];
                    }
                    foreach ($val as $keyName => $count) {
                        $data['stats'][$sKey][$keyName] = ($data['stats'][$sKey][$keyName] ?? 0) + $count;
                    }
                } else {
                    $data['stats'][$sKey] = ($data['stats'][$sKey] ?? 0) + (is_numeric($val) ? $val : 0);
                }
            }

            $data['processed'] += $applied;
            $data['errors'] += $errors;
            $data['input_tokens'] = ($data['input_tokens'] ?? 0) + $inputTokens;
            $data['output_tokens'] = ($data['output_tokens'] ?? 0) + $outputTokens;
            $data['estimated_cost'] = ($data['estimated_cost'] ?? 0) + $estimatedCost;

            // Increment chunk progress
            if (($this->retryAttempt ?? 0) === 0) {
                $data['initial_chunks_processed'] = ($data['initial_chunks_processed'] ?? 0) + 1;
            } else {
                $data['retry_chunks_processed'] = ($data['retry_chunks_processed'] ?? 0) + 1;
                $data['retries_success'] = ($data['retries_success'] ?? 0) + $applied;
                $data['retries_failed'] = ($data['retries_failed'] ?? 0) + $errors;
            }

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
                // AUTO-RETRY LOGIC: Se terminou com erros e é a primeira tentativa
                if ($data['errors'] > 0 && ($this->retryAttempt ?? 0) < 1) {
                    $this->triggerAutoRetry($data);
                    return; // Retorna para não marcar como completed ainda
                }

                $data['status'] = 'completed';
                $data['message'] = "Concluído!";
            }

            \Illuminate\Support\Facades\Cache::put($key, $data, now()->addHours(2));

            // 2. Atualiza o Banco de Dados (Persistência para o Histórico) - Usando Incrementos Atômicos
            \Illuminate\Support\Facades\DB::table('ai_processing_batches')
                ->where('batch_id', $this->batchId)
                ->incrementEach([
                    'processed_count' => $applied,
                    'error_count' => $errors,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'estimated_cost' => $estimatedCost
                ]);

            // 3. Atualiza Status e Stats separadamente com proteção contra sobrescrita de finalização
            $updateData = [
                'stats' => json_encode($data['stats']),
                'updated_at' => now()
            ];

            // FAIL-SAFE: Se atingiu o total, marca como completed atômicamente no banco
            if ($data['processed'] + $data['errors'] >= $data['total'] && $data['total'] > 0) {
                $updateData['status'] = 'completed';
            }

            \Illuminate\Support\Facades\DB::table('ai_processing_batches')
                ->where('batch_id', $this->batchId)
                ->update($updateData);

            if (!empty($detailedErrors) || $errorMessage) {
                $dbBatch = \App\Models\AiProcessingBatch::where('batch_id', $this->batchId)->first();
                if ($dbBatch) {
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
                    $dbBatch->update(['errors_log' => $existingLogs]);
                }
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

    /**
     * Triggers the second attempt for questions that failed in this batch.
     */
    protected function triggerAutoRetry(array &$currentProgress): void
    {
        $failedItems = \App\Models\AiBatchItem::where('batch_id', $this->batchId)
            ->where('status', 'failed')
            ->get();

        if ($failedItems->isEmpty()) {
            Log::info("[AIBATCH] No failed items to retry for batch {$this->batchId}");
            $currentProgress['status'] = 'completed';
            $currentProgress['message'] = "Concluído (Sem falhas para re-tentativa).";
            Cache::put("batch_progress_{$this->batchId}", $currentProgress, now()->addHours(2));
            return;
        }

        $failedIds = $failedItems->pluck('question_id')->toArray();
        $count = count($failedIds);

        Log::info("[AIBATCH] Triggering AUTO-RETRY Phase", [
            'batch_id' => $this->batchId,
            'failed_count' => $count,
            'original_total' => $currentProgress['total']
        ]);

        // 1. Atualiza o status para o Frontend ver a mudança
        $currentProgress['status'] = 'retrying';
        $currentProgress['message'] = "🔄 Re-tentativa automática iniciada para {$count} questões que falharam...";
        // Importante: Não resetamos o 'processed', mas resetamos o 'errors' que serão re-tentados
        $currentProgress['errors'] -= $count;
        Cache::put("batch_progress_{$this->batchId}", $currentProgress, now()->addHours(2));

        // 2. Atualiza no Banco também
        \App\Models\AiProcessingBatch::where('batch_id', $this->batchId)->update([
            'status' => 'retrying',
            'error_count' => \Illuminate\Support\Facades\DB::raw("error_count - {$count}")
        ]);

        // 3. Dispara os novos jobs com chunk_size REDUZIDO (2) para garantir sucesso
        $smallChunkSize = 2;
        $chunks = array_chunk($failedIds, $smallChunkSize);

        $currentProgress['retry_chunks_total'] = count($chunks);
        $currentProgress['retry_chunks_processed'] = 0;
        $currentProgress['retries_count'] = count($failedIds);
        $currentProgress['retries_success'] = 0;
        $currentProgress['retries_failed'] = 0;
        \Illuminate\Support\Facades\Cache::put("batch_progress_{$this->batchId}", $currentProgress, now()->addHours(2));

        foreach ($chunks as $index => $chunkIds) {
            dispatch(new self(
                $this->batchId,
                $chunkIds,
                $this->type,
                $this->model,
                $this->reprocess,
                $this->userId,
                $index + 1000, // Offset index para não colidir visualmente
                0, // Sem delay na re-tentativa
                1  // retryAttempt = 1
            ))->onQueue(config('xavier.embeddings.batch_queue', 'embeddings'));
        }
    }
}
