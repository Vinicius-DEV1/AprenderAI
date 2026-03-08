<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class ResponseSanitizer
{
    /**
     * Sanitizes AI response by stripping markdown and extracting valid JSON.
     */
    public function sanitize(mixed $text): array
    {
        if (is_array($text)) {
            return $text;
        }

        if (!$text || !is_string($text)) {
            return [];
        }

        // 1. TENTA PARSEAR DIRETO
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Se o JSON for um objeto com a chave "text", sanitiza o conteúdo de "text"
            if (isset($decoded['text']) && is_string($decoded['text'])) {
                return $this->sanitize($decoded['text']);
            }
            return $decoded;
        }

        // 2. LIMPEZA DE MARKDOWN (Tratando blocos não fechados)
        $cleanText = $text;
        if (str_contains($cleanText, '```json')) {
            $parts = explode('```json', $cleanText);
            $cleanText = end($parts);
        }
        $cleanText = str_replace('```', '', $cleanText);
        $cleanText = trim($cleanText);

        // 3. EXTRAÇÃO AGRESSIVA (Busca o primeiro '[' ou '{')
        $pStartArr = strpos($cleanText, '[');
        $pStartObj = strpos($cleanText, '{');

        $start = -1;
        $isArr = false;
        if ($pStartArr !== false && $pStartObj !== false) {
            $start = min($pStartArr, $pStartObj);
            $isArr = ($pStartArr <= $pStartObj);
        } elseif ($pStartArr !== false) {
            $start = $pStartArr;
            $isArr = true;
        } elseif ($pStartObj !== false) {
            $start = $pStartObj;
        }

        if ($start === -1) {
            return [];
        }

        $jsonSnippet = substr($cleanText, $start);

        // 4. RECUPERAÇÃO DE TRUNCAMENTO
        $lastBrace = strrpos($jsonSnippet, '}');
        if ($lastBrace !== false) {
            $potentialJson = substr($jsonSnippet, 0, $lastBrace + 1);
            if ($isArr) {
                $potentialJson .= ']';
            }

            $decoded = json_decode($potentialJson, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Se o JSON recuperado for um objeto com a chave "text", sanitiza o conteúdo de "text"
                if (isset($decoded['text']) && is_string($decoded['text'])) {
                    return $this->sanitize($decoded['text']);
                }
                Log::warning("[AIBATCH] Partial JSON Recovered: Itens extraídos de resposta truncada.");
                return $decoded;
            }
        }

        // 5. TENTATIVA FINAL
        $decoded = json_decode($jsonSnippet, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($decoded['text']) && is_string($decoded['text'])) {
                return $this->sanitize($decoded['text']);
            }
            return $decoded;
        }

        Log::error("AI JSON Parse Error Final: " . json_last_error_msg(), [
            'snippet' => substr($text, 0, 300)
        ]);

        return [];
    }

    public function extractImages(string $text): array
    {
        preg_match_all('/\!\[.*?\]\((.*?)\)/', $text, $matches);
        return array_unique($matches[1] ?? []);
    }
}
