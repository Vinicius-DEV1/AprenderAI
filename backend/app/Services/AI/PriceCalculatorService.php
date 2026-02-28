<?php

namespace App\Services\AI;

use App\Models\ApiPricing;
use Illuminate\Support\Facades\Cache;

class PriceCalculatorService
{
    /**
     * Tabela de preços cacheados do DB.
     */

    /**
     * Cache key for DB pricing data.
     */
    protected const CACHE_KEY = 'api_pricing_table';
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Calcula o custo estimado da requisição em USD.
     *
     * @param string $provider Provedor da API (ex: openai, gemini)
     * @param string $model Nome do modelo (ex: gemini-1.5-flash)
     * @param int $inputTokens Número de tokens de entrada (Prompt)
     * @param int $outputTokens Número de tokens de saída (Completion)
     * @return float Custo total da requisição (precisão de 6 casas decimais)
     */
    public function calculateCost(string $provider, string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = $this->getPricing($provider, $model);

        // Price is per 1,000,000 tokens
        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Returns the pricing array for a provider and model.
     * Reads from DB (cached) with a safe 0.00 fallback if not registered.
     */
    protected function getPricing(string $provider, string $model): array
    {
        $dbTable = $this->loadDbPricing();

        $searchKey = $provider . '::' . strtolower($model);

        if (isset($dbTable[$searchKey])) {
            return $dbTable[$searchKey];
        }

        // Try exact model match without provider prefix if not found combined
        $modelLower = strtolower($model);
        foreach ($dbTable as $key => $prices) {
            if (str_ends_with($key, '::' . $modelLower)) {
                return $prices;
            }
        }

        // Safe Fallback to 0 if no pricing exists. Admin must configure it.
        return ['input' => 0.00, 'output' => 0.00];
    }

    /**
     * Loads the pricing table from the database, cached for CACHE_TTL seconds.
     * Returns an associative array keyed by provider::model_key.
     */
    protected function loadDbPricing(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $rows = ApiPricing::all(['api_name', 'model_key', 'input_price_per_1m', 'output_price_per_1m']);
            $table = [];
            foreach ($rows as $row) {
                // Normalize provider name (e.g. "Google Gemini" -> "gemini")
                $providerKey = strtolower(explode(' ', $row->api_name)[1] ?? $row->api_name);
                if ($row->api_name === 'OpenAI')
                    $providerKey = 'openai';

                $key = $providerKey . '::' . strtolower($row->model_key);
                $table[$key] = [
                    'input' => (float) $row->input_price_per_1m,
                    'output' => (float) $row->output_price_per_1m,
                ];
            }
            return $table;
        });
    }

    /**
     * Invalidates the pricing cache. Should be called after any price update.
     */
    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }


}
