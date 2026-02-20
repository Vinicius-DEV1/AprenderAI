<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Models\ApiLog;
use App\Models\AiRequestLog;
use Illuminate\Support\Facades\Log;

class AITelemetryService
{
    public function logRequest(ApiKey $apiKey, string $prompt, array $result, float $executionTime, ?int $userId = null)
    {
        try {
            AiRequestLog::create([
                'user_id' => $userId,
                'api_key_id' => $apiKey->id,
                'provider' => $apiKey->provider,
                'model' => $apiKey->preferred_model ?? 'unknown',
                'prompt_preview' => substr($prompt, 0, 255),
                'input_tokens' => $result['usage']['input_tokens'] ?? 0,
                'output_tokens' => $result['usage']['output_tokens'] ?? 0,
                'total_tokens' => $result['usage']['total_tokens'] ?? 0,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'status' => isset($result['content']['error']) ? 'failed' : 'success',
                'response_preview' => substr(json_encode($result['content']), 0, 255),
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