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

    public int $tries   = 20; // Allow many retries since we handle backoff manually
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
            Log::info("[Xavier][IndexQuestion] No active keys for embedding. Releasing question #{$this->questionId} to retry in 5 minutes.");
            $this->release(300); // 5 minutes backoff
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
        $currentPipeline = config('xavier.embeddings.pipeline_version', 'v6_intent_unification');
        
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
        
        $statementVector   = $aiService->generateEmbedding($statementText,   $userId, 'RETRIEVAL_DOCUMENT');
        Log::debug("[Xavier][IndexQuestion] #{$this->questionId} statement vector: " . ($statementVector ? 'OK' : 'FAILED'));
        
        $conceptVector     = $aiService->generateEmbedding($conceptText,     $userId, 'RETRIEVAL_DOCUMENT');
        Log::debug("[Xavier][IndexQuestion] #{$this->questionId} concept vector: " . ($conceptVector ? 'OK' : 'FAILED'));
        
        $explanationVector = $aiService->generateEmbedding($explanationText, $userId, 'RETRIEVAL_DOCUMENT');
        Log::debug("[Xavier][IndexQuestion] #{$this->questionId} explanation vector: " . ($explanationVector ? 'OK' : 'FAILED'));

        if (!$statementVector || !$conceptVector || !$explanationVector) {
            Log::error("[Xavier][IndexQuestion] FAILED for question #{$this->questionId}. Vectors status: S:".($statementVector?'OK':'FAIL')." C:".($conceptVector?'OK':'FAIL')." E:".($explanationVector?'OK':'FAIL'));
            $this->fail(new \RuntimeException('Embedding generation failed'));
            return;
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
                'pipeline_version' => config('xavier.embeddings.pipeline_version', 'v6_intent_unification'),
                'indexed_at'       => now(),
            ]
        );

        Log::info("[Xavier][IndexQuestion] Question #{$this->questionId} indexed successfully (v{$newVersion}).");
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
            'pipeline_version' => config('xavier.embeddings.pipeline_version', 'v6_intent_unification'),
        ];
    }
}
