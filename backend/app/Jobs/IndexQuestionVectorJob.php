<?php

namespace App\Jobs;

use App\Models\Question;
use App\Models\QuestionVector;
use App\Services\AI\AIService;
use App\Services\AI\EmbeddingTextBuilder;
use App\Services\AI\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * IndexQuestionVectorJob
 *
 * Generates 3 named embeddings (statement, concept, explanation) for an
 * approved question and upserts the multi-vector point to Qdrant.
 *
 * Only processes questions with review_status = 'approved'.
 * Skips reindexing if the structured text hash has not changed (zero API cost).
 * Increments index_version on each successful reindex.
 *
 * Queue: embeddings
 */
class IndexQuestionVectorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tries limit.
     * With a 5-minute backoff (300s), 576 tries = 48 hours (2 days) of resilience.
     */
    public int $tries   = 576; 
    public int $timeout = 120;

    public function __construct(protected int $questionId)
    {
        $this->onQueue(config('xavier.embeddings.queue', 'embeddings'));
    }

    public function handle(
        AIService             $aiService,
        EmbeddingTextBuilder  $textBuilder,
        QdrantService         $qdrant
    ): void {
        /** @var Question|null $question */
        $question = Question::with(['subjects', 'topics', 'concepts', 'alternatives'])
            ->find($this->questionId);

        if (!$question) {
            Log::warning("[Xavier][IndexQuestion] Question #{$this->questionId} not found.");
            return;
        }

        // Circuit Breaker: If no API keys are available for embedding, release the job back to the queue
        // to wait for quota reset or manual intervention, preventing mass failures.
        if (!$aiService->hasActiveKey(\App\Models\ApiKey::CAPABILITY_EMBEDDING)) {
            Log::info("[Xavier][IndexQuestion] No active keys for embedding. Releasing question #{$this->questionId} to retry in 1 minute.");
            $aiService->registerCongestion('IndexQuestionVectorJob', $this->questionId);
            $this->release(60); // 1 minute backoff for global empty pool
            return;
        }

        // Only index approved questions
        if ($question->review_status !== 'approved' && !is_null($question->review_status)) {
            // Allow null (manually created questions that are always published)
            if (!is_null($question->review_status)) {
                Log::info("[Xavier][IndexQuestion] Skipping #{$this->questionId} — not approved.");
                return;
            }
        }

        // Build the 3 structured texts
        $statementText    = $textBuilder->buildForQuestion($question);
        $conceptText      = $textBuilder->buildConceptTextForQuestion($question);
        $explanationText  = $textBuilder->buildExplanationTextForQuestion($question);

        // Compute hash of all 3 texts combined to detect content changes
        $combinedHash = hash('sha256', $statementText . $conceptText . $explanationText);

        // Check if already indexed with same content AND same pipeline version
        $vectorRecord = QuestionVector::find($this->questionId);
        $currentPipeline = config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser');
        
        if ($vectorRecord && 
            !$vectorRecord->hasContentChanged($combinedHash) && 
            $vectorRecord->pipeline_version === $currentPipeline) {
            Log::info("[Xavier][IndexQuestion] Skipping #{$this->questionId} — content and pipeline unchanged.");
            return;
        }

        // Ensure Qdrant collection exists
        $qdrant->ensureQuestionsCollection();

        // Gerar 3 embeddings — um para cada named vector no Qdrant.
        // Cada um é formatado de forma diferente pelo EmbeddingTextBuilder para capturar
        // facetas semânticas distintas (enunciado completo, conceitos, explicação).
        // Usa taskType='RETRIEVAL_DOCUMENT' para que o Gemini otimize os vetores
        // para serem encontrados (não para encontrar documentos).
        $userId = null; // System job, no user attribution
        Log::info("[Xavier][IndexQuestion] Generating 3 vectors for #{$this->questionId}...");
        
        try {
            $statementVector   = $aiService->generateEmbedding($statementText,   $userId, 'RETRIEVAL_DOCUMENT');
            Log::debug("[Xavier][IndexQuestion] #{$this->questionId} statement vector: OK");
            
            $conceptVector     = $aiService->generateEmbedding($conceptText,     $userId, 'RETRIEVAL_DOCUMENT');
            Log::debug("[Xavier][IndexQuestion] #{$this->questionId} concept vector: OK");
            
            $explanationVector = $aiService->generateEmbedding($explanationText, $userId, 'RETRIEVAL_DOCUMENT');
            Log::debug("[Xavier][IndexQuestion] #{$this->questionId} explanation vector: OK");
            
            if (!$statementVector || !$conceptVector || !$explanationVector) {
                throw new \RuntimeException('Um dos vetores retornou inesperadamente vazio.');
            }
        } catch (\App\Exceptions\AIServiceBusyException $e) {
            // POOL BUSY OR LOCKED: All keys are currently used by other workers.
            // Release back to queue with shorter delay (30s) to retry quickly
            Log::info("[IndexQuestionVectorJob] AI Key pool busy/locked for question #{$this->questionId}. Releasing for 30s backoff.");
            $aiService->registerCongestion('IndexQuestionVectorJob', $this->questionId);
            $this->release(30);
            return;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            
            // Check for Quota or other retriable failover failures
            $isQuota = str_contains(strtolower($msg), '429') || 
                       str_contains(strtolower($msg), 'quota') || 
                       str_contains(strtolower($msg), 'full failover failure');

            if ($isQuota) {
                Log::warning("[IndexQuestionVectorJob] Quota limit hit or all providers failed for #{$this->questionId}. Releasing for 2m. Error: {$msg}");
                $aiService->registerCongestion('IndexQuestionVectorJob', $this->questionId);
                $this->release(120);
                return;
            }

            // Permanent failure: Log and fail the job definitively.
            Log::error("[IndexQuestionVectorJob] Permanent error indexing question #{$this->questionId}: " . $msg);
            $this->fail($e);
        }

        // Build Qdrant payload for filtering
        $payload = $this->buildPayload($question);

        // Upsert to Qdrant
        $success = $qdrant->upsertQuestion(
            $this->questionId,
            [
                'statement'   => $statementVector,
                'concept'     => $conceptVector,
                'explanation' => $explanationVector,
            ],
            $payload
        );

        if (!$success) {
            Log::error("[Xavier][IndexQuestion] Qdrant upsert failed for question #{$this->questionId}.");
            $this->fail(new \RuntimeException('Qdrant upsert failed'));
            return;
        }

        // Persist indexing metadata in MySQL
        $newVersion = ($vectorRecord ? $vectorRecord->index_version : 0) + 1;

        QuestionVector::updateOrCreate(
            ['question_id' => $this->questionId],
            [
                'qdrant_id'        => (string) $this->questionId,
                'embedding_hash'   => $combinedHash,
                'index_version'    => $newVersion,
                'pipeline_version' => config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser'),
                'indexed_at'       => now(),
            ]
        );

        Log::info("[Xavier][IndexQuestion] Question #{$this->questionId} indexed successfully (v{$newVersion}).");
        $aiService->removeCongestion('IndexQuestionVectorJob', $this->questionId);
    }

    /**
     * Build the Qdrant payload used for server-side filtering.
     */
    private function buildPayload(Question $question): array
    {
        $subjectId   = $question->subjects->first()?->id;
        $subjectName = $question->subjects->first()?->name;
        $topicId     = $question->topics->first()?->id;

        return [
            'question_id'   => $question->id,
            'subject_id'    => $subjectId,
            'subject_name'  => $subjectName,
            'topic_id'      => $topicId,
            'tipo_questao'  => $question->tipo_questao,
            'type'          => $question->type,
            'difficulty'    => $question->difficulty,
            'year'          => (int) ($question->year ?? 0),
            'organization'  => $question->organization,
            'institution'   => $question->institution,
            'is_active'     => (bool) $question->is_active,
            'review_status' => $question->review_status,
            'concepts'      => $question->concepts->pluck('id')->toArray(),
            // Popularity signal for ReRankService
            'answer_count'     => $question->userAnswers()->count(),
            'pipeline_version' => config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser'),
        ];
    }
}
