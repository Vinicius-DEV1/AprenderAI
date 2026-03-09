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
            if (isset($decoded['text']) && is_string($decoded['text'])) {
                return $this->sanitize($decoded['text']);
            }
            return is_array($decoded) ? $decoded : [];
        }

        // 2. LIMPEZA DE MARKDOWN
        $cleanText = $text;

        // Handle ```json ... ``` blocks
        if (preg_match('/```(?:json)?\s*([\s\S]+?)(?:```|$)/i', $cleanText, $m)) {
            $cleanText = $m[1];
        } else {
            $cleanText = str_replace(['```json', '```'], '', $cleanText);
        }

        $cleanText = trim($cleanText);

        // Try parsing after cleanup
        $decoded = json_decode($cleanText, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($decoded['text']) && is_string($decoded['text'])) {
                return $this->sanitize($decoded['text']);
            }
            return is_array($decoded) ? $decoded : [];
        }

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

        $jsonSnippet = substr($cleanText, $start);

        // Pre-fix common JSON issues: Invalid backslash escapes from AI LaTeX generation.
        // The AI generates formulas like \( 56\% \) inside JSON strings.
        // In standard JSON, a backslash must be followed by ", \, /, b, f, n, r, t, or u.
        // Anything else (like \% or \() throws "Invalid \escape" or "Syntax error".
        // This regex finds any backslash that is NOT followed by a valid JSON escape char and doubles it.
        $jsonSnippet = preg_replace('/\\\\([^"\\\\\/bfnrtu])/', '\\\\\\\\$1', $jsonSnippet);

        // Fix raw control characters inside strings using a safer regex that targets strings
        // We only replace raw \n, \r, \t if they appear within JSON string values
        $jsonSnippet = preg_replace_callback('/"([^"\\\\]*|\\\\.)*"/', function ($m) {
            return str_replace(["\n", "\r", "\t"], ["\\n", "\\r", "\\t"], $m[0]);
        }, $jsonSnippet);

        // Ensure UTF-8 validity
        $jsonSnippet = mb_convert_encoding($jsonSnippet, 'UTF-8', 'UTF-8');

        // 4. RECUPERAÇÃO SIMPLES — cortar no último '}', remover trailing comma, fechar o array
        if ($isArr) {
            $lastBrace = strrpos($jsonSnippet, '}');
            if ($lastBrace !== false) {
                $cutSnippet = substr($jsonSnippet, 0, $lastBrace + 1);
                $cutSnippet = rtrim($cutSnippet);
                $cutSnippet = rtrim($cutSnippet, ',');
                $potentialJson = $cutSnippet . ']';

                $decoded = json_decode($potentialJson, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    Log::warning('[AIBATCH] Partial JSON Recovered (Step 4)');
                    return is_array($decoded) ? $decoded : [];
                }
            }
        }

        // 5. RECUPERAÇÃO PROGRESSIVA
        if ($isArr) {
            $recovered = $this->progressiveArrayRepair($jsonSnippet);
            if (!empty($recovered)) {
                Log::warning('[AIBATCH] Progressive JSON Repair Success. Count: ' . count($recovered));
                return $recovered;
            }
        }

        // 6. TENTATIVA FINAL
        $decoded = json_decode($jsonSnippet, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return is_array($decoded) ? $decoded : [];
        }

        Log::error('AI JSON Parse Error Final: ' . json_last_error_msg(), [
            'snippet_start' => substr($text, 0, 200),
            'snippet_end' => substr($text, -200)
        ]);

        return [];
    }

    /**
     * Progressively removes incomplete JSON objects from the end of a JSON array
     */
    protected function progressiveArrayRepair(string $jsonSnippet): array
    {
        $lastBrace = strlen($jsonSnippet);
        while (($lastBrace = strrpos(substr($jsonSnippet, 0, $lastBrace), '}')) !== false) {
            $potentialJson = rtrim(substr($jsonSnippet, 0, $lastBrace + 1), ',') . ']';
            $decoded = json_decode($potentialJson, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    public function extractImages(string $text): array
    {
        preg_match_all('/\!\[.*?\]\((.*?)\)/', $text, $matches);
        return array_unique($matches[1] ?? []);
    }
}
