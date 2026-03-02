<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Models\Question;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\PromptService;
use App\Services\AI\ResponseSanitizer;
use App\Services\AI\AITelemetryService;

/**
 * AIService - Core service for AI interaction and management.
 */
class AIService
{
    protected $providers = ['openai', 'gemini', 'grok'];
    protected $promptService;
    protected $responseSanitizer;
    protected $telemetryService;

    public function __construct(
        PromptService $promptService,
        ResponseSanitizer $responseSanitizer,
        AITelemetryService $telemetryService
    ) {
        $this->promptService = $promptService;
        $this->responseSanitizer = $responseSanitizer;
        $this->telemetryService = $telemetryService;
    }

    /**
     * Checks if streaming is globally enabled.
     */
    public function isStreamingEnabled(): bool
    {
        return \App\Models\Setting::where('key', 'ai_streaming_enabled')->value('value') === 'true';
    }

    /**
     * Checks if at least one provider has an active key for a given capability.
     */
    public function hasActiveKey(string $capability = ApiKey::CAPABILITY_GENERAL): bool
    {
        return ApiKey::getKeyForCapability($capability) !== null;
    }

    /**
     * Evaluates question difficulty using the best available provider with failover.
     */
    public function evaluateQuestionDifficulty(\App\Models\Question $question): ?array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_TRIAGE)) {
            Log::warning('AI Difficulty Evaluation failed: No active API key for Triage.');
            return null;
        }

        return $this->executeWithFailover(ApiKey::CAPABILITY_TRIAGE, function ($apiKey) use ($question) {
            $provider = $apiKey->provider;

            $prompt = $this->promptService->get('question_difficulty_evaluator', [
                'question_text' => $question->statement,
                // alternativesAsMap(): retorna ['A'=>'texto', 'B'=>'texto'...]
                // Substitui json_encode($question->alternatives) que serializava
                // a Collection de objetos QuestionAlternative — confuso para a IA.
                'alternatives' => json_encode($question->alternativesAsMap())
            ]);

            $result = $this->callAI($provider, $apiKey, $prompt);
            $apiKey->incrementUsage();

            $content = $result['content'];

            if (
                isset($content['difficulty']) &&
                isset($content['reasoning']) &&
                !empty(trim($content['reasoning']))
            ) {

                $difficulty = match ($content['difficulty']) {
                    'easy' => 'easy',
                    'medium' => 'medium',
                    'hard' => 'hard',
                    default => 'medium'
                };

                $question->update([
                    'difficulty' => $difficulty,
                    'difficulty_reasoning' => trim($content['reasoning'])
                ]);

                Log::info('AI Difficulty Evaluation succeeded', [
                    'question_id' => $question->id,
                    'difficulty' => $difficulty
                ]);

                return $content;
            }

            Log::error('AI Difficulty Evaluation failed: Invalid response format.', [
                'question_id' => $question->id,
                'response' => $content
            ]);

            throw new \Exception('Invalid response format for difficulty evaluation.');
        });
    }

    // getFirstAvailableProvider was completely replaced by ApiKey::getKeysForCapability router.

    /**
     * Orchestrates AI calls and logs execution details.
     * Captures non-200 responses for robust telemetry.
     */
    protected function callAI(string $provider, ApiKey $apiKey, string $prompt, ?int $userId = null): array
    {
        $provider = $apiKey->effective_provider;
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
            $this->telemetryService->logRequest($apiKey, $prompt, $result, $executionTime, $userId);

            return $result;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $statusCode = 500;
            $errorMessage = $e->getMessage();

            // Detect Status Code if hidden in message
            if (preg_match('/Status Code: (\d+)/', $errorMessage, $matches)) {
                $statusCode = (int) $matches[1];
            } elseif (str_contains($errorMessage, '429')) {
                $statusCode = 429;
                $errorMessage = " Limite de Requisições Atingido (Quota Exceeded)";
            } elseif (str_contains($errorMessage, '401') || str_contains($errorMessage, '403')) {
                $statusCode = 403;
                $errorMessage = " Erro de Autenticação/Permissão (Invalid Key)";
            }

            $apiKey->update([
                'last_error_message' => "[$statusCode] $errorMessage",
                'last_error_at' => now(),
            ]);

            // SRE: Update ApiKey status to trigger auto-healing router
            if ($statusCode === 429) {
                $apiKey->update(['status' => 'quota_exceeded']);
            } elseif (in_array($statusCode, [401, 403])) {
                $apiKey->update(['status' => 'offline']);
            }

            // Persistence for Admin Dashboard (ApiLog)
            $this->telemetryService->log($provider, 'error', $errorMessage, $statusCode, ['error_detail' => $e->getMessage()], $apiKey->id);

            // Conditional logging for AI Request Log (Transaction log)
            if ($statusCode !== 429) {
                $this->telemetryService->logRequest($apiKey, $prompt, ['content' => ['error' => $e->getMessage()], 'usage' => []], $executionTime, $userId);
            }

            throw $e;
        }
    }

    /**
     * Orchestrates streaming AI calls. Yields chunks as they arrive.
     */
    protected function callAIStream(string $provider, ApiKey $apiKey, string $prompt, ?int $userId = null): \Generator
    {
        $provider = $apiKey->effective_provider;
        Log::info("DEBUG: Using Streaming API Key ID: {$apiKey->id} for provider: {$provider}");
        $startTime = microtime(true);
        $fullText = "";

        try {
            $stream = match ($provider) {
                'openai' => $this->callOpenAIStream($apiKey, $prompt),
                'gemini' => $this->callGeminiStream($apiKey, $prompt),
                default => throw new \Exception("Streaming not supported for provider: $provider")
            };

            foreach ($stream as $chunk) {
                $fullText .= $chunk;
                yield $chunk;
            }

            $executionTime = microtime(true) - $startTime;
            // Note: Token count estimation or final check might be needed here
            $this->telemetryService->logRequest($apiKey, $prompt, [
                'content' => $fullText,
                'usage' => ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0] // Usage usually comes in stream for OpenAI
            ], $executionTime, $userId);

        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $statusCode = 500;
            $errorMessage = $e->getMessage();

            if (preg_match('/Status Code: (\d+)/', $errorMessage, $matches)) {
                $statusCode = (int) $matches[1];
            } elseif (str_contains($errorMessage, '429')) {
                $statusCode = 429;
                $errorMessage = " Limite de Requisições Atingido (Quota Exceeded)";
            } elseif (str_contains($errorMessage, '401') || str_contains($errorMessage, '403')) {
                $statusCode = 403;
                $errorMessage = " Erro de Autenticação/Permissão (Invalid Key)";
            }

            $apiKey->update([
                'last_error_message' => "[$statusCode] $errorMessage",
                'last_error_at' => now(),
            ]);

            // SRE: Update ApiKey status to trigger auto-healing router
            if ($statusCode === 429) {
                $apiKey->update(['status' => 'quota_exceeded']);
            } elseif (in_array($statusCode, [401, 403])) {
                $apiKey->update(['status' => 'offline']);
            }

            // Persistence for Admin Dashboard (ApiLog)
            $this->telemetryService->log($provider, 'error', $errorMessage, $statusCode, ['error_detail' => $e->getMessage()], $apiKey->id);

            Log::error("Streaming AI Error: " . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Makes a request to OpenAI API.
     */
    protected function callOpenAI(ApiKey $apiKey, string $prompt): array
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $model = $apiKey->preferred_model ?? 'gpt-4o';

        $response = Http::withToken($apiKey->decrypted_key)
            ->connectTimeout(15)
            ->timeout(60)
            ->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenAI API Error: " . $response->body() . " (Status Code: " . $response->status() . ")");
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $content = $data['choices'][0]['message']['content'] ?? '';

        $json = $this->responseSanitizer->sanitize($content);
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

    protected function callOpenAIStream(ApiKey $apiKey, string $prompt): \Generator
    {
        $url = 'https://api.openai.com/v1/chat/completions';
        $model = $apiKey->preferred_model ?? 'gpt-4o';

        $client = new \GuzzleHttp\Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey->decrypted_key,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.7,
                'stream' => true,
            ],
            'stream' => true,
        ]);

        $body = $response->getBody();
        while (!$body->eof()) {
            $line = $this->readLine($body);
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                if ($data === '[DONE]')
                    break;

                $json = json_decode($data, true);
                $content = $json['choices'][0]['delta']['content'] ?? '';
                if ($content)
                    yield $content;
            }
        }
    }

    protected function readLine($body): string
    {
        $line = '';
        while (!$body->eof()) {
            $char = $body->read(1);
            if ($char === "\n")
                break;
            $line .= $char;
        }
        return trim($line);
    }

    /**
     * Makes a request to Gemini API, supporting image attachments.
     */
    protected function callGemini(ApiKey $apiKey, string $prompt): array
    {
        $model = $apiKey->preferred_model;
        $imageUrls = $this->responseSanitizer->extractImages($prompt);
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
            ->connectTimeout(15)
            ->withoutVerifying()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            throw new \Exception("Gemini API Error: " . $response->body() . " (Status Code: " . $response->status() . ")");
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $json = $this->responseSanitizer->sanitize($text);

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

    protected function callGeminiStream(ApiKey $apiKey, string $prompt): \Generator
    {
        $model = $apiKey->preferred_model;
        $decryptedKey = $apiKey->decrypted_key;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key={$decryptedKey}";

        $client = new \GuzzleHttp\Client();
        $response = $client->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.7]
            ],
            'stream' => true,
        ]);

        $body = $response->getBody();
        while (!$body->eof()) {
            $line = $this->readLine($body);
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                $json = json_decode($data, true);
                $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($content)
                    yield $content;
            }
        }
    }

    protected function callGrok(ApiKey $apiKey, string $prompt): array
    {
        return [];
    }

    protected function buildSimulationCorrectionPrompt(array $questionsAndAnswers, string $plan): string
    {
        $depthInstruction = match ($plan) {
            'free', 'basic' => "Para 'errors_explanation', forneça explicações CURTAS e DIRETAS (máximo 1 frase). Ex: 'A alternativa correta é B porque X.' foco apenas nas questões erradas.",
            'plus' => "Para 'errors_explanation', forneça explicações DETALHADAS e PEDAGÓGICAS. Explique o conceito por trás do erro e dê uma dica de estudo.",
            default => "Explicações concisas."
        };

        return $this->promptService->get('simulation_corrector', [
            'depth_instruction' => $depthInstruction,
            'data' => json_encode($questionsAndAnswers)
        ]);
    }
    public function generateEssayTopic(string $type, ?int $userId = null): array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_ESSAYS)) {
            throw new \Exception('Avaliador Xavier indisponível no momento (Key)');
        }

        return $this->executeWithFailover(ApiKey::CAPABILITY_ESSAYS, function ($apiKey) use ($type, $userId) {
            $provider = $apiKey->provider;

            $rules = "";
            if ($type === 'enem') {
                $rules .= "- Estilo ENEM: Um problema social/ambiental/cultural brasileiro.\n";
                $rules .= "- Inclua um 'Texto Motivador 1' (max 2 frases).\n";
                $rules .= "- Inclua 3 'Tópicos de Apoio' (bullets).\n";
                $rules .= "- Inclua a frase tema explícita.\n";
            } else {
                $rules .= "- Estilo CONCURSO PÚBLICO: Tema técnico ou atualidade (ex: Adm Pública, Direito, Tecnologia).\n";
                $rules .= "- Comando direto: 'Disserte sobre...'.\n";
            }

            $prompt = $this->promptService->get('essay_topic_generator', [
                'essay_type' => $type,
                'rules' => $rules
            ]);

            $result = $this->callAI($provider, $apiKey, $prompt, $userId);
            $apiKey->incrementUsage();

            $content = $result['content'];
            if (isset($content['error'])) {
                throw new \Exception($content['error']);
            }
            if (!isset($content['title']) || !isset($content['description'])) {
                throw new \Exception('Formato de resposta inválido do Xavier.');
            }

            return $content;
        });
    }

    public function detectOffTopic(string $title, string $content, string $type, ?int $userId = null): array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_ESSAYS))
            return ['off_topic' => false, 'reason' => 'API não disponível'];

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_ESSAYS, function ($apiKey) use ($title, $content, $type, $userId) {
                $provider = $apiKey->provider;
                $prompt = $this->buildOffTopicPrompt($title, $content, $type);

                $result = $this->callAI($provider, $apiKey, $prompt, $userId);
                $apiKey->incrementUsage();

                $responseContent = $result['content'];

                return [
                    'off_topic' => isset($responseContent['off_topic']) ? (bool) $responseContent['off_topic'] : false,
                    'reason' => $responseContent['reason'] ?? 'Indeterminado'
                ];
            });
        } catch (\Exception $e) {
            Log::error('OffTopic Detection failed', ['error' => $e->getMessage(), 'title' => $title]);
            // Fail open
            return ['off_topic' => false, 'reason' => 'Falha na detecção'];
        }
    }

    public function evaluateEssay(string $title, string $content, string $type, ?int $userId = null): ?array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_ESSAYS))
            return null;

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_ESSAYS, function ($apiKey) use ($title, $content, $type, $userId) {
                $provider = $apiKey->provider;
                $prompt = $this->buildXavierEvaluationPrompt($title, $content, $type);

                $result = $this->callAI($provider, $apiKey, $prompt, $userId);
                $apiKey->incrementUsage();

                $responseContent = $result['content'];
                if (!isset($responseContent['score'])) {
                    $responseContent['score'] = 0;
                }

                return [
                    'provider' => $provider,
                    'response' => $responseContent,
                    'usage' => $result['usage'] ?? []
                ];
            });
        } catch (\Exception $e) {
            Log::error('Xavier Evaluation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function buildXavierEvaluationPrompt(string $title, string $content, string $type): string
    {
        $maxScore = ($type === 'enem') ? 1000 : 100;

        return $this->promptService->get('essay_evaluator', [
            'essay_type' => $type,
            'essay_title' => $title,
            'essay_content' => $content,
            'max_score' => $maxScore
        ]);
    }

    protected function buildOffTopicPrompt(string $title, string $content, string $type): string
    {
        return $this->promptService->get('essay_offtopic_detector', [
            'essay_type' => $type,
            'essay_title' => $title,
            'essay_content' => $content,
        ]);
    }

    public function generateQuestions(string $subject, int $quantity = 1, array $context = []): array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_QUESTIONS)) {
            return [];
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_QUESTIONS, function ($apiKey) use ($subject, $quantity, $context) {
                $provider = $apiKey->provider;
                $prompt = $this->buildQuestionGenerationPrompt($subject, $quantity, $context);
                $result = $this->callAI($provider, $apiKey, $prompt);
                $apiKey->incrementUsage();

                $content = $result['content'];

                if (isset($content['questions']) && is_array($content['questions'])) {
                    return $content['questions'];
                }

                return [];
            });
        } catch (\Exception $e) {
            Log::error('AI Question Generation failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    protected function buildQuestionGenerationPrompt(string $subject, int $quantity, array $context = []): string
    {
        return $this->promptService->get('question_generator_standard', [
            'quantity' => $quantity,
            'subject' => $subject,
            'context' => json_encode($context)
        ]);
    }
    /**
     * Interaction chat focusing on a specific exam question (Simulations context).
     */
    public function chatAboutQuestion(mixed $question, mixed $simulation, string $userMessage, array $history): ?string
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_QUESTIONS)) {
            return "Desculpe, o sistema de IA está offline no momento.";
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_QUESTIONS, function ($apiKey) use ($question, $simulation, $userMessage, $history) {
                $provider = $apiKey->provider;

                $questionText = $question->statement;
                $alternatives = json_encode($question->alternativesAsMap());
                $correctAnswer = $question->correct_answer;

                $userAnswer = $simulation->answers()->where('question_id', $question->id)->first();
                $userAnswerText = $userAnswer ? $userAnswer->user_answer : 'Não respondida';

                $result = $this->callAI($provider, $apiKey, $this->promptService->get('xavier_tutor', [
                    'question_text' => $questionText,
                    'alternatives' => $alternatives,
                    'correct_answer' => $correctAnswer,
                    'user_answer' => $userAnswerText,
                    'chat_history' => $this->formatChatHistory($history),
                    'user_message' => $userMessage
                ]), $simulation->user_id);
                $apiKey->incrementUsage();
                $content = $result['content'];

                if (is_array($content) && isset($content['text'])) {
                    return $content['text'];
                }

                return $content['text'] ?? "Erro ao interpretar resposta.";
            });

        } catch (\Exception $e) {
            Log::error('AI Chat failed', ['error' => $e->getMessage()]);
            return "Desculpe, ocorreu um erro ao processar sua dúvida: " . $e->getMessage();
        }
    }

    public function streamChatAboutQuestion(mixed $question, mixed $simulation, string $userMessage, array $history): \Generator
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_QUESTIONS)) {
            yield "Desculpe, o sistema de IA está offline no momento.";
            return;
        }

        $keys = ApiKey::getKeysForCapability(ApiKey::CAPABILITY_QUESTIONS);
        $lastException = null;

        $questionText = $question->statement;
        $alternatives = json_encode($question->alternativesAsMap());
        $correctAnswer = $question->correct_answer;
        $userAnswer = $simulation->answers()->where('question_id', $question->id)->first();
        $userAnswerText = $userAnswer ? $userAnswer->user_answer : 'Não respondida';

        $prompt = $this->promptService->get('xavier_tutor', [
            'question_text' => $questionText,
            'alternatives' => $alternatives,
            'correct_answer' => $correctAnswer,
            'user_answer' => $userAnswerText,
            'chat_history' => $this->formatChatHistory($history),
            'user_message' => $userMessage
        ]);

        foreach ($keys as $apiKey) {
            try {
                $provider = $apiKey->provider;
                $stream = $this->callAIStream($provider, $apiKey, $prompt, $simulation->user_id);
                // Testa se a primeira linha (conexão iterável) funciona sem erro de auth/quota.
                // Como Generators não iniciam as exceções sem iteração, o callAIStream capta do $client->post.

                yield from $stream;

                $apiKey->incrementUsage();
                return; // Sucesso na streaming

            } catch (\Exception $e) {
                $lastException = $e;

                if ($this->isRetriableError($e)) {
                    Log::warning("Streaming provider {$apiKey->provider} failed, failing over to next priority...", [
                        'error' => $e->getMessage()
                    ]);
                    $this->banKeyTemporarily($apiKey, $e);
                    continue;
                }

                // Erros locais/formatos ou 400 sem retry vazam
                break;
            }
        }

        yield "Desculpe, ocorreu um erro ao se comunicar com a IA no momento.";
    }

    /**
     * Interaction chat for standalone questions (outside simulations).
     * @param Question $question
     * @param string $userAnswer
     * @param string $userMessage
     * @param array $history
     * @return string|null
     */
    public function chatAboutStandaloneQuestion(Question $question, string $userAnswer, string $userMessage, array $history, ?int $userId = null): ?string
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_QUESTIONS)) {
            return "Desculpe, o sistema de IA está offline no momento.";
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_QUESTIONS, function ($apiKey) use ($question, $userAnswer, $userMessage, $history, $userId) {
                $provider = $apiKey->provider;

                $questionText = $question->statement;
                $alternatives = json_encode($question->alternativesAsMap());
                $correctAnswer = $question->correct_answer;

                $result = $this->callAI($provider, $apiKey, $this->promptService->get('xavier_tutor', [
                    'question_text' => $questionText,
                    'alternatives' => $alternatives,
                    'correct_answer' => $correctAnswer,
                    'user_answer' => $userAnswer,
                    'chat_history' => $this->formatChatHistory($history),
                    'user_message' => $userMessage
                ]), $userId);
                $apiKey->incrementUsage();

                $content = $result['content'];

                if (is_array($content) && isset($content['text'])) {
                    return $content['text'];
                }

                return $content['text'] ?? "Erro ao interpretar resposta.";
            });
        } catch (\Exception $e) {
            Log::error('AI Standalone Chat failed', [
                'question_id' => $question->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function streamChatAboutStandaloneQuestion(Question $question, string $userAnswer, string $userMessage, array $history, ?int $userId = null): \Generator
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_QUESTIONS)) {
            yield "Desculpe, o sistema de IA está offline no momento.";
            return;
        }

        $keys = ApiKey::getKeysForCapability(ApiKey::CAPABILITY_QUESTIONS);
        $lastException = null;

        $questionText = $question->statement;
        $alternatives = json_encode($question->alternativesAsMap());
        $correctAnswer = $question->correct_answer;

        $prompt = $this->promptService->get('xavier_tutor', [
            'question_text' => $questionText,
            'alternatives' => $alternatives,
            'correct_answer' => $correctAnswer,
            'user_answer' => $userAnswer,
            'chat_history' => $this->formatChatHistory($history),
            'user_message' => $userMessage
        ]);

        foreach ($keys as $apiKey) {
            try {
                $provider = $apiKey->provider;
                $stream = $this->callAIStream($provider, $apiKey, $prompt, $userId);

                yield from $stream;

                $apiKey->incrementUsage();
                return;

            } catch (\Exception $e) {
                $lastException = $e;

                if ($this->isRetriableError($e)) {
                    Log::warning("Streaming provider {$apiKey->provider} failed, failing over to next...", [
                        'error' => $e->getMessage()
                    ]);
                    $this->banKeyTemporarily($apiKey, $e);
                    continue;
                }

                break;
            }
        }

        yield "Desculpe, ocorreu um erro ao se comunicar com a IA no momento.";
    }

    /**
     * Dispatcher for API key validation.
     * Uses a LIGHTWEIGHT strategy by listing models instead of generating content.
     */
    public function validateKey(string $provider, string $key): array
    {
        try {
            return match ($provider) {
                'openai' => $this->validateOpenAIKey($key),
                'gemini' => $this->validateGeminiKey($key),
                'grok' => $this->validateGrokKey($key),
                default => ['is_valid' => false, 'error' => "Provedor '$provider' não suportado."]
            };
        } catch (\Exception $e) {
            Log::error("Key validation failed for $provider: " . $e->getMessage());
            return ['is_valid' => false, 'error' => $e->getMessage()];
        }
    }

    public function generateJson(string $prompt, ?string $model = null): array
    {
        $provider = $model ? $this->getProviderForModel($model) : null;

        return $this->executeWithFailover(ApiKey::CAPABILITY_GENERAL, function ($apiKey) use ($prompt, $model) {
            $provider = $apiKey->provider;
            if ($model) {
                $apiKey->preferred_model = $model;
            }

            $result = $this->callAI($provider, $apiKey, $prompt);
            return ['data' => $result['content']];
        }, $provider);
    }

    protected function getProviderForModel(string $model): ?string
    {
        if (str_contains($model, 'gpt'))
            return 'openai';
        if (str_contains($model, 'gemini'))
            return 'gemini';
        return null;
    }

    public function generateStudyPlan(array $stats, array $input): ?array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_STUDY_PLANS)) {
            return null;
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_STUDY_PLANS, function ($apiKey) use ($stats, $input) {
                $provider = $apiKey->effective_provider;
                $prompt = $this->promptService->get('study_plan_generator', [
                    'stats' => json_encode($stats),
                    'input' => json_encode($input)
                ]);

                $result = $this->callAI($provider, $apiKey, $prompt);
                $apiKey->incrementUsage();

                return $result['content'];
            });
        } catch (\Exception $e) {
            Log::error('AI Study Plan Generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Engine Central de Failover para as chamadas de API.
     * Iterates over priorized available keys using $closure.
     */
    protected function executeWithFailover(string $capability, \Closure $closure, ?string $provider = null)
    {
        $keys = ApiKey::getKeysForCapability($capability, $provider);
        $lastException = null;

        if ($keys->isEmpty()) {
            throw new \Exception("Nenhum provedor de IA online ou com quota disponível para a rota: {$capability}.");
        }

        foreach ($keys as $apiKey) {
            try {
                return $closure($apiKey);
            } catch (\Exception $e) {
                $lastException = $e;

                if ($this->isRetriableError($e)) {
                    Log::warning("AI Provider failed, failing over to next priority...", [
                        'capability' => $capability,
                        'key_id' => $apiKey->id,
                        'error' => $e->getMessage()
                    ]);

                    $this->banKeyTemporarily($apiKey, $e);
                    continue;
                }

                // Se for erro na formatação do prompt (ex. 400 Bad Request), jogar pra cima pois tentamos e fomos rejeitados na raiz.
                throw $e;
            }
        }

        throw $lastException ?? new \Exception("Falha completa de Failover para a rota de IA: {$capability}.");
    }

    /**
     * Verifica se o erro gerado na chamada é passível de retry em outra chave.
     */
    protected function isRetriableError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        // 429 = Quota. 500, 502, 503, 504 = Server error do Google/OpenAI.
        if (
            str_contains($message, '429') ||
            str_contains($message, '500') ||
            str_contains($message, '502') ||
            str_contains($message, '503') ||
            str_contains($message, '504') ||
            str_contains($message, 'quota_exceeded') ||
            str_contains($message, 'timeout') ||
            str_contains($message, 'connection refused')
        ) {
            return true;
        }

        // Erros 401/403 (Invalid Key) - A chave testada não serve mais
        if (str_contains($message, '401') || str_contains($message, '403')) {
            return true;
        }

        // 400 Bad Request (conteúdo negado, erro do payload) -> NAO recriar, todas chaves vão dar erro
        return false;
    }

    /**
     * Coloca a ID da key em um cache de blacklist rápido por 60 min
     * impedindo a extração excessiva do BD enquando o HealthChecker não roda.
     */
    protected function banKeyTemporarily(ApiKey $apiKey, \Exception $e): void
    {
        $bannedIds = Cache::get('api_key_blacklist', []);
        if (!in_array($apiKey->id, $bannedIds)) {
            $bannedIds[] = $apiKey->id;
        }

        Cache::put('api_key_blacklist', $bannedIds, now()->addMinutes(60));
    }

    public function interpretSearchPrompt(string $userPrompt, array $filterOptions): ?array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_SEARCH)) {
            return null;
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_SEARCH, function ($apiKey) use ($userPrompt, $filterOptions) {
                $provider = $apiKey->provider;
                $aiName = \App\Models\Setting::where('key', 'ai_name')->value('value') ?? 'Xavier';

                // LÓGICA DE MAPEAMENTO (Tema vs Assunto):
                // O Xavier deve mapear a coluna 'topic' do JSON resultante para:
                // - 'theme' (no banco) se o tipo for 'enem'
                // - 'topic' (no banco) se o tipo for 'concurso'
                $prompt = $this->promptService->get('ai_search_interpreter', [
                    'user_prompt' => $userPrompt,
                    'filter_options' => json_encode($filterOptions)
                ], "Você é o {$aiName}, um Agente de Busca moderno e empático. Seu objetivo é minerar o banco de dados para encontrar exatamente o que o aluno precisa.
DIRETRIZES:
1. Respostas Curtas: Use no máximo 5 linhas no campo 'suggestion_tip'. Seja encorajador e proativo.
2. Formato: Retorne APENAS o JSON: { \"type\": \"enem|concurso\", \"subject\": \"...\", \"topic\": \"...\", \"difficulty\": \"...\", \"year\": ..., \"keyword\": \"...\", \"suggestion_tip\": \"...\", \"suggestions\": [ {\"label\": \"Texto do Botão\", \"filters\": {...}} ] }
3. Sem Resultados: Se não encontrar nada, use 'suggestion_tip' para explicar de forma empática e 'suggestions' para propor caminhos alternativos.

Busca do usuário: '{user_prompt}'
Opções válidas (JSON): {filter_options}");

                $result = $this->callAI($provider, $apiKey, $prompt);
                $apiKey->incrementUsage();

                $content = $result['content'];

                // VALIDAÇÃO: Se o sanitizer retornou um array que só contém 'text', 
                // significa que o prompt não retornou um JSON válido de filtros.
                // Para a busca assistida, isso deve ser tratado como falha de interpretação.
                if (isset($content['text']) && count($content) === 1) {
                    throw new \Exception('AI Search Interpretation failed: AI returned text instead of filters JSON.');
                }

                return $content;
            });
        } catch (\Exception $e) {
            Log::error('AI Search Interpretation failed: ' . $e->getMessage());
            return null;
        }
    }

    protected function validateOpenAIKey(string $key): array
    {
        try {
            $response = Http::withToken($key)
                ->connectTimeout(5)
                ->timeout(10)
                ->get('https://api.openai.com/v1/models');

            if ($response->failed()) {
                $status = $response->status();
                $errorData = $response->json();
                $error = $errorData['error']['message'] ?? $response->body() ?? 'Erro desconhecido';
                return ['is_valid' => false, 'error' => "OpenAI Error ($status): $error"];
            }

            $data = $response->json('data');
            if (!is_array($data)) {
                return ['is_valid' => false, 'error' => "OpenAI Error: Resposta inválida."];
            }

            return [
                'is_valid' => true,
                'models' => collect($data)
                    ->map(fn($m) => ['id' => $m['id'], 'name' => strtoupper($m['id'])])
                    ->values()
                    ->toArray()
            ];
        } catch (\Exception $e) {
            return ['is_valid' => false, 'error' => "OpenAI Exception: " . $e->getMessage()];
        }
    }

    protected function validateGeminiKey(string $key): array
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models?key=$key";
            $response = Http::connectTimeout(5)
                ->timeout(10)
                ->get($url);

            if ($response->failed()) {
                $status = $response->status();
                $errorData = $response->json();

                $error = 'Erro desconhecido';
                if (isset($errorData['error']['message'])) {
                    $error = $errorData['error']['message'];
                } elseif (isset($errorData[0]['error']['message'])) {
                    $error = $errorData[0]['error']['message'];
                } elseif (is_string($errorData)) {
                    $error = $errorData;
                } elseif ($response->body()) {
                    $error = substr($response->body(), 0, 200);
                }

                return ['is_valid' => false, 'error' => "Gemini Error ($status): $error"];
            }

            $models = $response->json('models');
            if (!is_array($models)) {
                return ['is_valid' => false, 'error' => "Gemini Error: Resposta de modelos inválida."];
            }

            return [
                'is_valid' => true,
                'models' => collect($models)
                    ->map(fn($m) => [
                        'id' => str_replace('models/', '', $m['name']),
                        'name' => $m['displayName'] ?? $m['name']
                    ])
                    ->values()
                    ->toArray()
            ];
        } catch (\Exception $e) {
            return ['is_valid' => false, 'error' => "Gemini Exception: " . $e->getMessage()];
        }
    }

    protected function validateGrokKey(string $key): array
    {
        return ['is_valid' => false, 'error' => 'Grok validation not yet implemented.'];
    }

    /**
     * Formats chat history array into a readable string for the prompt.
     */
    protected function formatChatHistory(array $history): string
    {
        $formatted = "";
        foreach ($history as $msg) {
            $role = ($msg['role'] ?? 'user') === 'user' ? 'Aluno' : 'Professor';
            $text = $msg['message'] ?? $msg['content'] ?? '';
            $formatted .= "$role: $text\n";
        }
        return $formatted;
    }
}
