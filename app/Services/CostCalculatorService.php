<?php

namespace App\Services;

class CostCalculatorService
{
    /**
     * Calculate cost in BRL using centralized pricing from config
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $config = config('services.ai.pricing');
        
        $prices = $config['models'][$model] ?? $config['models']['default'];
        $exchangeRate = $config['exchange_rate'] ?? 5.0;

        $costUsd = ($inputTokens * $prices['input']) + ($outputTokens * $prices['output']);
        $costBrl = $costUsd * $exchangeRate;

        return (float) $costBrl;
    }
}
