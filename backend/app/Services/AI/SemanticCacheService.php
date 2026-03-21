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
        if (\App\Models\Configuration::get('xavier_search_cache_enabled', '1') !== '1') {
            return null;
        }

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
     * Busca um match similar usando Cosine Similarity calculada em PHP.
     *
     * Carrega os vetores do cache (limitado a 5000 mais recentes dentro do TTL)
     * e calcula o Dot Product de cada um contra o vetor da query.
     * Como os embeddings do Gemini são normalizados L2, Dot Product ≡ Cosine Similarity.
     *
     * @param  array  $embeddingVector  Vetor da query do usuário (3072 dims)
     * @param  float  $threshold        Score mínimo para considerar um match (padrão: 0.94)
     * @return array|null  Filtros cacheados se houve match, null caso contrário
     */
    public function findSimilarMatch(array $embeddingVector, float $threshold = 0.94): ?array
    {
        if (\App\Models\Configuration::get('xavier_search_cache_enabled', '1') !== '1') {
            return null;
        }

        if (empty($embeddingVector)) {
            return null;
        }

        try {
            /*
             * TTL de 48 horas para evitar resultados stale.
             * Quando novas questões são indexadas, o cache não é invalidado explicitamente,
             * então este TTL garante que buscas antigas sejam reprocessadas periodicamente
             * para capturar novos conteúdos relevantes.
             */
            $cacheTtlHours = 48;

            $caches = AiSearchCache::select('id', 'prompt_text', 'filters_result', 'embedding')
                ->where('last_used_at', '>=', now()->subHours($cacheTtlHours))
                ->orderBy('last_used_at', 'desc')
                ->limit(5000)
                ->get();

            $bestMatch = null;
            $highestScore = -1.0;

            foreach ($caches as $cache) {
                $dbVector = $cache->embedding;

                // Pula vetores com dimensão incompatível (ex: migração de modelo)
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
                // Atualiza last_used_at para manter a entrada viva enquanto for útil
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
        if (\App\Models\Configuration::get('xavier_search_cache_enabled', '1') !== '1') {
            return;
        }

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
     * Como o Gemini (gemini-embedding-001) já retorna arrays normalizados L2 (magnitude = 1),
     * O Dot Product é IDÊNTICO matemático à Similaridade de Cosseno.
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
