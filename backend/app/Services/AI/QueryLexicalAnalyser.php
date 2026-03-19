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

    /**
     * Analisa o prompt e retorna uma estrutura de intenção decomposta.
     */
    public function analyse(string $prompt): array
    {
        $prompt = mb_strtolower($prompt);
        
        $analysis = [
            'original_prompt' => $prompt,
            'positive_terms'  => [], // O que o usuário QUER
            'negative_terms'  => [], // O que o usuário NÃO QUER
            'is_restricted'   => false, // Se há um operador "apenas/somente"
        ];

        // 1. Detecção de Restrição (Apenas/Somente)
        foreach ($this->restrictionKeywords as $kw) {
            if (str_contains($prompt, $kw)) {
                $analysis['is_restricted'] = true;
                break;
            }
        }

        // 2. Detecção de Negação (Menos/Exceto)
        // Usamos uma lógica de quebra de prompt para identificar o que vem após a negação.
        foreach ($this->negationKeywords as $kw) {
            $parts = explode(" {$kw} ", " {$prompt} ");
            if (count($parts) > 1) {
                // O que veio ANTES da negação é positivo
                $analysis['positive_terms'][] = trim($parts[0]);
                
                // O que veio DEPOIS da negação é negativo
                // Se houver múltiplas negações, pegamos o resto da string e continuamos processando
                $analysis['negative_terms'][] = trim($parts[1]);
            }
        }

        // Se não houver termos negativos definidos, o prompt todo é positivo
        if (empty($analysis['negative_terms'])) {
            $analysis['positive_terms'] = [$prompt];
        }

        Log::info('[Xavier][Lexical] Prompt analysed.', $analysis);

        return $analysis;
    }
}
