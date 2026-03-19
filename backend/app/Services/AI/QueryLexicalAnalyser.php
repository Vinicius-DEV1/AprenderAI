<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * QueryLexicalAnalyser
 * 
 * Componente do Xavier 2.0 responsável por analisar o prompt bruto do usuário
 * e identificar operadores lógicos naturais de Negação e Restrição.
 * 
 * Exemplos:
 * - "biologia menos ecologia" -> [positive: biologia, negative: ecologia]
 * - "apenas questões da banca FGV" -> [only: FGV]
 */
class QueryLexicalAnalyser
{
    protected array $negationKeywords = [
        'menos', 'não', 'sem', 'exceto', 'fora', 'tirando', 'exceção', 'livre de'
    ];

    protected array $restrictionKeywords = [
        'apenas', 'somente', 'exclusivamente', 'só', 'exclusivo'
    ];

    public function analyse(string $prompt): array
    {
        $prompt = mb_strtolower($prompt);
        
        $analysis = [
            'original_prompt' => $prompt,
            'positive_terms'  => [], // O que o usuário QUER
            'negative_terms'  => [], // O que o usuário NÃO QUER
            'restricted_terms' => [], // O que o usuário quer EXCLUSIVAMENTE
            'is_restricted'   => false, // Se há um operador "apenas/somente"
        ];

        // 1. Detecção de Restrição (Apenas/Somente)
        foreach ($this->restrictionKeywords as $kw) {
            $parts = explode(" {$kw} ", " {$prompt} ");
            if (count($parts) > 1) {
                $analysis['is_restricted'] = true;
                // O que vem DEPOIS do 'apenas' é o que deve ser filtrado exclusivamente
                $analysis['restricted_terms'][] = trim($parts[1]);
                // O que vem ANTES pode ser o contexto (ex: "questões de biologia apenas da FGV")
                if (!empty(trim($parts[0]))) {
                    $analysis['positive_terms'][] = trim($parts[0]);
                }
            }
        }

        // 2. Detecção de Negação (Menos/Exceto)
        foreach ($this->negationKeywords as $kw) {
            $parts = explode(" {$kw} ", " {$prompt} ");
            if (count($parts) > 1) {
                // O que veio ANTES da negação é positivo
                if (!empty(trim($parts[0]))) {
                    $analysis['positive_terms'][] = trim($parts[0]);
                }
                
                // O que veio DEPOIS da negação é negativo
                $analysis['negative_terms'][] = trim($parts[1]);
            }
        }

        // Se houver restrições e nenhum termo positivo explícito ainda, 
        // os próprios termos restritos são o foco positivo
        if ($analysis['is_restricted'] && empty($analysis['positive_terms'])) {
            $analysis['positive_terms'] = $analysis['restricted_terms'];
        }

        // Fallback: se não houver nada filtrado, o prompt todo é positivo
        if (empty($analysis['positive_terms']) && empty($analysis['negative_terms'])) {
            $analysis['positive_terms'] = [$prompt];
        }

        Log::info('[Xavier][Lexical] Prompt analysed.', $analysis);

        return $analysis;
    }
}
