<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\AiRequestLog;
use Illuminate\Support\Facades\Log;

class AITelemetryService
{
    public function logRequest(ApiKey $apiKey, string $prompt, array $result, float $executionTime, ?int $userId = null, ?int $questionId = null)
    {
        try {
            $inputTokens = $result['usage']['input_tokens'] ?? 0;
            $outputTokens = $result['usage']['output_tokens'] ?? 0;

            // Fallback estimation if tokens are missing but content exists
            $responseText = is_string($result['content']) ? $result['content'] : json_encode($result['content']);
            if ($outputTokens <= 0 && !empty($responseText) && empty($result['error'])) {
                $outputTokens = $this->estimateTokens($responseText);
            }

            if ($inputTokens <= 0 && !empty($prompt)) {
                $inputTokens = $this->estimateTokens($prompt);
            }

            $totalTokens = $result['usage']['total_tokens'] ?? ($inputTokens + $outputTokens);
            $modelName = $apiKey->preferred_model ?? 'unknown';

            // Inteligência Financeira: Calcula Custo da Transação
            $calculator = app(PriceCalculatorService::class);
            $cost = $calculator->calculateCost($apiKey->provider, $modelName, $inputTokens, $outputTokens);

            // Force casting to avoid Eloquent silently dropping it on nullable fields
            $safeUserId = $userId !== null ? (int) $userId : null;
            $safeQuestionId = $questionId !== null ? (int) $questionId : null;

            if ($safeUserId === null) {
                Log::warning("AITelemetryService: logRequest called without user_id.", ['provider' => $apiKey->provider, 'prompt' => substr($prompt, 0, 50)]);
            }

            $log = AiRequestLog::create([
                'user_id' => $safeUserId,
                'question_id' => $safeQuestionId,
                'api_key_id' => $apiKey->id,
                'api_key_name' => substr($apiKey->key, -4), // Optional hint
                'provider' => $apiKey->provider,
                'model' => $modelName,
                'prompt_text' => $prompt,
                'response_text' => is_string($result['content']) ? $result['content'] : json_encode($result['content']),
                'tokens_used_input' => $inputTokens,
                'tokens_used_output' => $outputTokens,
                'tokens_used_total' => $totalTokens,
                'execution_time' => $executionTime,
                'estimated_cost' => $cost,
            ]);

            return $cost;
        } catch (\Exception $e) {
            Log::warning("Telemetry Error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return 0;
        }
    }

    public function log(string $provider, string $status, string $message, int $code, array $details, ?int $keyId = null)
    {
        try {
            ApiLog::create([
                'provider' => $provider,
                'key_id' => $keyId,
                'status' => $status,
                'type' => 'error', // Required by database constraint
                'message' => substr($message, 0, 255),
                'status_code' => $code,
                'payload' => $details, // Field name in model is payload, but using details array
            ]);
        } catch (\Exception $e) {
            Log::warning("ApiLog Error: " . $e->getMessage());
        }
    }

    /**
     * Estimativa de tokens baseada em caracteres (Fallback).
     * Média de 3.8 caracteres por token para Português/Inglês.
     */
    public function estimateTokens(string $text): int
    {
        if (empty($text))
            return 0;
        return max(1, (int) ceil(mb_strlen($text) / 3.8));
    }
}
