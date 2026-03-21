<?php

namespace App\Jobs;

use App\Jobs\Concerns\HasConcurrencyLimit;
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
    use HasConcurrencyLimit;

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

        // Concurrency Semaphore: limit to max N simultaneous embedding API calls cluster-wide
        $maxConcurrent = config('xavier.concurrency.max_embeddings', 5);
        if (!$this->acquireSlot('embeddings', $maxConcurrent, retryIn: 20)) {
            return; // Released back to queue automatically
        }

        try {

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

        // ── Build the 5 structured embedding texts ───────────────────────────
        // Each text is formatted to match a specific named vector in Qdrant,
        // capturing a different semantic facet of the question.
        $statementText    = $textBuilder->buildForQuestion($question);
        $conceptText      = $textBuilder->buildConceptTextForQuestion($question);
        $explanationText  = $textBuilder->buildExplanationTextForQuestion($question);
        $alternativesText = $textBuilder->buildAlternativesTextForQuestion($question);
        $skillsText       = $textBuilder->buildSkillsTextForQuestion($question);

        // Compute hash of all 5 texts combined to detect content changes
        $combinedHash = hash('sha256', $statementText . $conceptText . $explanationText . $alternativesText . $skillsText);

        // Check if already indexed with same content AND same pipeline version
        $vectorRecord = QuestionVector::find($this->questionId);
        $currentPipeline = config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser');
        
        if ($vectorRecord && 
            !$vectorRecord->hasContentChanged($combinedHash) && 
            $vectorRecord->pipeline_version === $currentPipeline) {
            Log::info("[Xavier][IndexQuestion] Skipping #{$this->questionId} — content and pipeline unchanged.");
            return;
        }

        // Ensure Qdrant collection exists (with 5 named vectors + payload indexes)
        $qdrant->ensureQuestionsCollection();

        // ── Generate 5 embeddings (one per named vector) ─────────────────────
        // taskType='RETRIEVAL_DOCUMENT' tells Gemini to optimize vectors for
        // being found (i.e. document-side), matching RETRIEVAL_QUERY on search-side.
        $userId = null; // System job, no user attribution
        Log::info("[Xavier][IndexQuestion] Generating 5 vectors for #{$this->questionId}...");
        
        try {
            $texts = [
                $statementText,
                $conceptText,
                $explanationText,
                $alternativesText,
                $skillsText
            ];

            $vectors = $aiService->generateEmbeddingsBatch($texts, $userId, 'RETRIEVAL_DOCUMENT');

            if (!$vectors || count($vectors) !== 5) {
                throw new \RuntimeException('Batch embedding failed or returned incomplete results.');
            }

            [$statementVector, $conceptVector, $explanationVector, $alternativesVector, $skillsVector] = $vectors;

            Log::debug("[Xavier][IndexQuestion] #{$this->questionId} batch embeddings: OK");

        } catch (\App\Exceptions\AIServiceBusyException $e) {
            Log::info("[IndexQuestionVectorJob] AI Key pool busy for question #{$this->questionId}. Releasing for 30s backoff.");
            $aiService->registerCongestion('IndexQuestionVectorJob', $this->questionId);
            $this->release(30);
            return;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            
            $isQuota = str_contains(strtolower($msg), '429') || 
                       str_contains(strtolower($msg), 'quota') || 
                       str_contains(strtolower($msg), 'full failover failure');

            if ($isQuota) {
                Log::warning("[IndexQuestionVectorJob] Quota limit hit for #{$this->questionId}. Releasing for 2m. Error: {$msg}");
                $aiService->registerCongestion('IndexQuestionVectorJob', $this->questionId);
                $this->release(120);
                return;
            }

            Log::error("[IndexQuestionVectorJob] Permanent error indexing question #{$this->questionId}: " . $msg);
            $this->fail($e);
        }



        // Build Qdrant payload for filtering
        $payload = $this->buildPayload($question);

        // Upsert to Qdrant with all 5 named vectors + rich payload
        $success = $qdrant->upsertQuestion(
            $this->questionId,
            [
                'statement'    => $statementVector,
                'concept'      => $conceptVector,
                'explanation'  => $explanationVector,
                'alternatives' => $alternativesVector,
                'skills'       => $skillsVector,
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

        } finally {
            $this->releaseSlot('embeddings');
        }
    }

    /**
     * Build the rich Qdrant payload used for server-side filtering and ReRank scoring.
     *
     * Key design decisions:
     * - subject_ids / topic_ids are stored as arrays so multi-discipline questions
     *   benefit from Qdrant match.any filtering.
     * - subject_names / topic_names enable subject_name fallback detection
     *   when the Filters Collection is empty (e.g. right after a reset).
     * - answer_count is stored here so ReRankService needs zero MySQL queries.
     * - has_explanation and other boolean flags enable precise payload filters.
     */
    private function buildPayload(Question $question): array
    {
        // Primary (first) subject and topic — kept for backward compatibility
        $subjectId   = $question->subjects->first()?->id;
        $subjectName = $question->subjects->first()?->name;
        $topicId     = $question->topics->first()?->id;
        $topicName   = $question->topics->first()?->name;

        // Correct alternative letter (A-E)
        $correctAlternative = $question->alternatives->where('is_correct', true)->first();
        $correctLetter = null;
        if ($correctAlternative) {
            $sorted = $question->alternatives->sortBy('order')->values();
            $idx = $sorted->search(fn($a) => $a->id === $correctAlternative->id);
            $letters = ['A', 'B', 'C', 'D', 'E'];
            $correctLetter = $letters[$idx] ?? null;
        }

        // Plain-text word count (approximated from statement)
        $wordCount = $question->statement
            ? str_word_count(strip_tags($question->statement))
            : 0;

        return [
            // ── Identity ──────────────────────────────────────────────────
            'question_id'   => $question->id,
            'pipeline_version' => config('xavier.embeddings.pipeline_version', 'v7_lexical_analyser'),

            // ── Taxonomy (legacy single values + V2 arrays) ───────────────
            'subject_id'    => $subjectId,
            'subject_name'  => $subjectName,
            'subject_ids'   => $question->subjects->pluck('id')->toArray(),
            'subject_names' => $question->subjects->pluck('name')->toArray(),
            'topic_id'      => $topicId,
            'topic_name'    => $topicName,
            'topic_ids'     => $question->topics->pluck('id')->toArray(),
            'topic_names'   => $question->topics->pluck('name')->toArray(),

            // ── Classification ───────────────────────────────────────────
            'tipo_questao'      => $question->tipo_questao,
            'type'              => $question->type,
            'difficulty'        => $question->difficulty,
            'year'              => (int) ($question->year ?? 0),
            'organization'      => $question->organization,
            'institution'       => $question->institution,
            'is_active'         => (bool) $question->is_active,
            'review_status'     => $question->review_status,

            // ── Question Properties (V2 payload fields) ──────────────────
            'has_explanation'   => !empty($question->explanation),
            'has_image'         => str_contains($question->statement ?? '', '<img'),
            'word_count'        => $wordCount,
            'alternatives_count'=> $question->alternatives->count(),
            'correct_letter'    => $correctLetter,

            // ── Concepts (legacy) ─────────────────────────────────────────
            'concepts'          => $question->concepts->pluck('id')->toArray(),

            // ── Popularity signal (used by ReRankService, zero MySQL needed) ──
            'answer_count'      => $question->userAnswers()->count(),
        ];
    }
}
