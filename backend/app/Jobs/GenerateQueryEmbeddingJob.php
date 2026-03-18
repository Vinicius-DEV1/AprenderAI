<?php

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\AiSearchRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * GenerateQueryEmbeddingJob
 *
 * Gera UM dos 3 embeddings format-aligned de uma busca do usuário e armazena em Redis.
 *
 * PARALELISMO CONTROLADO:
 *   - Para cada busca, o QuestionController despacha 3 instâncias independentes deste Job
 *     (uma por slot: 'statement', 'concept', 'explanation').
 *   - Cada instância é processada por um worker diferente do pool 'search_embeddings',
 *     garantindo execução SIMULTÂNEA sem bloqueio.
 *   - O slot indica qual named vector do Qdrant este embedding destina-se a buscar.
 *
 * MECANISMO DE COORDENAÇÃO (anti-race-condition):
 *   - Um contador atômico Redis (INCR) rastreia quantos dos 3 slots foram concluídos.
 *   - Apenas o último job a terminar (o que incrementa o contador para 3) dispara o
 *     RunVectorSearchJob — evitando despachos duplicados sem usar locks.
 *
 * CAPABILITY ISOLADA:
 *   - Usa CAPABILITY_QUERY_EMBEDDING (exclusiva de busca) para não competir com
 *     as chaves de indexação batch (CAPABILITY_EMBEDDING) e suas filas separadas.
 *
 * Fila: 'search_embeddings' (adicionada como prioridade máxima nos workers 'default').
 */
class GenerateQueryEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de tentativas antes de considerar o job falho.
     * Retry automático cobre erros transitórios de rede ou rate limit 429.
     */
    public int $tries = 2;

    /**
     * Timeout em segundos por tentativa.
     * Embedding é uma chamada HTTP simples — 30s é mais que suficiente.
     */
    public int $timeout = 30;

    /**
     * @param int    $searchRequestId  ID do AiSearchRequest — usado como namespace no Redis
     * @param string $slot             Qual named vector gerar: 'statement' | 'concept' | 'explanation'
     * @param string $text             Texto normalizado para este slot
     * @param int    $userId           ID do usuário para telemetria
     * @param array  $searchContext    Contexto completo da busca (sql_filters, intent_filters, etc.)
     *                                 Passado via parâmetro para evitar re-query no RunVectorSearchJob.
     */
    public function __construct(
        protected int    $searchRequestId,
        protected string $slot,
        protected string $text,
        protected int    $userId,
        protected array  $searchContext = []
    ) {
        // Fila dedicada à busca — adicionada com prioridade máxima no worker 'default'.
        // Não usa a fila 'embeddings' (reservada para indexação batch) para evitar
        // contenção de chaves (CAPABILITY_EMBEDDING vs CAPABILITY_QUERY_EMBEDDING).
        $this->onQueue(config('xavier.search_embeddings.queue', 'search_embeddings'));
    }

    public function handle(): void
    {
        // ── Chave de namespace Redis para esta busca específica ──────────────────
        // Padrão: xavier:qembed:{searchRequestId}:{slot}
        // Todo dado desta busca expira em 5 minutos (TTL) para não vazar memória.
        $ttl          = config('xavier.search_embeddings.ttl', 300);
        $embedKey     = "xavier:qembed:{$this->searchRequestId}:{$this->slot}";
        $counterKey   = "xavier:qembed_done:{$this->searchRequestId}";
        $contextKey   = "xavier:qembed_ctx:{$this->searchRequestId}";

        // ── Verificação de segurança: AiSearchRequest ainda existe e está gerando ──
        $searchRequest = AiSearchRequest::find($this->searchRequestId);
        if (!$searchRequest || $searchRequest->status === 'completed') {
            Log::info("[Xavier][QueryEmbed] Slot '{$this->slot}' abortado: busca #{$this->searchRequestId} já foi concluída ou não existe.");
            return;
        }

        // ── Obtém chave API via CAPABILITY_QUERY_EMBEDDING (isolada de indexação) ──
        // O motor de round-robin + blacklist do ApiKey garante distribuição de carga
        // e failover automático entre chaves disponíveis.
        $apiKeyModel = ApiKey::getKeyForCapability(ApiKey::CAPABILITY_QUERY_EMBEDDING);

        if (!$apiKeyModel) {
            // Sem chave disponível: marca slot como falho sem quebrar o contador.
            // O RunVectorSearchJob verificará vetores nulos e usará fallback síncrono.
            Log::warning("[Xavier][QueryEmbed] Nenhuma chave disponível para CAPABILITY_QUERY_EMBEDDING (slot: {$this->slot}).");
            Cache::put($embedKey, null, $ttl);
            $this->triggerIfLast($counterKey, $contextKey, $ttl);
            return;
        }

        // ── Chamada à API Gemini Embedding ───────────────────────────────────────
        $apiKey = $apiKeyModel->decrypted_key;
        $url    = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

        // taskType RETRIEVAL_QUERY: otimiza o vetor para encontrar documentos relevantes
        // (inversamente ao RETRIEVAL_DOCUMENT usado na indexação).
        $payload = [
            'content'  => ['parts' => [['text' => $this->text]]],
            'taskType' => 'RETRIEVAL_QUERY',
        ];

        Log::info("[Xavier][QueryEmbed] Gerando embedding slot '{$this->slot}' para busca #{$this->searchRequestId}.");

        $response = Http::timeout(30)->post($url, $payload);

        if ($response->failed()) {
            $statusCode = $response->status();
            Log::error("[Xavier][QueryEmbed] Falha na API Gemini (slot: {$this->slot}, status: {$statusCode}).");

            // Atualiza status da chave se necessário (blacklist temporária)
            if ($statusCode === 429) {
                $apiKeyModel->update(['status' => 'quota_exceeded']);
            } elseif (in_array($statusCode, [401, 403])) {
                $apiKeyModel->update(['status' => 'offline']);
            }

            // Armazena null — RunVectorSearchJob usará o vetor genérico como fallback
            Cache::put($embedKey, null, $ttl);
            $this->triggerIfLast($counterKey, $contextKey, $ttl);
            return;
        }

        $vector = $response->json('embedding.values');

        if (empty($vector)) {
            Log::error("[Xavier][QueryEmbed] Vetor vazio na resposta da API (slot: {$this->slot}).");
            Cache::put($embedKey, null, $ttl);
            $this->triggerIfLast($counterKey, $contextKey, $ttl);
            return;
        }

        // ── Sucesso: persiste o vetor no Redis ───────────────────────────────────
        Cache::put($embedKey, $vector, $ttl);
        $apiKeyModel->incrementUsage();

        Log::info("[Xavier][QueryEmbed] Slot '{$this->slot}' concluído para busca #{$this->searchRequestId} (" . count($vector) . " dims).");

        // ── Armazena o contexto da busca (uma única vez, não importa qual slot chegue primeiro) ──
        // O SearchContext contém: expanded_concept_ids, sql_filters, intent_filters,
        // query_vector (genérico), normalized_query — tudo necessário para RunVectorSearchJob.
        if (!empty($this->searchContext)) {
            Cache::add($contextKey, json_encode($this->searchContext), $ttl);
        }

        // ── Verifica atomicamente se foi o último dos 3 slots a terminar ─────────
        $this->triggerIfLast($counterKey, $contextKey, $ttl);
    }

    /**
     * Incrementa atomicamente o contador de slots concluídos.
     * Se todos os 3 foram processados, dispara o RunVectorSearchJob.
     *
     * ANTI-RACE-CONDITION:
     *   - Redis INCR é atômico — nunca dois jobs incrementam para 3 simultaneamente.
     *   - Somente o job que recebe exatamente 3 como retorno do INCR dispara o próximo job.
     *   - Essa garantia elimina o risco de despacho duplo do RunVectorSearchJob.
     */
    private function triggerIfLast(string $counterKey, string $contextKey, int $ttl): void
    {
        // Redis::incr retorna o novo valor do contador de forma atômica
        $done = Redis::incr($counterKey);

        // Define TTL no contador apenas na primeira vez (primeiro slot a incrementar)
        if ($done === 1) {
            Redis::expire($counterKey, $ttl);
        }

        // Exatamente quando os 3 slots completam (independente da ordem), dispara a busca
        if ($done >= 3) {
            Log::info("[Xavier][QueryEmbed] Todos os 3 slots concluídos para busca #{$this->searchRequestId}. Disparando RunVectorSearchJob.");
            RunVectorSearchJob::dispatch($this->searchRequestId, $this->userId);
        }
    }

    /**
     * Tratamento de falha definitiva (após esgotamento das tentativas).
     * Garante que mesmo falhando, o contador seja incrementado para não travar a busca.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("[Xavier][QueryEmbed] Job falhou definitivamente (slot: {$this->slot}, busca: #{$this->searchRequestId}). Erro: " . $exception->getMessage());

        $counterKey = "xavier:qembed_done:{$this->searchRequestId}";
        $contextKey = "xavier:qembed_ctx:{$this->searchRequestId}";
        $ttl        = config('xavier.search_embeddings.ttl', 300);

        // Armazena null para o slot falho (RunVectorSearchJob usará fallback síncrono)
        Cache::put("xavier:qembed:{$this->searchRequestId}:{$this->slot}", null, $ttl);

        // Incrementa o contador mesmo em falha — evita que a busca trave esperando
        // um slot que nunca virá
        $this->triggerIfLast($counterKey, $contextKey, $ttl);
    }
}
