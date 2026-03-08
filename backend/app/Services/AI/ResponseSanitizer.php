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

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $p1 = strpos($text, '{');
        $p2 = strpos($text, '[');
        $start = -1;

        if ($p1 !== false && $p2 !== false) {
            $start = min($p1, $p2);
        } elseif ($p1 !== false) {
            $start = $p1;
        } elseif ($p2 !== false) {
            $start = $p2;
        }

        $p3 = strrpos($text, '}');
        $p4 = strrpos($text, ']');
        $end = -1;

        if ($p3 !== false && $p4 !== false) {
            $end = max($p3, $p4);
        } elseif ($p3 !== false) {
            $end = $p3;
        } elseif ($p4 !== false) {
            $end = $p4;
        }

        if ($start !== -1 && $end !== -1 && $end > $start) {
            $cleanText = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($cleanText, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        $cleanText = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', trim($text));
        $decoded = json_decode($cleanText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("AI JSON Parse Error: " . json_last_error_msg(), [
                'raw_snippet' => substr($text, 0, 500)
            ]);
            return [];
        }

        return $decoded ?: [];
    }

    public function extractImages(string $text): array
    {
        preg_match_all('/\!\[.*?\]\((.*?)\)/', $text, $matches);
        return array_unique($matches[1] ?? []);
    }
}
