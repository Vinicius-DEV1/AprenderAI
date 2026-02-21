<?php

namespace App\Services\AI;

class PriceCalculatorService
{
    /**
     * Tabela de Preços (USD por 1 Milhão de Tokens)
     * Valores baseados na precificação oficial (fev/2026).
     */
    protected const PRICING_TABLE = [
        'gemini-1.5-flash' => [
            'input' => 0.075,
            'output' => 0.30,
        ],
        'gemini-1.5-pro' => [
            'input' => 1.25,
            'output' => 5.00,
        ],
        'gpt-4o' => [
            'input' => 2.50,
            'output' => 10.00,
        ],
        'gpt-4o-mini' => [
            'input' => 0.150,
            'output' => 0.60,
        ],
        'grok-2' => [ // Exemplo fictício/aproximado
            'input' => 2.00,
            'output' => 10.00,
        ],
        // Fallback genérico para modelos não listados
        'default' => [
            'input' => 0.00,
            'output' => 0.00,
        ]
    ];

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
        // Normaliza o nome do modelo para encontrar a melhor correspondência
        $modelKey = $this->resolveModelKey($model);
        $pricing = self::PRICING_TABLE[$modelKey];

        // O preço é por 1.000.000 de tokens
        $inputCost = ($inputTokens / 1000000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000000) * $pricing['output'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Tenta resolver o nome comercial do modelo para nossa chave interna.
     */
    private function resolveModelKey(string $model): string
    {
        $model = strtolower($model);

        foreach (array_keys(self::PRICING_TABLE) as $key) {
            if ($key !== 'default' && str_contains($model, $key)) {
                return $key;
            }
        }

        // Se passar apenas "gemini" ou "gpt-4", tentamos deduzir um padrão
        if (str_contains($model, 'gemini')) {
            return 'gemini-1.5-flash'; // Fallback mais barato da família
        }
        
        if (str_contains($model, 'gpt-4')) {
            return 'gpt-4o';
        }

        return 'default';
    }
}
