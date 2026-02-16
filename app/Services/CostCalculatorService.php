<?php

namespace App\Services;

class CostCalculatorService
{
    /**
     * Gemini 1.5/2.0 Flash Prices (per 1M tokens)
     * Input: $0.075 / 1M
     * Output: $0.30 / 1M
     */
    protected const PRICES = [
        'gemini-1.5-flash' => [
            'input' => 0.075 / 1000000,
            'output' => 0.30 / 1000000,
        ],
        'gemini-1.5-pro' => [
            'input' => 3.50 / 1000000,
            'output' => 10.50 / 1000000,
        ],
        'gemini-2.0-flash' => [
            'input' => 0.10 / 1000000,
            'output' => 0.40 / 1000000,
        ],
        // Default prices if model not found
        'default' => [
            'input' => 0.075 / 1000000,
            'output' => 0.30 / 1000000,
        ],
    ];

    /**
     * Fixed exchange rate USD to BRL
     */
    protected const EXCHANGE_RATE = 5.0;

    /**
     * Calculate cost in BRL
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $prices = self::PRICES[$model] ?? self::PRICES['default'];

        $costUsd = ($inputTokens * $prices['input']) + ($outputTokens * $prices['output']);
        $costBrl = $costUsd * self::EXCHANGE_RATE;

        return (float) $costBrl;
    }
}
