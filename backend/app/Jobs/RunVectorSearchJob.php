<?php

namespace App\Jobs;

use App\Models\AiSearchRequest;
use App\Models\SearchInteractionLog;
use App\Models\User;
use App\Notifications\SemanticSearchErrorNotification;
use App\Services\AI\HybridSearchService;
use App\Services\AI\ReRankService;
use App\Services\AI\SemanticCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


/**
 * RunVectorSearchJob
 *
 * Executes the Qdrant multi-vector search + ReRank after all 5 query
 * embeddings are ready in Redis.
 *
 * WHEN IS IT DISPATCHED?
 *   - By QuestionController::aiSearch() after generating all 5 format-aligned
 *     vectors synchronously via a batch API call and storing them in Redis.
 *   - The dispatch happens directly (not via GenerateQueryEmbeddingJob) in the
 *     v8 pipeline. The counter key is set to 5 atomically before dispatch.
 *
 * RESPONSIBILITIES:
 *   1. Reads the 5 format-aligned vectors from Redis:
 *        statement, concept, explanation, alternatives, skills
 *   2. Falls back to the generic query_vector for any null slot.
 *   3. Runs HybridSearch (Qdrant multi-vector + optional SQL fallback).
 *   4. Runs ReRank (vector + popularity + quality + recency + user profile).
 *   5. Persists the ranked question IDs to AiSearchRequest (status='completed').
 *   6. Writes SearchInteractionLog rows for user-click tracking.
 *   7. Stores result in the L2 Semantic Cache for future similar queries.
 *   8. Cleans up all Redis keys created per-search to free memory early.
 *
 * Queue: 'search_embeddings' — processed at highest priority by worker-default.
 *
 * @see QuestionController::aiSearch() for the upstream pipeline
 * @see HasConcurrencyLimit — not used here; search is always allowed to run
 */
class RunVectorSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1; // Não faz retry: se falhar, o resultado é simplesmente null
    public int $timeout = 60;

    /**
     * @param int $searchRequestId  ID do AiSearchRequest a ser completado
     * @param int $userId           ID do usuário para logging de interações
     */
    public function __construct(
        protected int $searchRequestId,
        protected int $userId
    ) {
        $this->onQueue(config('xavier.search_embeddings.queue', 'search_embeddings'));
    }

    public function handle(
        HybridSearchService $hybridSearch,
        ReRankService       $reranker,
        SemanticCacheService $cacheService
    ): void {
        // ── Recupera contexto de busca do Redis ───────────────────────────────
        // O contexto foi armazenado pelo GenerateQueryEmbeddingJob e contém tudo
        // necessário para executar a busca sem re-fazer queries ao banco de dados.
        $contextKey  = "xavier:qembed_ctx:{$this->searchRequestId}";
        $rawContext  = Cache::get($contextKey);

        if (!$rawContext) {
            Log::error("[Xavier][RunVectorSearch] Contexto ausente no Redis para busca #{$this->searchRequestId}.");
            $this->markFailed("Search context expired or not found in Redis.");
            return;
        }

        $ctx = json_decode($rawContext, true);

        // ── Read all 5 format-aligned query vectors from Redis ────────────────
        // Each slot was stored by QuestionController::aiSearch() via a batch
        // embedding call. If a slot is missing or null, we fall back to the
        // generic query_vector (generated synchronously in Step 3) to ensure
        // the search always proceeds.
        //
        // Slot mapping (mirrors IndexQuestionVectorJob named vectors in Qdrant):
        //   statement    → matches the question statement named vector
        //   concept      → matches the concept/subject named vector
        //   explanation  → matches the explanation named vector
        //   alternatives → matches the alternatives named vector (added in v8)
        //   skills       → matches the skills named vector (added in v8)
        $queryVector = $ctx['query_vector']; // Generic vector — synchronous Step 3 fallback

        $statementVector    = Cache::get("xavier:qembed:{$this->searchRequestId}:statement")    ?? $queryVector;
        $conceptVector      = Cache::get("xavier:qembed:{$this->searchRequestId}:concept")      ?? $queryVector;
        $explanationVector  = Cache::get("xavier:qembed:{$this->searchRequestId}:explanation")  ?? $queryVector;
        $alternativesVector = Cache::get("xavier:qembed:{$this->searchRequestId}:alternatives") ?? $queryVector;
        $skillsVector       = Cache::get("xavier:qembed:{$this->searchRequestId}:skills")       ?? $queryVector;

        Log::info("[Xavier][RunVectorSearch] Executing vector search for #{$this->searchRequestId}.", [
            'statement_cached'    => Cache::has("xavier:qembed:{$this->searchRequestId}:statement"),
            'concept_cached'      => Cache::has("xavier:qembed:{$this->searchRequestId}:concept"),
            'explanation_cached'  => Cache::has("xavier:qembed:{$this->searchRequestId}:explanation"),
            'alternatives_cached' => Cache::has("xavier:qembed:{$this->searchRequestId}:alternatives"),
            'skills_cached'       => Cache::has("xavier:qembed:{$this->searchRequestId}:skills"),
        ]);

        // Build the 5-vector map expected by HybridSearchService and QdrantService.
        // QdrantService::searchQuestions() iterates all provided named vectors for
        // multi-vector scoring, so providing all 5 maximises retrieval quality.
        $queryVectors = [
            'statement'    => $statementVector,
            'concept'      => $conceptVector,
            'explanation'  => $explanationVector,
            'alternatives' => $alternativesVector,
            'skills'       => $skillsVector,
        ];

        // ── Step 7: Hybrid Search ───────────────────────────────────────────
        $expandedConceptIds = $ctx['expanded_concept_ids'] ?? [];
        
        // V2 Semantic Expansion support
        $expandedSubjectIds = $ctx['intent_filters']['expanded_subject_id'] ?? [];
        $expandedTopicIds   = $ctx['intent_filters']['expanded_topic_id'] ?? [];

        $sqlFilters         = $ctx['sql_filters']          ?? ['keyword' => $ctx['prompt']];
        $candidateLimit     = $ctx['candidate_limit']      ?? 50;
        $excludedConceptIds = $ctx['excluded_concept_ids'] ?? [];

        $candidates = $hybridSearch->search(
            $queryVectors, 
            $expandedConceptIds, 
            $sqlFilters, 
            $candidateLimit, 
            $excludedConceptIds,
            $expandedSubjectIds,
            $expandedTopicIds
        );
        Log::info("[Xavier][RunVectorSearch] Hybrid search: " . count($candidates) . " candidatos.");

        // ── Step 8: ReRank ──────────────────────────────────────────────────
        $finalLimit    = $ctx['final_limit']    ?? 100;
        $intentFilters = $ctx['intent_filters'] ?? [];
        $searchPath    = $ctx['search_path']    ?? 'vector_only';

        $rankedItems = $reranker->rerank($candidates, $finalLimit, $intentFilters, $this->userId);
        $questionIds = array_column($rankedItems, 'question_id');
        Log::info("[Xavier][RunVectorSearch] ReRank finalizado: " . count($rankedItems) . " questões.");

        // ── Step 9: Persiste resultado no AiSearchRequest (marking 'completed') ──
        $scoreDetailsMap = array_column($rankedItems, null, 'question_id');

        AiSearchRequest::where('id', $this->searchRequestId)->update([
            'status'  => 'completed',
            'filters' => json_encode([
                'vector_search' => true,
                'question_ids'  => $questionIds,
                'search_mode'   => 'vector',
                'search_path'   => $searchPath,
                'score_details' => $scoreDetailsMap,
                'concepts'      => $ctx['detected_concepts'] ?? [],
                'is_restricted' => $ctx['is_restricted']    ?? false,
                'restricted'    => $ctx['restricted_terms'] ?? [],
                'difficulty'    => $sqlFilters['difficulty'] ?? null,
                'year'          => $sqlFilters['year'] ?? null,
                'year_operator' => $sqlFilters['year_operator'] ?? null,
                'organization'  => $sqlFilters['organization'] ?? null,
                'institution'   => $sqlFilters['institution'] ?? null,
            ]),
        ]);

        // ── Registra SearchInteractionLog para rastreamento de posição ────────
        // Bulk insert em vez de N INSERTs individuais — evita gargalo de latência MySQL
        // quando a busca retorna 80-100 questões rankeadas.
        if (!empty($rankedItems)) {
            $now = now();
            $logs = array_map(function ($item, $idx) use ($expandedConceptIds, $searchPath, $now) {
                return [
                    'ai_search_id'         => $this->searchRequestId,
                    'user_id'              => $this->userId,
                    'question_id'          => $item['question_id'],
                    'rank_position'        => $idx + 1,
                    'was_clicked'          => false,
                    'expanded_concept_ids' => json_encode($expandedConceptIds),
                    'search_path'          => $searchPath,
                    'created_at'           => $now,
                ];
            }, $rankedItems, array_keys($rankedItems));

            \Illuminate\Support\Facades\DB::table('search_interaction_logs')->insert($logs);
        }


        // ── Armazena no L2 Semantic Cache para buscas similares futuras ───────
        $originalPrompt = $ctx['prompt'] ?? ''; // <--- USAR PROMPT COMPLETO AQUI
        if ($originalPrompt && $queryVector) {
            $cacheService->storeInCache(
                $originalPrompt,
                $queryVector,
                [
                    'vector_search' => true,
                    'question_ids'  => $questionIds,
                    'score_details' => $scoreDetailsMap,
                ],
                $expandedConceptIds
            );
        }

        // ── Step 10: Clean up all Redis keys created for this search ──────────
        // Although all keys have a TTL (5 min), we delete them immediately after
        // the search completes to free Redis memory as early as possible.
        // All 5 embedding keys + the context and counter keys are removed.
        try {
            \Illuminate\Support\Facades\Redis::del([
                "xavier:qembed_ctx:{$this->searchRequestId}",
                "xavier:qembed_done:{$this->searchRequestId}",
                "xavier:qembed:{$this->searchRequestId}:statement",
                "xavier:qembed:{$this->searchRequestId}:concept",
                "xavier:qembed:{$this->searchRequestId}:explanation",
                "xavier:qembed:{$this->searchRequestId}:alternatives",
                "xavier:qembed:{$this->searchRequestId}:skills",
            ]);
        } catch (\Exception $e) {
            Log::warning("[Xavier][RunVectorSearch] Failed to clean Redis keys: " . $e->getMessage());
        }

        Log::info("[Xavier][RunVectorSearch] Busca #{$this->searchRequestId} concluída com " . count($questionIds) . " questões.");
    }

    /**
     * Marca a busca como 'failed' no banco e loga o erro.
     */
    private function markFailed(string $reason): void
    {
        AiSearchRequest::where('id', $this->searchRequestId)->update([
            'status' => 'failed',
            'error'  => $reason,
        ]);

        // Notify admins
        try {
            $searchRequest = AiSearchRequest::find($this->searchRequestId);
            $admins = User::where('role', 'admin')->get();
            $userName = $searchRequest->user ? $searchRequest->user->name : 'System/Guest';
            
            \Illuminate\Support\Facades\Notification::send($admins, new SemanticSearchErrorNotification(
                $searchRequest->prompt ?? 'Unknown',
                $reason,
                $userName
            ));
        } catch (\Exception $e) {
            Log::warning("[Xavier][RunVectorSearch] Failed to notify admins of search error: " . $e->getMessage());
        }
    }

    /**
     * Tratamento de exceção inesperada no job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[Xavier][RunVectorSearch] Job falhou para busca #{$this->searchRequestId}: " . $exception->getMessage());
        $this->markFailed($exception->getMessage());
    }
}
