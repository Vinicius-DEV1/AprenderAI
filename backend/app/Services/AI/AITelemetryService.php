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
            $totalTokens = $result['usage']['total_tokens'] ?? 0;
            $modelName = $apiKey->preferred_model ?? 'unknown';

            // Inteligência Financeira: Calcula Custo da Transação
            $calculator = app(PriceCalculatorService::class);
            $cost = $calculator->calculateCost($modelName, $inputTokens, $outputTokens);

            AiRequestLog::create([
                'user_id' => $userId,
                'question_id' => $questionId,
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
        } catch (\Exception $e) {
            Log::warning("Telemetry Error: " . $e->getMessage());
        }
    }

    public function log(string $provider, string $status, string $message, int $code, array $details, ?int $keyId = null)
    {
        try {
            ApiLog::create([
                'provider' => $provider,
                'key_id' => $keyId,
                'status' => $status,
                'message' => substr($message, 0, 255),
                'status_code' => $code,
                'details' => json_encode($details),
            ]);
        } catch (\Exception $e) {
            Log::warning("ApiLog Error: " . $e->getMessage());
        }
    }
}
