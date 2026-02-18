$content = @'
<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $providers = ['openai', 'gemini', 'grok'];
    protected $costCalculator;

    public function __construct(CostCalculatorService $costCalculator)
    {
        $this->costCalculator = $costCalculator;
    }

    protected function log(string $provider, string $type, string $message, ?int $statusCode = null, ?array $payload = null, ?int $apiKeyId = null): void
    {
        try {
            \App\Models\ApiLog::create([
                'api_key_id' => $apiKeyId ?? \App\Models\ApiKey::getActiveKeyForProvider($provider)?->id,
                'provider' => $provider,
                'type' => $type,
                'status_code' => $statusCode,
                'message' => $message,
                'payload' => $payload
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to write ApiLog: " . $e->getMessage());
        }
    }

    public function hasActiveKey(): bool
    {
        foreach ($this->providers as $provider) {
            if (ApiKey::getActiveKeyForProvider($provider)) {
                return true;
            }
        }
        return false;
    }

    public function evaluateQuestionDifficulty(\App\Models\Question $question): ?array
    {
        if (!$this->hasActiveKey()) {
            Log::warning('AI Difficulty Evaluation failed: No active API key.');
            return null;
        }

        $provider = $this->getFirstAvailableProvider();
        if (!$provider) {
            Log::warning('AI Difficulty Evaluation failed: No available provider.');
            return null;
        }

        $apiKey = ApiKey::getActiveKeyForProvider($provider);

        try {
            $prompt = "Avalie o nível de dificuldade desta questão de concurso/ENEM.\n\n" .
                "Questão: {$question->statement}\n" .
                "Alternativas: " . json_encode($question->alternatives) . "\n\n" .
                "REGRAS:\n" .
                "1. Analise o conteúdo técnico, a complexidade do enunciado e as pegadinhas.\n" .
                "2. Retorne APENAS um JSON válido com: { 'difficulty': 'easy/medium/hard', 'reasoning': 'Uma frase curta explicando o porquê' }.\n" .
                "3. Use português claro e didático.";

            $result = $this->callAI($provider, $apiKey, $prompt);
            $apiKey->incrementUsage();

            $content = $result['content'];

            if (isset($content['difficulty']) && isset($content['reasoning'])) {
                $difficulty = match($content['difficulty']) {
                    'easy' => 'easy',
                    'medium' => 'medium',
                    'hard' => 'hard',
                    default => 'medium'
                };

                $question->update([
                    'difficulty' => $difficulty,
                    'difficulty_reasoning' => $content['reasoning']
                ]);

                return $content;
            }

            Log::error('AI Difficulty Evaluation failed: Invalid response format.', [
                'question_id' => $question->id,
                'response' => $content
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('AI Difficulty Evaluation failed: Exception', [
                'question_id' => $question->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function getFirstAvailableProvider(): ?string
    {
        if (ApiKey::getActiveKeyForProvider('gemini')) {
            return 'gemini';
        }

        foreach ($this->providers as $provider) {
            if ($provider !== 'gemini' && ApiKey::getActiveKeyForProvider($provider)) {
                return $provider;
            }
        }

        return null;
    }

    protected function callAI(string $provider, ApiKey $apiKey, string $prompt, ?int $userId = null): array
    {
        Log::info("DEBUG: Using API Key ID: {$apiKey->id} for provider: {$provider}");
        $startTime = microtime(true);

        try {
            $result = match ($provider) {
                'openai' => $this->callOpenAI($apiKey, $prompt),
                'gemini' => $this->callGemini($apiKey, $prompt),
                'grok' => $this->callGrok($apiKey, $prompt),
                default => throw new \Exception("Provider not supported: $provider")
            };

            $executionTime = microtime(true) - $startTime;
            $this->logAiRequest($apiKey, $prompt, $result, $executionTime, $userId);

            return $result;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;

            if (!str_contains($e->getMessage(), '429')) {
                $this->logAiRequest($apiKey, $prompt, ['content' => ['error' => $e->getMessage()], 'usage' => []], $executionTime, $userId);
            }

            if (str_contains($e->getMessage(), '429')) {
                $this->log($provider, 'warning', "QUOTA EXHAUSTED: 429 received. Key ID: {$apiKey->id}. Job should retry.", 429, null, $apiKey->id);
            }

            throw $e;
        }
    }

    protected function callOpenAI(ApiKey $apiKey, string $prompt): array
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $model = $apiKey->preferred_model ?? 'gpt-4o';

        $response = Http::withToken($apiKey->decrypted_key)
            ->timeout(120)
            ->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenAI API Error: " . $response->body());
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $content = $data['choices'][0]['message']['content'] ?? '';

        $json = $this->sanitizeAIResponse($content);
        if (empty($json)) {
            $json = ['text' => $content];
        }

        return [
            'content' => $json,
            'usage' => [
                'input_tokens' => $usage['prompt_tokens'] ?? 0,
                'output_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ]
        ];
    }

    protected function callGemini(ApiKey $apiKey, string $prompt): array
    {
        $model = $apiKey->preferred_model;
        $imageUrls = $this->extractImages($prompt);
        $parts = [['text' => $prompt]];

        foreach ($imageUrls as $url) {
            try {
                $imageData = null;
                $mimeType = 'image/jpeg';

                if (str_starts_with($url, 'http')) {
                    $response = Http::timeout(10)->get($url);
                    if ($response->successful()) {
                        $imageData = base64_encode($response->body());
                        $mimeType = $response->header('Content-Type') ?: 'image/jpeg';
                    }
                } else {
                    $cleanUrl = ltrim($url, '/');
                    $path = public_path($cleanUrl);

                    if (file_exists($path)) {
                        $imageData = base64_encode(file_get_contents($path));
                        $mimeType = mime_content_type($path) ?: 'image/jpeg';
                    }
                }

                if ($imageData) {
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $imageData
                        ]
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to attach image: $url. Error: " . $e->getMessage());
            }
        }

        $payload = [
            'contents' => [['parts' => $parts]],
            'generationConfig' => ['temperature' => 0.7]
        ];

        $decryptedKey = $apiKey->decrypted_key;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$decryptedKey}";

        $response = Http::timeout(120)
            ->withoutVerifying()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            throw new \Exception("Gemini API Error: " . $response->body());
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $json = $this->sanitizeAIResponse($text);

        if (empty($json)) {
            $json = ['text' => $text];
        }

        $usageMeta = $data['usageMetadata'] ?? [];

        return [
            'content' => $json,
            'usage' => [
                'input_tokens' => $usageMeta['promptTokenCount'] ?? 0,
                'output_tokens' => $usageMeta['candidatesTokenCount'] ?? 0,
                'total_tokens' => $usageMeta['totalTokenCount'] ?? 0,
            ]
        ];
    }

    protected function callGrok(ApiKey $apiKey, string $prompt): array
    {
        return [];
    }

    protected function sanitizeAIResponse(?string $text): array
    {
        if (!$text) return [];

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

    protected function extractImages(string $text): array
    {
        preg_match_all('/\!\[.*?\]\((.*?)\)/', $text, $matches);
        return array_unique($matches[1] ?? []);
    }

    protected function logAiRequest(ApiKey $apiKey, string $prompt, array $result, float $executionTime, ?int $userId = null): void
    {
        try {
            $inputTokens = $result['usage']['input_tokens'] ?? 0;
            $outputTokens = $result['usage']['output_tokens'] ?? 0;
            $model = $apiKey->preferred_model ?? 'padrão';

            $estimatedCost = $this->costCalculator->calculateCost($model, $inputTokens, $outputTokens);

            \App\Models\AiRequestLog::create([
                'user_id' => $userId ?? (\Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::id() : null),
                'api_key_id' => $apiKey->id,
                'api_key_name' => $apiKey->provider . ' (' . $model . ')',
                'provider' => $apiKey->provider,
                'model' => $model,
                'prompt_text' => substr($prompt, 0, 10000),
                'response_text' => is_array($result['content']) ? json_encode($result['content']) : (string) $result['content'],
                'tokens_used_input' => $inputTokens,
                'tokens_used_output' => $outputTokens,
                'tokens_used_total' => $inputTokens + $outputTokens,
                'execution_time' => round($executionTime, 3),
                'estimated_cost' => $estimatedCost,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to log AI Request: " . $e->getMessage());
        }
    }
}
'@

$content | Out-File -FilePath "app\Services\AIService.php" -Encoding UTF8 -NoNewline
Write-Host "AIService.php deployed successfully!"

# Remove BOM if present
$bytes = [System.IO.File]::ReadAllBytes("app\Services\AIService.php")
if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
    Write-Host "Removing BOM..."
    $newBytes = $bytes[3..($bytes.Length - 1)]
    [System.IO.File]::WriteAllBytes("app\Services\AIService.php", $newBytes)
    Write-Host "BOM removed successfully!"
}

