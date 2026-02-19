<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Question;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AIService - Core service for AI interaction and management.
 * 
 * IMPORTANT: This file MUST be saved in UTF-8 WITHOUT BOM to prevent
 * "Namespace declaration statement has to be the very first statement" errors in PHP.
 */
class AIService
{
    protected $providers = ['openai', 'gemini', 'grok'];
    protected $costCalculator;

    public function __construct(CostCalculatorService $costCalculator)
    {
        $this->costCalculator = $costCalculator;
    }

    /**
     * Logs internal API health checks and provider status to the 'ApiLog' table.
     * This feeds the "Logs de Eventos da API" section in the Admin Dashboard.
     */
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
            $this->logAiRequest($apiKey, $prompt, $result, $executionTime, $userId);

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
            $this->log($provider, 'error', $errorMessage, $statusCode, ['error_detail' => $e->getMessage()], $apiKey->id);

            // Conditional logging for AI Request Log (Transaction log)
            if ($statusCode !== 429) {
                $this->logAiRequest($apiKey, $prompt, ['content' => ['error' => $e->getMessage()], 'usage' => []], $executionTime, $userId);
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

    /**
     * Makes a request to Gemini API, supporting image attachments.
     */
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
            ->connectTimeout(15)
            ->withoutVerifying()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            throw new \Exception("Gemini API Error: " . $response->body() . " (Status Code: " . $response->status() . ")");
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

    protected function buildSimulationCorrectionPrompt(array $questionsAndAnswers, string $plan): string
    {
        $baseStructure = "Retorne APENAS um JSON válido com esta estrutura exata: {
            'total_correct': int, 
            'total_questions': int, 
            'errors_explanation': [
                { 'question_id': id_da_questao, 'why_wrong': 'motivo do erro', 'correct_approach': 'como resolver' }
            ]
        }";

        $depthInstruction = match ($plan) {
            'free', 'basic' => "Para 'errors_explanation', forneça explicações CURTAS e DIRETAS (máximo 1 frase). Ex: 'A alternativa correta é B porque X.' foco apenas nas questões erradas.",
            'plus' => "Para 'errors_explanation', forneça explicações DETALHADAS e PEDAGÓGICAS. Explique o conceito por trás do erro e dê uma dica de estudo.",
            default => "Explicações concisas."
        };

        return "Corrija as questões abaixo. $baseStructure\n\n$depthInstruction\n\nDados:\n" . json_encode($questionsAndAnswers);
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

        $prompt = "Você é o Professor Xavier, um avaliador experiente de redações.\n";
        $prompt .= "Sua tarefa: Criar um tema de redação inédito para $type.\n";
        $prompt .= "Regras:\n";
        if ($type === 'enem') {
            $prompt .= "- Estilo ENEM: Um problema social/ambiental/cultural brasileiro.\n";
            $prompt .= "- Inclua um 'Texto Motivador 1' (max 2 frases).\n";
            $prompt .= "- Inclua 3 'Tópicos de Apoio' (bullets).\n";
            $prompt .= "- Inclua a frase tema explícita.\n";
        } else {
            $prompt .= "- Estilo CONCURSO PÚBLICO: Tema técnico ou atualidade (ex: Adm Pública, Direito, Tecnologia).\n";
            $prompt .= "- Comando direto: 'Disserte sobre...'.\n";
        }
        $prompt .= "\nRetorne APENAS um objeto JSON válido. NÃO use markdown. NÃO use código ```json.\nEstrutura: { \"title\": \"Titulo do Tema\", \"description\": \"Texto completo do tema\" }.";

        try {
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

        return "Você é o Professor Xavier, corretor oficial de redações.\n" .
            "Corrija este texto seguindo rigorosamente os critérios do {$type}.\n" .
            "Tema: $title\n" .
            "Texto do Aluno:\n$content\n\n" .
            "Retorne APENAS JSON válido com esta estrutura exata:\n" .
            "{\n" .
            "  'score': (inteiro 0-$maxScore),\n" .
            "  'summary': 'Resumo geral em 1 parágrafo',\n" .
            "  'strengths': ['ponto forte 1', 'ponto forte 2'],\n" .
            "  'weaknesses': ['ponto a melhorar 1', 'ponto a melhorar 2'],\n" .
            "  'checklist': [ {'item': 'Coesão', 'status': 'ok'/'atenção'}, {'item': 'Gramática', 'status': 'ok'/'atenção'} ],\n" .
            "  'corrections': [ {'excerpt': 'trecho erro', 'issue': 'explicação erro', 'suggestion': 'sugestão correção'} ],\n" .
            "  'improved_version': 'Reescreva a redação mantendo a ideia do aluno, mas elevando para nota máxima.'\n" .
            "}\n" .
            "Seja polido, didático e motive o aluno.";
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
        return "Gere {$quantity} questões inéditas estilo ENEM de {$subject}.\n" .
            "Retorne APENAS um JSON válido com a chave 'questions' contendo uma lista de objetos.\n" .
            "Cada objeto deve ter: 'statement' (enunciado), 'alternatives' (objeto A:texto, B:texto...), 'correct_answer' (A,B,C,D ou E), 'explanation' (breve explicação).";
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
            $alternatives = json_encode($question->alternatives);
            $correctAnswer = $question->correct_answer;

            $userAnswer = $simulation->answers()->where('question_id', $question->id)->first();
            $userAnswerText = $userAnswer ? $userAnswer->user_answer : 'Não respondida';

            $baseContext = "Você é um professor particular explicando uma questão de prova.\n";
            $baseContext .= "Questão: $questionText\n";
            $baseContext .= "Alternativas: $alternatives\n";
            $baseContext .= "Resposta Correta: $correctAnswer\n";
            $baseContext .= "Resposta do Aluno: $userAnswerText\n\n";
            $baseContext .= "Histórico da conversa:\n";

            foreach ($history as $msg) {
                $role = $msg['role'] === 'user' ? 'Aluno' : 'Professor';
                $baseContext .= "$role: {$msg['message']}\n";
            }

            $baseContext .= "Aluno: $userMessage\n";
            $baseContext .= "Professor (responda de forma concisa e didática):";

            $result = $this->callAI($provider, $apiKey, $baseContext, $simulation->user_id);
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
            $alternatives = json_encode($question->alternatives);
            $correctAnswer = $question->correct_answer;

            $baseContext = "Você é um professor particular explicando uma questão de prova.\n";
            $baseContext .= "Questão: $questionText\n";
            $baseContext .= "Alternativas: $alternatives\n";
            $baseContext .= "Resposta Correta: $correctAnswer\n";
            $baseContext .= "Resposta Escolhida pelo Aluno: $userAnswer\n\n";
            $baseContext .= "Histórico da conversa:\n";

            foreach ($history as $msg) {
                $role = ($msg['role'] ?? 'user') === 'user' ? 'Aluno' : 'Professor';
                $text = $msg['message'] ?? $msg['content'] ?? '';
                $baseContext .= "$role: $text\n";
            }

            $baseContext .= "Aluno: $userMessage\n";
            $baseContext .= "Professor (responda de forma concisa e didática):";

            $result = $this->callAI($provider, $apiKey, $baseContext);
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
     * Sanitizes AI response by stripping markdown and extracting valid JSON.
     */
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

    /**
     * Persists AI transaction logs for SRE monitoring and cost management.
     */
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
}