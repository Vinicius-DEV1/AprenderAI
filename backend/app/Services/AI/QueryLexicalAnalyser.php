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

    protected array $difficultyMap = [
        'fácil'    => 'easy',
        'facil'    => 'easy',
        'fáceis'   => 'easy',
        'faceis'   => 'easy',
        'médio'    => 'medium',
        'medio'    => 'medium',
        'médios'   => 'medium',
        'medios'   => 'medium',
        'média'    => 'medium',
        'media'    => 'medium',
        'médias'   => 'medium',
        'medias'   => 'medium',
        'difícil'  => 'hard',
        'dificil'  => 'hard',
        'difíceis' => 'hard',
        'dificeis' => 'hard',
        'hard'     => 'hard',
        'complexa' => 'hard',
        'complexas'=> 'hard',
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
            'years'           => [], // Anos detectados (ex: [2023])
            'year_operator'   => '=', // Default para anos: igual
            'difficulty'      => null, // Nível de dificuldade detectado
            'organizations'   => [], // Bancas/Organizações detectadas
            'institutions'    => [], // Instituições de ensino detectadas
        ];

        $analysis = $this->detectDifficulty($prompt, $analysis);
        $analysis = $this->detectTemporal($prompt, $analysis);
        $analysis = $this->detectRestriction($prompt, $analysis);
        $analysis = $this->detectNegation($prompt, $analysis);
        $analysis = $this->detectEntities($prompt, $analysis);

        $analysis['clean_prompt'] = $this->cleanPrompt($prompt, $analysis);

        // Se houver restrições e nenhum termo positivo explícito ainda, 
        // os próprios termos restritos são o foco positivo
        if ($analysis['is_restricted'] && empty($analysis['positive_terms'])) {
            $analysis['positive_terms'] = $analysis['restricted_terms'];
        }

        // Fallback: se não houver nada filtrado, o prompt todo é positivo
        if (empty($analysis['positive_terms']) && empty($analysis['negative_terms'])) {
            $analysis['positive_terms'] = [$analysis['clean_prompt']];
        }

        Log::info('[Xavier][Lexical] Prompt analysed.', $analysis);

        return $analysis;
    }

    private function detectDifficulty(string &$prompt, array $analysis): array
    {
        // 0. Detecção de Dificuldade
        foreach ($this->difficultyMap as $keyword => $level) {
            // Usa word boundaries \b para não dar match em "médios" quando procura "médio" e deixar o "s"
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/iu', $prompt)) {
                $analysis['difficulty'] = $level;
                // Remove a palavra exata do prompt via regex
                $prompt = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/iu', '', $prompt);
                break;
            }
        }
        return $analysis;
    }

    private function detectTemporal(string &$prompt, array &$analysis): array
    {
        // 1. Detecção de Anos e Operadores Temporais
        if (preg_match_all('/\b(19|20)\d{2}\b/', $prompt, $matches)) {
            $analysis['years'] = array_map('intval', $matches[0]);
            
            // Detecta operadores como "desde", "após", "depois de", "superior a"
            if (preg_match('/(desde|após|depois|superior|acima de)\s+(19|20)\d{2}/', $prompt)) {
                $analysis['year_operator'] = '>=';
            } elseif (preg_match('/(até|antes|anterior|abaixo de)\s+(19|20)\d{2}/', $prompt)) {
                $analysis['year_operator'] = '<=';
            } elseif (preg_match('/(em|de)\s+(19|20)\d{2}/', $prompt)) {
                $analysis['year_operator'] = '=';
            }

            // Remove anos do prompt para não interferir em outras análises
            $prompt = preg_replace('/\b(19|20)\d{2}\b/', '', $prompt);
        }
        return $analysis;
    }

    private function detectEntities(string &$prompt, array &$analysis): array
    {
        // ── Detecção de Instituições populares ────────────────────────────────
        $institutions = [
            'enem'    => ['enem'],
            'usp'     => ['usp', 'fuvest'],
            'unicamp' => ['unicamp', 'comvest'],
            'unesp'   => ['unesp', 'vunesp'],
            'ufrj'    => ['ufrj'],
            'ufmg'    => ['ufmg'],
        ];

        foreach ($institutions as $key => $synonyms) {
            foreach ($synonyms as $synonym) {
                if (preg_match('/\b' . preg_quote($synonym, '/') . '\b/i', $prompt)) {
                    $analysis['institutions'][] = strtoupper($key);
                    $prompt = preg_replace('/\b' . preg_quote($synonym, '/') . '\b/i', '', $prompt);
                    break;
                }
            }
        }

        // ── Detecção de Bancas (Organizações) ─────────────────────────────────
        $orgs = [
            'fgv'        => ['fgv', 'getulio vargas', 'getúlio vargas'],
            'cebraspe'   => ['cebraspe', 'cespe'],
            'fcc'        => ['fcc', 'carlos chagas'],
            'vunesp'     => ['vunesp'],
            'idecan'     => ['idecan'],
            'cesgranrio' => ['cesgranrio'],
            'ibfc'       => ['ibfc'],
            'ibam'       => ['ibam'],
            'quadrix'    => ['quadrix'],
            'faperp'     => ['faperp'],
            'fundatec'   => ['fundatec'],
            'consulplan' => ['consulplan'],
        ];

        foreach ($orgs as $key => $synonyms) {
            foreach ($synonyms as $synonym) {
                if (preg_match('/\b' . preg_quote($synonym, '/') . '\b/i', $prompt)) {
                    $analysis['organizations'][] = strtoupper($key);
                    $prompt = preg_replace('/\b' . preg_quote($synonym, '/') . '\b/i', '', $prompt);
                    break;
                }
            }
        }

        return $analysis;
    }

    private function detectRestriction(string &$prompt, array &$analysis): array
    {
        foreach ($this->restrictionKeywords as $kw) {
            $parts = explode(" {$kw} ", " {$prompt} ");
            if (count($parts) > 1) {
                $analysis['is_restricted'] = true;
                $analysis['restricted_terms'][] = trim($parts[1]);
                if (!empty(trim($parts[0]))) {
                    $analysis['positive_terms'][] = trim($parts[0]);
                }
                $prompt = str_replace(" {$kw} ", ' ', $prompt);
            }
        }
        return $analysis;
    }

    private function detectNegation(string &$prompt, array &$analysis): array
    {
        foreach ($this->negationKeywords as $kw) {
            $parts = explode(" {$kw} ", " {$prompt} ");
            if (count($parts) > 1) {
                if (!empty(trim($parts[0]))) {
                    $analysis['positive_terms'][] = trim($parts[0]);
                }
                $analysis['negative_terms'][] = trim($parts[1]);
                $prompt = str_replace(" {$kw} ", ' ', $prompt);
            }
        }
        return $analysis;
    }

    private function cleanPrompt(string $prompt, array $analysis): string
    {
        // Remove espaços extras e pontuação final
        $clean = preg_replace('/\s+/', ' ', $prompt);
        return trim($clean, " \t\n\r\0\x0B.,;?!");
    }
}
