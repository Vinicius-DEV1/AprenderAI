<?php

namespace App\Services\AI;

use App\Models\AiSearchCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SemanticCacheService
{
    /**
     * Busca um match exato baseado no Hash do Prompt.
     * Retorna instantâneo.
     */
    public function findExactMatch(string $prompt): ?array
    {
        $hash = md5(trim(strtolower($prompt)));
        $cache = AiSearchCache::where('prompt_hash', $hash)->first();

        if ($cache) {
            $cache->update(['last_used_at' => now()]);
            Log::info('[SemanticCache] L1 Hit: Match Exato via Hash.', ['prompt' => $prompt]);
            return $cache->filters_result;
        }

        return null;
    }

    /**
     * Busca um match similar usando Cosine Similarity no MySQL 8.0+.
     * Assume que os items do vetor tem tamanho fixo (~768 dimensões)
     * e os compara extraindo-os através das JSON functions nativas.
     */
    public function findSimilarMatch(array $embeddingVector, float $threshold = 0.94): ?array
    {
        if (empty($embeddingVector)) {
            return null;
        }

        try {
            // Converte o array PHP do embedding em formato JSON string p/ a Query
            $vectorJson = json_encode($embeddingVector);

            /*
             * Nota: Para MySQL >= 8.0 que nao possuem suporte vetorial puro (ex: pgvector),
             * não é recomendado processar milhões de registros desta forma,
             * mas para um cache de até ~50.000 buscas frequentes é perfeitamente performático (<50ms).
             * A query aqui simula 'Cosine Similarity' através de um cálculo matemático bruto com variáveis json
             * Como os embeddings retornados pelas APIs costumam vir normalizados (magnitude = 1),
             * O Cosine Similarity é equivalente ao `Dot Product` (Produto Escalar).
             */

            // Limitamos a busca aos items validados nos ultimos N meses se for muito grande
            $query = "
                SELECT id, prompt_text, filters_result,
                (
                    SELECT SUM(
                        JSON_EXTRACT('{$vectorJson}', CONCAT('$[', seq, ']')) *
                        JSON_EXTRACT(ai_search_cache.embedding, CONCAT('$[', seq, ']'))
                    )
                    FROM (
                        SELECT 0 as seq UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 -- ... E assim por diante ... 
                        -- Para evitar query super-longa em raw MySQL,
                        -- como o MySQL não tem LATERAL join nativo de unnesting array nativo prático p/ 700+ posições,
                        -- usamos uma Stored Procedure auxiliar criada via Migration ou um fallback em memória 
                        -- se a base não for otimizada.
                    ) mock_table_for_sequence
                ) as similarity_score
                FROM ai_search_cache
                -- Fallback implementavel em PHP abaixo
            ";

            /*  
             * IMPLEMENTAÇÃO PHP (O(N) memory bound)
             * Em vez da query gigante e super pesada p/ o Banco MySQL (ineficiente para 768 itens de DB puro), 
             * já que estamos num domínio de 'Cache', é muito mais rápido e eficiente:
             * 1. Trazer todos os vetores da tabela local (limitado a últimas buscas)
             * 2. Calcular o Dot Product direto no Kernel de C do PHP.
             */

            // TODO SCALE: Se passar de certa quantidade, varremos apenas os Top 1000 +recentes.
            $caches = AiSearchCache::select('id', 'prompt_text', 'filters_result', 'embedding')
                ->orderBy('last_used_at', 'desc')
                ->limit(5000)
                ->get();

            $bestMatch = null;
            $highestScore = -1.0;

            foreach ($caches as $cache) {
                $dbVector = $cache->embedding;
                if (!is_array($dbVector) || count($dbVector) !== count($embeddingVector)) {
                    continue;
                }

                $score = $this->calculateDotProduct($embeddingVector, $dbVector);

                if ($score > $highestScore) {
                    $highestScore = $score;
                    $bestMatch = $cache;
                }
            }

            if ($bestMatch && $highestScore >= $threshold) {
                $bestMatch->update(['last_used_at' => now()]);
                Log::info('[SemanticCache] L2 Hit: Match por Similaridade Matemática.', [
                    'score' => round($highestScore, 4),
                    'matched_prompt' => $bestMatch->prompt_text
                ]);
                return $bestMatch->filters_result;
            }

            Log::info('[SemanticCache] Miss: Nenhuma similaridade atingiu o limite.', ['highest_score' => $highestScore]);
            return null;

        } catch (\Exception $e) {
            Log::error('[SemanticCache] Erro durante o calculo de similaridade.', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Armazena um resultado válido no Cache.
     *
     * @param  string   $prompt
     * @param  array    $embeddingVector
     * @param  array    $filtersResult
     * @param  string[] $conceptIds   Optional: concept IDs detected in this search (for learning loop)
     */
    public function storeInCache(string $prompt, array $embeddingVector, array $filtersResult, array $conceptIds = []): void
    {
        try {
            $hash = md5(trim(strtolower($prompt)));

            AiSearchCache::updateOrCreate(
                ['prompt_hash' => $hash],
                [
                    'prompt_text'    => $prompt,
                    'embedding'      => $embeddingVector,
                    'filters_result' => $filtersResult,
                    'concept_ids'    => !empty($conceptIds) ? $conceptIds : null,
                    'last_used_at'   => now(),
                ]
            );

            Log::info('[SemanticCache] Novo resultado salvo em cache.', [
                'prompt'       => $prompt,
                'concept_ids'  => $conceptIds,
            ]);
        } catch (\Exception $e) {
            Log::error('[SemanticCache] Falha ao salvar no cache.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Calcula o Dot Product de dois arrays.
     * Como o Gemini (text-embedding-004) já retorna arrays normalizados L2 (tamanho do vetor 1),
     * O Dot Product é IDENTICO matemático à Similaridade de Cosseno.
     */
    private function calculateDotProduct(array $vecA, array $vecB): float
    {
        $sum = 0.0;
        $count = count($vecA);
        for ($i = 0; $i < $count; $i++) {
            $sum += ((float) $vecA[$i]) * ((float) $vecB[$i]);
        }
        return $sum;
    }
}
