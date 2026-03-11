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
    private const SYSTEM_PROMPT_ESSAY_EVALUATOR = <<<EOT
Você é um avaliador técnico e reescritor profissional de redações (ENEM e Concurso Público).

⚠️ REGRA ABSOLUTA:
Você DEVE responder exclusivamente com UM JSON válido.
Não escreva comentários.
Não escreva markdown.
Não escreva texto fora do JSON.
Não inclua explicações antes ou depois.

========================
OBJETIVO DO SISTEMA
========================
1) Avaliar a redação conforme o TEMA_OFICIAL.
2) Detectar fuga ao tema com rigor máximo.
3) Garantir que "improved_version" NUNCA esteja vazio.
4) Se houver fuga ao tema, a nota final DEVE ser 0 obrigatoriamente.

========================
ENTRADAS (fornecidas pelo sistema)
========================
TIPO: {type}
TEMA_OFICIAL: {topic}
REDACAO_USUARIO: {essay}

========================
DEFINIÇÃO DETERMINÍSTICA DE FUGA AO TEMA
========================
off_topic = true SOMENTE se ocorrer condição CLARA e EVIDENTE:

1) O assunto CENTRAL da redação for completamente diferente do TEMA_OFICIAL.
2) O texto ignora completamente o recorte temático exigido.
3) A tese e os argumentos não têm qualquer relação com o TEMA_OFICIAL.

off_topic = false quando:
- O aluno aborda o tema com alguma relação, mesmo que superficial.
- O texto menciona o tema e desenvolve uma perspectiva sobre ele.
- Há tratamento parcial do tema mas reconhecível.

Em caso de dúvida, prefira off_topic = false e avalie normalmente.
Só marque off_topic = true quando a fuga for inequívoca e evidente.

========================
REGRAS OBRIGATÓRIAS
========================

SE off_topic = true:
- overall_score = 0
- c1 = 0, c2 = 0, c3 = 0, c4 = 0, c5 = 0
- competence_feedback: preencha cada campo com "Fuga ao tema detectada."
- summary: explique brevemente que houve fuga ao tema e que a nota foi zerada
- "improved_version" deve ser uma NOVA redação escrita DO ZERO
- NÃO reutilize trechos do texto do usuário
- Baseie-se EXCLUSIVAMENTE no TEMA_OFICIAL
- ENEM: entre 1500 e 3000 caracteres, com proposta de intervenção completa
- CONCURSO: texto formal, coeso e completo

SE off_topic = false:
- Avalie normalmente
- ENEM: cada competência 0 a 200
- overall_score = soma exata das competências
- Deve ser múltiplo de 40
- "improved_version" deve ser uma versão MELHORADA do texto do usuário
- Corrigir gramática, coesão e aprofundar argumentos

========================
REGRA CRÍTICA DE NÃO-VAZIO
========================
"improved_version" NUNCA pode ser:
- null
- ""
- texto com menos de 200 caracteres
- texto irrelevante

Se por qualquer motivo não conseguir melhorar o texto,
você DEVE gerar uma redação nova adequada ao TEMA_OFICIAL.

Para ENEM:
- Mínimo recomendado: 1500 caracteres.
Se ficar menor que isso, reescreva até atingir extensão adequada.

========================
REGRAS PARA summary E competence_feedback
========================
"summary" DEVE ser:
- Um parágrafo narrativo de 2 a 4 frases
- Tom profissional, analítico e humano, como um avaliador inteligente
- Reconhecer pontos positivos antes de apontar melhorias
- Referir-se diretamente ao desempenho DESTA redação específica
- Não ser uma lista de recomendações frias
- Não ser genérico
- Exemplo de tom: "Você demonstrou compreensão clara do tema e construiu uma argumentação que sustenta bem a tese central. No entanto, a proposta de intervenção ainda carece de especificidade quanto a agente, ação e meios."

"competence_feedback" DEVE conter:
- Uma única frase por competência
- Específica para o desempenho DESTA redação
- Analisa o porquê da nota atribuída, não explica o conceito da competência
- NÃO usar linguagem genérica como "a competência foi avaliada com base em..."
- Exemplo: c1: "Há bom domínio da norma culta, com pequenos desvios gramaticais isolados."
- Exemplo: c2: "O tema foi abordado corretamente, mas o repertório poderia ser mais produtivo."

========================
VALIDAÇÃO INTERNA OBRIGATÓRIA
========================
Antes de finalizar o JSON:
1) Confirme se a tese responde diretamente ao TEMA_OFICIAL.
2) Confirme se improved_version está preenchido e coerente.
3) Confirme se overall_score é coerente com as competências.
4) Se off_topic=true, confirme que todos os scores são 0.
5) Confirme que summary é um parágrafo narrativo (não uma lista).
6) Confirme que cada campo de competence_feedback é específico desta redação.

Somente então gere o JSON final.

========================
FORMATO DE SAÍDA (EXATO)
========================

{
  "type": "ENEM|CONCURSO",
  "off_topic": true|false,
  "off_topic_reason": "explicação objetiva",
  "overall_score": number,
  "competence_scores": {
    "c1": number,
    "c2": number,
    "c3": number,
    "c4": number,
    "c5": number
  },
  "competence_feedback": {
    "c1": "frase única explicando o porquê da nota em C1 para esta redação",
    "c2": "frase única explicando o porquê da nota em C2 para esta redação",
    "c3": "frase única explicando o porquê da nota em C3 para esta redação",
    "c4": "frase única explicando o porquê da nota em C4 para esta redação",
    "c5": "frase única explicando o porquê da nota em C5 para esta redação"
  },
  "summary": "parágrafo narrativo analítico de 2 a 4 frases sobre o desempenho geral",
  "strengths": ["item1", "item2", "item3"],
  "weaknesses": ["item1", "item2", "item3"],
  "actionable_feedback": [
    "ação prática 1",
    "ação prática 2",
    "ação prática 3"
  ],
  "improved_version": "texto completo aqui"
}

Não omita nenhuma chave.
Não escreva nada fora desse JSON.
EOT;

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
        try {
            return ApiKey::getKeyForCapability($capability) !== null;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Erro ao validar chave para $capability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Evaluates question difficulty using the best available provider with failover.
     */
    public function evaluateQuestionDifficulty(\App\Models\Question $question, ?int $userId = null): ?array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_TRIAGE)) {
            Log::warning('AI Difficulty Evaluation failed: No active API key for Triage.');
            return null;
        }

        return $this->executeWithFailover(ApiKey::CAPABILITY_TRIAGE, function ($apiKey) use ($question, $userId) {
            $provider = $apiKey->provider;

            $prompt = $this->promptService->get('question_difficulty_evaluator', [
                'question_text' => $question->statement,
                // alternativesAsMap(): retorna ['A'=>'texto', 'B'=>'texto'...]
                // Substitui json_encode($question->alternatives) que serializava
                // a Collection de objetos QuestionAlternative — confuso para a IA.
                'alternatives' => json_encode($question->alternativesAsMap())
            ]);

            $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_TRIAGE);
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
    protected function callAI(string $provider, ApiKey $apiKey, string $prompt, ?int $userId = null, ?string $module = null): array
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
            $cost = $this->telemetryService->logRequest($apiKey, $prompt, $result, $executionTime, $userId, null, $module);

            $result['estimated_cost'] = $cost;
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
                $this->telemetryService->logRequest($apiKey, $prompt, ['content' => ['error' => $e->getMessage()], 'usage' => []], $executionTime, $userId, null, $module);
            }

            throw $e;
        }
    }

    /**
     * Orchestrates streaming AI calls. Yields chunks as they arrive.
     */
    protected function callAIStream(string $provider, ApiKey $apiKey, string $prompt, ?int $userId = null, ?string $module = null): \Generator
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

            $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];
            foreach ($stream as $chunk) {
                if (is_array($chunk) && isset($chunk['usage'])) {
                    $usage = $chunk['usage'];
                    continue;
                }
                $fullText .= $chunk;
                yield $chunk;
            }

            $executionTime = microtime(true) - $startTime;
            $this->telemetryService->logRequest($apiKey, $prompt, [
                'content' => $fullText,
                'usage' => $usage
            ], $executionTime, $userId, null, $module);

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
            if ($statusCode === 429 && $provider === 'openai') {
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
            ->timeout(300)
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
            $finishReason = $data['choices'][0]['finish_reason'] ?? 'unknown';
            Log::error('[callOpenAI] Sanitization FAILED. DATA DUMP:', [
                'length' => strlen($content),
                'finish_reason' => $finishReason,
                'start' => substr($content, 0, 500),
                'end' => substr($content, -500),
            ]);
            throw new \Exception("OpenAI retornou resposta não-parseável (finish_reason={$finishReason}, length=" . strlen($content) . "). Possível truncamento — reduza o chunk_size.");
        }

        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $totalTokens = $usage['total_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        if ($outputTokens <= 0 && $totalTokens > 0) {
            $outputTokens = max(0, $totalTokens - $inputTokens);
        }

        if ($outputTokens <= 0 && !empty($content)) {
            $outputTokens = $this->telemetryService->estimateTokens(is_string($content) ? $content : json_encode($content));
        }

        return [
            'content' => $json,
            'usage' => [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens > 0 ? $totalTokens : ($inputTokens + $outputTokens),
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
                'stream_options' => ['include_usage' => true]
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

                if (isset($json['usage'])) {
                    yield [
                        'usage' => [
                            'input_tokens' => $json['usage']['prompt_tokens'] ?? 0,
                            'output_tokens' => $json['usage']['completion_tokens'] ?? 0,
                            'total_tokens' => $json['usage']['total_tokens'] ?? 0,
                        ]
                    ];
                }
            }
        }
    }

    protected function readLine($body): string
    {
        return \GuzzleHttp\Psr7\Utils::readLine($body);
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
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 65536,
            ]
        ];

        $decryptedKey = $apiKey->decrypted_key;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$decryptedKey}";

        $response = Http::timeout(300)
            ->connectTimeout(15)
            ->withoutVerifying()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if ($response->failed()) {
            throw new \Exception("Gemini API Error: " . $response->body() . " (Status Code: " . $response->status() . ")");
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        $finishReason = $data['candidates'][0]['finishReason'] ?? 'unknown';

        // Log detalhado para depurar truncamento e formato
        Log::info("[GEMINI] Response audit", [
            'length' => strlen($text),
            'finish_reason' => $finishReason,
            'start' => substr($text, 0, 1000),
            'end' => substr($text, -1000),
        ]);
        file_put_contents(storage_path('logs/last_full_ai_response.txt'), $text);

        $json = $this->responseSanitizer->sanitize($text);

        if (empty($json)) {
            throw new \Exception("Gemini retornou resposta não-parseável (finishReason={$finishReason}, length=" . strlen($text) . "). Possível truncamento — reduza o chunk_size.");
        }

        $usageMeta = $data['usageMetadata'] ?? [];

        $inputTokens = $usageMeta['promptTokenCount'] ?? 0;
        $totalTokens = $usageMeta['totalTokenCount'] ?? 0;
        $outputTokens = $usageMeta['candidatesTokenCount'] ?? $usageMeta['candidateTokenCount'] ?? 0;

        if ($outputTokens <= 0 && $totalTokens > 0) {
            $outputTokens = max(0, $totalTokens - $inputTokens);
        }

        if ($outputTokens <= 0 && !empty($text)) {
            $outputTokens = $this->telemetryService->estimateTokens($text);
        }

        return [
            'content' => $json,
            'usage' => [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $totalTokens > 0 ? $totalTokens : ($inputTokens + $outputTokens),
            ]
        ];
    }

    /**
     * Gera um embedding usando o Gemini text-embedding-004
     */
    public function generateEmbedding(string $text, ?int $userId = null): ?array
    {
        $startTime = microtime(true);
        try {
            // Busca uma chave com a capacidade específica de embedding
            $apiKeyModel = ApiKey::getKeyForCapability(ApiKey::CAPABILITY_EMBEDDING, 'gemini');

            if (!$apiKeyModel) {
                // Fallback para qualquer chave gemini se não houver uma específica
                $apiKeyModel = ApiKey::getActiveKeyForProvider('gemini');
            }

            if (!$apiKeyModel) {
                Log::warning('Nenhuma chave ativa para embeddings.', ['provider' => 'gemini']);
                return null;
            }

            $apiKey = $apiKeyModel->decrypted_key;
            // Usando v1beta e gemini-embedding-001 que está disponível para esta conta
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

            $payload = [
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ];

            $response = Http::timeout(5)->post($url, $payload);
            $executionTime = microtime(true) - $startTime;

            if ($response->successful()) {
                $data = $response->json();
                $vector = $data['embedding']['values'] ?? null;

                if ($vector) {
                    // Log telemetry for Admin Visibility
                    $this->telemetryService->logRequest(
                        $apiKeyModel,
                        $text,
                        ['content' => '[VECTOR DATA]', 'usage' => ['total_tokens' => $this->telemetryService->estimateTokens($text)]],
                        $executionTime,
                        $userId,
                        null,
                        'embedding',
                        'gemini-embedding-001'
                    );
                }

                return $vector;
            }

            Log::error('Erro ao chamar API de Embedding.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => str_replace($apiKey, 'HIDDEN', $url)
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exceção ao gerar Embedding.', ['error' => $e->getMessage()]);
            return null;
        }
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
                'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 65536]
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

                if (isset($json['usageMetadata'])) {
                    yield [
                        'usage' => [
                            'input_tokens' => $json['usageMetadata']['promptTokenCount'] ?? 0,
                            'output_tokens' => $json['usageMetadata']['candidatesTokenCount'] ?? 0,
                            'total_tokens' => $json['usageMetadata']['totalTokenCount'] ?? 0,
                        ]
                    ];
                }
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

            $recentTitles = [];
            if ($userId) {
                $recentTitles = \App\Models\Essay::where('user_id', $userId)
                    ->whereNotNull('title')
                    ->latest()
                    ->take(5)
                    ->pluck('title')
                    ->filter()
                    ->toArray();
            }

            $rules = "";
            if (!empty($recentTitles)) {
                $rules .= "- MÁXIMA IMPORTÂNCIA: O usuário já fez redações sobre estes temas recentes: [" . implode(" | ", $recentTitles) . "].\n";
                $rules .= "- É OBRIGATÓRIO escolher um tema COMPLETAMENTE DIFERENTE dos listados acima.\n\n";
            }

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

            if (blank($prompt)) {
                \Log::warning('System prompt missing for essay_topic_generator. Using fallback.');
                $prompt = "Você é um especialista em elaboração de temas de redação no padrão ENEM e concursos públicos brasileiros. Gere um tema atual, relevante, claro e desafiador. Retorne APENAS um objeto JSON válido com: { \"title\": \"Titulo\", \"description\": \"Texto\" }.";
            }

            // FIX: Ensure the AI ALWAYS outputs strict JSON containing 'title' and 'description' to avoid parsing errors
            $prompt .= "\n\n⚠️ REGRA CRÍTICA ABSOLUTA: A sua resposta DEVE ser EXCLUSIVAMENTE um objeto JSON válido. NÃO inclua saudações, reflexões ou qualquer outro texto antes ou depois do JSON. O JSON OBRIGATORIAMENTE deve conter exatas duas chaves: \"title\" e \"description\". Exemplo do formato exigido:\n{\n  \"title\": \"O título do tema gerado\",\n  \"description\": \"O texto motivador completo ou descrição do tema\"\n}";

            $maxAttempts = 2;
            $content = null;
            $requestUuid = \Illuminate\Support\Str::uuid()->toString();

            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_ESSAYS);
                    $apiKey->incrementUsage();

                    $content = $result['content'];
                    if (isset($content['error'])) {
                        throw new \Exception($content['error']);
                    }
                    if (!isset($content['title']) || !isset($content['description'])) {
                        throw new \Exception('Formato de resposta inválido do Xavier.');
                    }

                    // Test Similarity against recents
                    $isTooSimilar = false;
                    foreach ($recentTitles as $recent) {
                        similar_text(strtolower($content['title']), strtolower($recent), $percent);
                        if ($percent > 70) {
                            $isTooSimilar = true;
                            \Log::info("Theme too similar ({$percent}%) to recent '{$recent}', retrying...");
                            break;
                        }
                    }

                    if (!$isTooSimilar || $attempt === $maxAttempts) {
                        break;
                    }
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                    $isRetryable = str_contains($errorMessage, '429') ||
                        str_contains($errorMessage, 'timeout') ||
                        str_contains($errorMessage, 'Status Code: 5') ||
                        str_contains($errorMessage, 'Connection timed out');

                    Log::warning("[GenerateEssayTopic] Attempt {$attempt} failed", [
                        'request_id' => $requestUuid,
                        'user_id' => $userId,
                        'provider' => $provider,
                        'error' => $errorMessage,
                        'retryable' => $isRetryable
                    ]);

                    if ($isRetryable && $attempt < $maxAttempts) {
                        // Backoff curto
                        usleep($attempt === 1 ? 300000 : 800000); // 300ms, 800ms
                        continue;
                    }

                    // Falha definitiva
                    Log::error("[GenerateEssayTopic] Final failure", [
                        'request_id' => $requestUuid,
                        'user_id' => $userId,
                        'provider' => $provider,
                        'error' => $errorMessage
                    ]);

                    throw new \Exception(json_encode([
                        'code' => 'AI_TOPIC_GENERATION_FAILED',
                        'message' => 'Não foi possível gerar um tema agora. Tente novamente em instantes.',
                        'request_id' => $requestUuid
                    ]));
                }
            }

            return $content;
        });
    }

    public function detectOffTopic(string $title, string $content, string $type, ?int $userId = null): array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_ESSAYS)) {
            // Fail open: if key is missing, let the main evaluator grade it
            return ['off_topic' => false, 'reason' => 'API não disponível para pré-verificação. Avaliação principal decidirá.'];
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_ESSAYS, function ($apiKey) use ($title, $content, $type, $userId) {
                $provider = $apiKey->provider;
                $prompt = $this->buildOffTopicPrompt($title, $content, $type);

                $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_ESSAYS);
                $apiKey->incrementUsage();

                $responseContent = $result['content'];

                // Explicit strict boolean conversion
                $isOffTopic = isset($responseContent['off_topic']) && (
                    $responseContent['off_topic'] === true ||
                    $responseContent['off_topic'] === 'true' ||
                    $responseContent['off_topic'] === 1
                );

                return [
                    'off_topic' => $isOffTopic,
                    'reason' => $responseContent['reason'] ?? ($isOffTopic ? 'Fuga ao tema detectada pela IA.' : 'Indeterminado')
                ];
            });
        } catch (\Exception $e) {
            Log::error('OffTopic Detection failed (Fail-Open applied)', ['error' => $e->getMessage(), 'title' => $title]);
            // Fail open: if error occurs parsing the off-topic boolean, don't punish the user
            return ['off_topic' => false, 'reason' => 'Erro técnico na pré-verificação de tema. Avaliação principal ditará o resultado.'];
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

                $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_ESSAYS);
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
        $prompt = self::SYSTEM_PROMPT_ESSAY_EVALUATOR;
        $prompt = str_replace('{type}', strtoupper($type), $prompt);
        $prompt = str_replace('{topic}', $title, $prompt);
        $prompt = str_replace('{essay}', $content, $prompt);

        return $prompt;
    }

    protected function buildOffTopicPrompt(string $title, string $content, string $type): string
    {
        $prompt = $this->promptService->get('essay_offtopic_detector', [
            'essay_type' => $type,
            'essay_title' => $title,
            'essay_content' => $content,
        ]);

        if (blank($prompt) || trim($prompt) === '') {
            Log::warning('System prompt missing for essay_offtopic_detector. Using fallback.');
            $prompt = <<<EOT
Você é um avaliador rigoroso. Verifique se a redação abaixo foge do tema.
Responda EXCLUSIVAMENTE com um JSON no formato: {"off_topic": true/false, "reason": "motivo em uma frase"}.

TEMA OFICIAL: {essay_title}
REDAÇÃO DO USUÁRIO: {essay_content}

REGRA: Só marque off_topic = true se o aluno ignorar COMPLETAMENTE o tema. Se houver qualquer relação, mesmo que superficial, off_topic = false. Em caso de dúvida, false.
EOT;
            $prompt = str_replace('{essay_type}', $type, $prompt);
            $prompt = str_replace('{essay_title}', $title, $prompt);
            $prompt = str_replace('{essay_content}', $content, $prompt);
        }

        return $prompt;
    }

    public function generateImprovedEssayForTopic(string $topic, string $type): string
    {
        $fallbackPremium = "O tema '{$topic}' exige uma abordagem estruturada. Comece com uma tese clara na introdução, desenvolva argumentos sólidos baseados em fatos ou citações nos parágrafos de desenvolvimento e, no caso do ENEM, finalize com uma proposta de intervenção detalhada que combata a causa do problema respeitando os direitos humanos.";

        if (!$this->hasActiveKey(ApiKey::CAPABILITY_ESSAYS)) {
            return $fallbackPremium;
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_ESSAYS, function ($apiKey) use ($topic, $type, $fallbackPremium) {
                $provider = $apiKey->provider;

                $lengthInstruction = $type === 'enem'
                    ? "O texto DEVE ter no mínimo 1500 caracteres e no máximo 3000 caracteres, contendo introdução, desenvolvimento e proposta de intervenção completa com 5 elementos."
                    : "O texto DEVE ser formal, coeso, dissertativo-argumentativo e completo.";

                $prompt = "Você é um professor especialista. O aluno fugiu do tema ou o sistema precisa de um exemplo. Baseado APENAS no tema oficial: '{$topic}', escreva uma redação EXEMPLAR completa (adequada para '{$type}'). A resposta DEVE ser apenas o texto da redação, sem títulos, sem introduções explicativas e sem aspas. {$lengthInstruction}";

                $result = $this->callAI($provider, $apiKey, $prompt, null, ApiKey::CAPABILITY_ESSAYS);
                $apiKey->incrementUsage();

                $generated = trim((string) ($result['content']['text'] ?? $result['content'] ?? ''));

                // Validate if it's not JSON (sometimes AI returns JSON even when told not to)
                if (str_starts_with($generated, '{')) {
                    $sanitized = json_decode($generated, true);
                    $generated = $sanitized['text'] ?? $sanitized['improved_version'] ?? $generated;
                }

                if (!empty($generated) && strlen($generated) > 200) {
                    return $generated;
                }

                return $fallbackPremium;
            });
        } catch (\Exception $e) {
            Log::warning("[AIService] Fallback rewrite generation failed: " . $e->getMessage());
            return $fallbackPremium;
        }
    }

    public function generateQuestions(string $subject, int $quantity = 1, array $context = [], ?int $userId = null): array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_QUESTIONS)) {
            return [];
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_QUESTIONS, function ($apiKey) use ($subject, $quantity, $context, $userId) {
                $provider = $apiKey->provider;
                $prompt = $this->buildQuestionGenerationPrompt($subject, $quantity, $context);
                $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_QUESTIONS);
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
                ]), $simulation->user_id, ApiKey::CAPABILITY_QUESTIONS);
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
                $stream = $this->callAIStream($provider, $apiKey, $prompt, $simulation->user_id, ApiKey::CAPABILITY_QUESTIONS);
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
                ]), $userId, ApiKey::CAPABILITY_QUESTIONS);
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
        // 1. Try Chat Tutor capability
        $capability = ApiKey::CAPABILITY_CHAT_TUTOR;
        $keys = ApiKey::getKeysForCapability($capability);

        if ($keys->isEmpty()) {
            yield "Desculpe, o sistema de IA está offline no momento.";
            return;
        }

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
                $stream = $this->callAIStream($provider, $apiKey, $prompt, $userId, $capability);

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

    /**
     * Generates JSON via AI for batch triage operations.
     * Uses CAPABILITY_TRIAGE routing to ensure only triage-configured keys are used.
     * Writes the active key info to cache so the frontend can display it.
     */
    public function generateJsonForBatch(string $prompt, ?string $batchId = null, ?int $userId = null): array
    {
        return $this->executeWithFailover(ApiKey::CAPABILITY_TRIAGE, function ($apiKey) use ($prompt, $batchId, $userId) {
            // Write active key info to cache so frontend can display it
            if ($batchId) {
                $keyName = $apiKey->vault?->name ?? ("Chave #{$apiKey->id}");
                Cache::put("batch_active_key_{$batchId}", [
                    'name' => $keyName,
                    'provider' => $apiKey->effective_provider,
                    'model' => $apiKey->preferred_model,
                ], now()->addHours(2));
            }

            $result = $this->callAI($apiKey->effective_provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_TRIAGE);

            $decoded = $this->responseSanitizer->sanitize($result['content']);

            if (empty($decoded)) {
                Log::warning('[AIService::generateJsonForBatch] responseSanitizer returned empty — raw content snippet:', [
                    'snippet' => substr($result['content'] ?? '', 0, 300),
                ]);
            }

            return [
                'data' => $decoded,
                'usage' => $result['usage'] ?? ['input_tokens' => 0, 'output_tokens' => 0],
                'estimated_cost' => $result['estimated_cost'] ?? 0
            ];
        });
    }

    public function generateJson(string $prompt, ?string $model = null, ?int $userId = null): array
    {
        $provider = $model ? $this->getProviderForModel($model) : null;

        return $this->executeWithFailover(ApiKey::CAPABILITY_GENERAL, function ($apiKey) use ($prompt, $model, $userId) {
            $provider = $apiKey->provider;
            if ($model) {
                $apiKey->preferred_model = $model;
            }

            $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_GENERAL);

            // Decodifica o JSON retornado pela IA (pode vir como string bruta com markdown)
            $decoded = $this->responseSanitizer->sanitize($result['content']);

            if (empty($decoded)) {
                Log::warning('[AIService::generateJson] responseSanitizer returned empty — raw content snippet:', [
                    'snippet' => substr($result['content'] ?? '', 0, 300),
                ]);
            }

            return [
                'data' => $decoded,
                'usage' => $result['usage'] ?? ['input_tokens' => 0, 'output_tokens' => 0],
                'estimated_cost' => $result['estimated_cost'] ?? 0
            ];
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

    public function generateStudyPlan(array $stats, array $input, ?int $userId = null): ?array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_STUDY_PLANS)) {
            return null;
        }

        // Fetch User and additional metrics
        $user = $userId ? \App\Models\User::find($userId) : null;
        if ($user) {
            $totalAttempts = \App\Models\UserTopicStat::where('user_id', $user->id)->sum('attempts');
            $totalCorrect = \App\Models\UserTopicStat::where('user_id', $user->id)->sum('correct');
            $stats['overall_accuracy'] = $totalAttempts > 0 ? round(($totalCorrect / $totalAttempts) * 100, 1) : 0;

            // Average time per question (from simulations)
            $sims = $user->simulations()->where('status', 'finished')->where('time_spent', '>', 0)->latest()->take(10)->get();
            if ($sims->isNotEmpty()) {
                $totalTime = $sims->sum('time_spent');
                $totalQs = $sims->sum('questions_count');
                $stats['avg_seconds_per_question'] = $totalQs > 0 ? round($totalTime / $totalQs, 1) : 0;
            }

            // Recent Essay
            $lastEssay = $user->essays()->where('status', 'corrected')->latest()->first();
            if ($lastEssay) {
                $stats['last_essay_score'] = $lastEssay->score;
            }
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_STUDY_PLANS, function ($apiKey) use ($stats, $input, $userId) {
                $provider = $apiKey->effective_provider;
                $prompt = $this->promptService->get('study_plan_generator', [
                    'stats' => json_encode($stats),
                    'input' => json_encode($input)
                ]);

                $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_STUDY_PLANS);
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
            $attempts = 0;
            $maxAttempts = 3;

            while ($attempts < $maxAttempts) {
                try {
                    return $closure($apiKey);
                } catch (\Exception $e) {
                    $attempts++;
                    $lastException = $e;

                    if ($this->isRetriableError($e)) {
                        if ($attempts < $maxAttempts) {
                            $sleepSeconds = pow(2, $attempts); // 2s, 4s
                            Log::warning("AI Provider {$apiKey->provider} hit temporary error, retrying in {$sleepSeconds}s...", [
                                'attempt' => $attempts,
                                'key_id' => $apiKey->id,
                                'error' => $e->getMessage()
                            ]);
                            sleep($sleepSeconds);
                            continue;
                        }

                        Log::warning("AI Provider failed after {$maxAttempts} attempts, failing over to next priority...", [
                            'capability' => $capability,
                            'key_id' => $apiKey->id,
                            'error' => $e->getMessage()
                        ]);

                        $this->banKeyTemporarily($apiKey, $e);
                        break; // Move out of the while loop to try the next Key in foreach
                    }

                    // Se for erro na formatação do prompt (ex. 400 Bad Request), jogar pra cima pois tentamos e fomos rejeitados na raiz.
                    throw $e;
                }
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

        // 429 = Rate Limit / Quota Exceeded — tentar com outra chave
        if (str_contains($message, '429') || str_contains($message, 'quota exceeded') || str_contains($message, 'rate limit')) {
            return true;
        }

        // 500, 502, 503, 504 = Server error do Google/OpenAI.
        if (
            str_contains($message, '500') ||
            str_contains($message, '502') ||
            str_contains($message, '503') ||
            str_contains($message, '504') ||
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

    public function interpretSearchPrompt(string $userPrompt, array $filterOptions, ?int $userId = null): ?array
    {
        if (!$this->hasActiveKey(ApiKey::CAPABILITY_SEARCH)) {
            return null;
        }

        try {
            return $this->executeWithFailover(ApiKey::CAPABILITY_SEARCH, function ($apiKey) use ($userPrompt, $filterOptions, $userId) {
                $provider = $apiKey->provider;
                $aiName = \App\Models\Setting::where('key', 'ai_name')->value('value') ?? 'Xavier';

                // LÓGICA DE MAPEAMENTO (Tema vs Assunto):
                // O Xavier deve mapear a coluna 'topic' do JSON resultante para:
                // - 'theme' (no banco) se o tipo for 'enem'
                // - 'topic' (no banco) se o tipo for 'concurso'
                $prompt = $this->promptService->get('ai_search_interpreter', [
                    'user_prompt' => $userPrompt,
                    'filter_options' => json_encode($filterOptions)
                ], "Você é o {$aiName}, um Agente de Busca de alta precisão. Sua missão é converter a frase do usuário em um JSON de filtros ESTRITAMENTE baseados nas opções fornecidas.

### REGRAS DE OURO (NÃO NEGOCIÁVEIS):
1. **USO OBRIGATÓRIO DE IDS**: Para os campos 'subject' e 'topic', você DEVE retornar o ID (número ou string curta) encontrado no JSON de opções válidas. NUNCA retorne o nome amigável (ex: retornar '12' em vez de 'Geografia').
2. **PROIBIDO FILTROS FANTASMAS**: Se o usuário não mencionou o ANO, o campo 'year' DEVE ser string vazia (\"\"). Se ele não mencionou a dificuldade, 'difficulty' DEVE ser \"\". NUNCA invente '2024' ou qualquer outro valor por conta própria.
3. **ECONOMIA DE KEYWORDS**: O campo 'keyword' deve conter APENAS termos que não foram capturados como matéria ou assunto. Se já mapeou o assunto, deixe 'keyword' vazio (\"\").
4. **VALORES TÉCNICOS**:
   - Tipo DEVE ser: \"enem\", \"concurso\" ou vazio (\"\") se o usuário não especificar.
   - Dificuldade DEVE ser: \"easy\", \"medium\" ou \"hard\".
   - Status DEVE ser: \"unanswered\" ou \"answered\".

### EXEMPLO DE SUCESSO:
**Input do Usuário:** \"questões de geografia\"
**Opções Válidas:** {\"subjects\":[{\"id\":45, \"name\":\"Geografia\"}], ...}
**Output Correto:**
{
  \"type\": \"\",
  \"subject\": \"45\",
  \"topic\": \"\",
  \"difficulty\": \"\",
  \"year\": \"\",
  \"keyword\": \"\",
  \"suggestion_tip\": \"Encontrei questões de Geografia para você.\",
  \"suggestions\": []
}

### DADOS PARA PROCESSAR AGORA:
Busca do aluno: '{user_prompt}'
Opções válidas (JSON): {filter_options}

RETORNE APENAS O JSON:");

                $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_SEARCH);
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

    /**
     * Sends a raw text prompt to any available LLM and returns the raw string response.
     * Used by ConceptExtractionJob and other system jobs that need plain-text output (not JSON).
     *
     * @param  string   $prompt   The full prompt to send
     * @param  int|null $userId   Optional user ID for telemetry attribution
     * @return string             Raw LLM response text
     * @throws \Exception         If no API key is available or all providers fail
     */
    public function sendRawPrompt(string $prompt, ?int $userId = null): string
    {
        return $this->executeWithFailover(ApiKey::CAPABILITY_GENERAL, function ($apiKey) use ($prompt, $userId) {
            $provider = $apiKey->effective_provider;

            $response = match ($provider) {
                'gemini' => $this->callGeminiRaw($apiKey, $prompt),
                'openai' => $this->callOpenAIRaw($apiKey, $prompt),
                default  => throw new \Exception("Provider not supported for raw prompts: {$provider}"),
            };

            $apiKey->incrementUsage();

            $this->telemetryService->logRequest(
                $apiKey,
                $prompt,
                ['content' => ['text' => $response], 'usage' => ['total_tokens' => $this->telemetryService->estimateTokens($prompt . $response)]],
                0,
                $userId,
                null,
                'concept_extraction'
            );

            return $response;
        });
    }

    /**
     * Calls Gemini and returns the raw text content (not JSON-parsed).
     */
    private function callGeminiRaw(ApiKey $apiKey, string $prompt): string
    {
        $model        = $apiKey->preferred_model;
        $decryptedKey = $apiKey->decrypted_key;
        $url          = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$decryptedKey}";

        $response = Http::timeout(60)
            ->connectTimeout(10)
            ->withoutVerifying()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents'        => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1024],
            ]);

        if ($response->failed()) {
            throw new \Exception("Gemini raw call failed: " . $response->body() . " (Status: " . $response->status() . ")");
        }

        return $response->json('candidates.0.content.parts.0.text') ?? '';
    }

    /**
     * Calls OpenAI and returns the raw text content (not JSON-parsed).
     */
    private function callOpenAIRaw(ApiKey $apiKey, string $prompt): string
    {
        $url   = 'https://api.openai.com/v1/chat/completions';
        $model = $apiKey->preferred_model ?? 'gpt-4o';

        $response = Http::withToken($apiKey->decrypted_key)
            ->connectTimeout(10)
            ->timeout(60)
            ->post($url, [
                'model'       => $model,
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.3,
                'max_tokens'  => 1024,
            ]);

        if ($response->failed()) {
            throw new \Exception("OpenAI raw call failed: " . $response->body() . " (Status: " . $response->status() . ")");
        }

        return $response->json('choices.0.message.content') ?? '';
    }
}

