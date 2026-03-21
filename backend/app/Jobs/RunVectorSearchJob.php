<?php

namespace App\Jobs;

use App\Models\AiSearchRequest;
use App\Models\SearchInteractionLog;
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
 * Executa o pipeline Qdrant + ReRank após os 3 embeddings de query estarem prontos.
 *
 * QUANDO É DISPARADO:
 *   - Pelo GenerateQueryEmbeddingJob, automaticamente quando TODOS os 3 slots
 *     (statement, concept, explanation) forem concluídos (sucesso ou falha com fallback).
 *   - O mecanismo de atomicidade via Redis INCR garante que este job é disparado
 *     exatamente UMA VEZ por busca, independente da ordem de conclusão dos slots.
 *
 * RESPONSABILIDADES:
 *   1. Lê os 3 vetores do Redis (gerados pelo GenerateQueryEmbeddingJob).
 *   2. Para qualquer slot null (falhou), usa o vetor genérico armazenado no contexto.
 *   3. Executa HybridSearch (Qdrant + SQL fallback) + ReRank.
 *   4. Persiste resultado em AiSearchRequest (status='completed').
 *   5. Registra logs de interação (SearchInteractionLog) para aprendizado.
 *   6. Armazena resultado no L2 Semantic Cache para reuso futuro.
 *
 * Fila: 'search_embeddings' (mesma fila dos embedding jobs — processada com prioridade
 * pelos 40 workers 'default' que incluem search_embeddings na lista de filas).
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

        // ── Lê os 3 vetores format-aligned do Redis ───────────────────────────
        // Cada slot foi armazenado pelo GenerateQueryEmbeddingJob.
        // Se um slot voltar null (falhou), usa o vetor genérico como fallback.
        $queryVector = $ctx['query_vector']; // Vetor genérico — gerado sincronamente na Step 3

        $statementVector   = Cache::get("xavier:qembed:{$this->searchRequestId}:statement")   ?? $queryVector;
        $conceptVectorQ    = Cache::get("xavier:qembed:{$this->searchRequestId}:concept")     ?? $queryVector;
        $explanationVector = Cache::get("xavier:qembed:{$this->searchRequestId}:explanation") ?? $queryVector;

        Log::info("[Xavier][RunVectorSearch] Executando busca vetorial para #{$this->searchRequestId}.", [
            'statement_from_job'   => Cache::has("xavier:qembed:{$this->searchRequestId}:statement"),
            'concept_from_job'     => Cache::has("xavier:qembed:{$this->searchRequestId}:concept"),
            'explanation_from_job' => Cache::has("xavier:qembed:{$this->searchRequestId}:explanation"),
        ]);

        $queryVectors = [
            'statement'   => $statementVector,
            'concept'     => $conceptVectorQ,
            'explanation' => $explanationVector,
        ];

        // ── Step 7: Hybrid Search ───────────────────────────────────────────
        $expandedConceptIds = $ctx['expanded_concept_ids'] ?? [];
        $sqlFilters         = $ctx['sql_filters']          ?? ['keyword' => $ctx['prompt']];
        $candidateLimit     = $ctx['candidate_limit']      ?? 50;
        $excludedConceptIds = $ctx['excluded_concept_ids'] ?? [];

        $candidates = $hybridSearch->search($queryVectors, $expandedConceptIds, $sqlFilters, $candidateLimit, $excludedConceptIds);
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
                    'updated_at'           => $now,
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

        // ── Step 10: Cleanup Redis ─────────────────────────────────────────
        // Embora as chaves tenham TTL (5 min), limpamos agora para liberar RAM.
        try {
            \Illuminate\Support\Facades\Redis::del([
                "xavier:qembed_ctx:{$this->searchRequestId}",
                "xavier:qembed:{$this->searchRequestId}:statement",
                "xavier:qembed:{$this->searchRequestId}:concept",
                "xavier:qembed:{$this->searchRequestId}:explanation",
            ]);
        } catch (\Exception $e) {
            Log::warning("[Xavier][RunVectorSearch] Falha ao limpar chaves Redis: " . $e->getMessage());
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
