<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Question;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        )
    {
        $this->promptService = $promptService;
        $this->responseSanitizer = $responseSanitizer;
        $this->telemetryService = $telemetryService;
    }

    /**
     * Checks if at least one provider has an active key.
     */
    public function hasActiveKey(): bool
    {
        foreach ($this->providers as $provider) {
            if (ApiKey::getActiveKeyForProvider($provider)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Evaluates question difficulty using the best available provider.
     */
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

            if (isset($content['difficulty']) && 
                isset($content['reasoning']) && 
                !empty(trim($content['reasoning']))) {
                
                $difficulty = match($content['difficulty']) {
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
            return null;

        } catch (\Exception $e) {
            Log::error('AI Difficulty Evaluation failed: Exception', [
                'question_id' => $question->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Returns the first available AI provider starting with Gemini.
     */
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

    /**
     * Orchestrates AI calls and logs execution details.
     * Captures non-200 responses for robust telemetry.
     */
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
    public function generateEssayTopic(string $type): array
    {
        if (!$this->hasActiveKey()) {
            throw new \Exception('Avaliador Xavier indisponível no momento (Key)');
        }

        $provider = $this->getFirstAvailableProvider();
        if (!$provider)
            throw new \Exception('Avaliador Xavier indisponível no momento (Provider)');

        $apiKey = ApiKey::getActiveKeyForProvider($provider);

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

        try {
            $prompt = $this->promptService->get('essay_topic_generator', [
                'essay_type' => $type,
                'rules' => $rules
            ]);

            $result = $this->callAI($provider, $apiKey, $prompt);
            $apiKey->incrementUsage();

            $content = $result['content'];
            if (isset($content['error'])) {
                throw new \Exception($content['error']);
            }
            if (!isset($content['title']) || !isset($content['description'])) {
                throw new \Exception('Formato de resposta inválido do Xavier.');
            }

            return $content;
        } catch (\Exception $e) {
            Log::error("Xavier Topic Gen Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function evaluateEssay(string $title, string $content, string $type): ?array
    {
        if (!$this->hasActiveKey())
            return null;
        $provider = $this->getFirstAvailableProvider();
        if (!$provider)
            return null;

        $apiKey = ApiKey::getActiveKeyForProvider($provider);

        $prompt = $this->buildXavierEvaluationPrompt($title, $content, $type);

        try {
            $result = $this->callAI($provider, $apiKey, $prompt);
            $apiKey->incrementUsage();

            $content = $result['content'];
            if (!isset($content['score']))
                $content['score'] = 0;

            return [
                'provider' => $provider,
                'response' => $content,
                'usage' => $result['usage'] ?? []
            ];
        } catch (\Exception $e) {
            Log::error('Xavier Evaluation failed', ['error' => $e->getMessage()]);
            return null;
        } finally {
            if (isset($apiKey))
                $apiKey->incrementUsage();
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

    public function generateQuestions(string $subject, int $quantity = 1): array
    {
        if (!$this->hasActiveKey()) {
            return [];
        }

        $provider = $this->getFirstAvailableProvider();
        if (!$provider) {
            return [];
        }

        try {
            $apiKey = ApiKey::getActiveKeyForProvider($provider);
            $prompt = $this->buildQuestionGenerationPrompt($subject, $quantity);
            $result = $this->callAI($provider, $apiKey, $prompt);
            $apiKey->incrementUsage();

            $content = $result['content'];

            if (isset($content['questions']) && is_array($content['questions'])) {
                return $content['questions'];
            }

            return [];

        } catch (\Exception $e) {
            Log::error('AI Question Generation failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    protected function buildQuestionGenerationPrompt(string $subject, int $quantity): string
    {
        return $this->promptService->get('question_generator_standard', [
            'quantity' => $quantity,
            'subject' => $subject
        ]);
    }
    /**
     * Interaction chat focusing on a specific exam question (Simulations context).
     */
    public function chatAboutQuestion(mixed $question, mixed $simulation, string $userMessage, array $history): ?string
    {
        if (!$this->hasActiveKey()) {
            return "Desculpe, o sistema de IA está offline no momento.";
        }

        $provider = $this->getFirstAvailableProvider();
        if (!$provider) {
            return "Nenhum provedor de IA disponível.";
        }

        $apiKey = ApiKey::getActiveKeyForProvider($provider);

        try {
            $questionText = $question->statement;
            // alternativesAsMap(): ['A'=>'texto', 'B'=>'texto'...] — limpo para o prompt
            $alternatives = json_encode($question->alternativesAsMap());
            // correct_answer agora é um accessor virtual que lê is_correct da tabela relacional
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
            $content = $result['content'];

            if (is_array($content) && isset($content['text'])) {
                return $content['text'];
            }

            return $content['text'] ?? "Erro ao interpretar resposta.";

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), '429')) {
                throw $e;
            }
            Log::error('AI Chat failed', ['error' => $e->getMessage()]);
            return "Desculpe, ocorreu um erro ao processar sua dúvida.";
        } finally {
            if (isset($apiKey)) {
                $apiKey->incrementUsage();
            }
        }
    }

    /**
     * Interaction chat for standalone questions (outside simulations).
     * @param Question $question
     * @param string $userAnswer
     * @param string $userMessage
     * @param array $history
     * @return string|null
     */
    public function chatAboutStandaloneQuestion(Question $question, string $userAnswer, string $userMessage, array $history): ?string
    {
        if (!$this->hasActiveKey()) {
            return "Desculpe, o sistema de IA está offline no momento.";
        }

        $provider = $this->getFirstAvailableProvider();
        if (!$provider) {
            return "Nenhum provedor de IA disponível.";
        }

        $apiKey = ApiKey::getActiveKeyForProvider($provider);

        try {
            $questionText = $question->statement;
            // alternativesAsMap(): ['A'=>'texto', 'B'=>'texto'...] — limpo para o prompt
            $alternatives = json_encode($question->alternativesAsMap());
            // correct_answer agora é um accessor virtual que lê is_correct da tabela relacional
            $correctAnswer = $question->correct_answer;

            $result = $this->callAI($provider, $apiKey, $this->promptService->get('xavier_tutor', [
                'question_text' => $questionText,
                'alternatives' => $alternatives,
                'correct_answer' => $correctAnswer,
                'user_answer' => $userAnswer,
                'chat_history' => $this->formatChatHistory($history),
                'user_message' => $userMessage
            ]));
            $content = $result['content'];

            if (is_array($content) && isset($content['text'])) {
                return $content['text'];
            }

            return $content['text'] ?? "Erro ao interpretar resposta.";

        } catch (\Exception $e) {
            Log::error('AI Standalone Chat failed', [
                'question_id' => $question->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            if (isset($apiKey)) {
                $apiKey->incrementUsage();
            }
        }
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

    public function generateJson(string $prompt): array
    {
        $provider = $this->getFirstAvailableProvider();
        if (!$provider) {
            throw new \Exception('Nenhum provedor de IA disponível para geração de JSON.');
        }

        $apiKey = ApiKey::getActiveKeyForProvider($provider);
        $result = $this->callAI($provider, $apiKey, $prompt);

        return ['data' => $result['content']];
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
                    ->filter(fn($m) => str_contains($m['id'], 'gpt'))
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
                    ->filter(fn($m) => str_contains($m['name'], 'gemini') || str_contains($m['name'], 'learnlm'))
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
