<?php

namespace App\Services\AI;

use App\Models\ApiPricing;
use Illuminate\Support\Facades\Cache;

class PriceCalculatorService
{
    /**
     * Fallback hardcoded pricing (USD per 1,000,000 tokens).
     * Used when a model is not found in the api_pricing DB table.
     */
    protected const FALLBACK_PRICING = [
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.150, 'output' => 0.60],
        'grok-2' => ['input' => 2.00, 'output' => 10.00],
        'default' => ['input' => 0.00, 'output' => 0.00],
    ];

    /**
     * Cache key for DB pricing data.
     */
    protected const CACHE_KEY = 'api_pricing_table';
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Calcula o custo estimado da requisição em USD.
     *
     * @param string $model Nome do modelo (ex: gemini-1.5-flash)
     * @param int $inputTokens Número de tokens de entrada (Prompt)
     * @param int $outputTokens Número de tokens de saída (Completion)
     * @return float Custo total da requisição (precisão de 6 casas decimais)
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $modelKey = $this->resolveModelKey($model);
        $pricing = $this->getPricing($modelKey);

        // Price is per 1,000,000 tokens
        $inputCost = ($inputTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($outputTokens / 1_000_000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Returns the pricing array for a resolved model key.
     * Reads from DB (cached) with fallback to hardcoded constants.
     */
    protected function getPricing(string $modelKey): array
    {
        $dbTable = $this->loadDbPricing();

        if (isset($dbTable[$modelKey])) {
            return $dbTable[$modelKey];
        }

        // Fallback to hardcoded constants
        return self::FALLBACK_PRICING[$modelKey] ?? self::FALLBACK_PRICING['default'];
    }

    /**
     * Loads the pricing table from the database, cached for CACHE_TTL seconds.
     * Returns an associative array keyed by model_key.
     */
    protected function loadDbPricing(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $rows = ApiPricing::all(['model_key', 'input_price_per_1m', 'output_price_per_1m']);
            $table = [];
            foreach ($rows as $row) {
                $table[$row->model_key] = [
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

    /**
     * Tenta resolver o nome comercial do modelo para nossa chave interna.
     */
    protected function resolveModelKey(string $model): string
    {
        $model = strtolower($model);

        // Try exact/partial match against known keys (DB + fallback union)
        $knownKeys = array_unique(array_merge(
            array_keys(self::FALLBACK_PRICING),
            array_keys($this->loadDbPricing())
        ));

        foreach ($knownKeys as $key) {
            if ($key !== 'default' && str_contains($model, $key)) {
                return $key;
            }
        }

        // Family-level fallbacks
        if (str_contains($model, 'gemini')) {
            return 'gemini-1.5-flash';
        }

        if (str_contains($model, 'gpt-4')) {
            return 'gpt-4o';
        }

        return 'default';
    }
}
