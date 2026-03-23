<?php

namespace App\Services\AI;

use App\Models\ApiKey;
use App\Models\Question;
use App\Exceptions\AIServiceBusyException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\PromptService;
use App\Services\AI\ResponseSanitizer;
use App\Services\AI\AITelemetryService;
use Illuminate\Support\Facades\Redis;

/**
 * AIService - Core service for AI interaction and management.
 */
class AIService
{
    private const SYSTEM_PROMPT_ESSAY_EVALUATOR = <<<EOT
Você é um avaliador de redações de nível elite — corretor de banca especializado, professor doutor em Língua Portuguesa e Produção Textual. Sua avaliação deve ser técnica, rigorosa, imparcial e compatível com bancas reais de alto nível.

⚠️ REGRA ABSOLUTA:
Responda EXCLUSIVAMENTE com UM JSON válido.
Sem comentários, sem markdown, sem texto fora do JSON.

========================
ENTRADAS DO SISTEMA
========================
TIPO: {type}
TEMA_OFICIAL: {topic}
REDACAO_USUARIO: {essay}

========================
DETECÇÃO DE FUGA AO TEMA
========================
off_topic = true quando:
1) O assunto CENTRAL do texto for completamente diferente do TEMA_OFICIAL.
2) O texto ignora totalmente o recorte temático exigido.
3) A tese e os argumentos não têm qualquer relação com o TEMA_OFICIAL.

off_topic = false quando:
- O aluno aborda o tema, mesmo que superficialmente.
- O texto menciona o tema e desenvolve alguma perspectiva sobre ele.

Em caso de dúvida leve, prefira off_topic = false e avalie normalmente.
Só marque off_topic = true quando a fuga for inequívoca e evidente.

SE off_topic = true → OBRIGATÓRIO:
- overall_score = 0
- c1 = 0, c2 = 0, c3 = 0, c4 = 0, c5 = 0
- summary: explicar claramente que houve fuga ao tema e que a nota foi zerada
- improved_version: nova redação escrita DO ZERO, baseada EXCLUSIVAMENTE no TEMA_OFICIAL
  - ENEM: mínimo 1500 caracteres, com proposta de intervenção completa
  - CONCURSO: texto formal, dissertativo, completo

========================
CRITÉRIOS DE AVALIAÇÃO — ENEM
========================
Cada competência: 0 a 200. Total: soma exata das 5 competências (máx 1000).
overall_score = c1 + c2 + c3 + c4 + c5 (DEVE ser múltiplo de 40)

C1 — Domínio da Norma Culta
- Avaliar: gramática, ortografia, acentuação, pontuação, concordância, regência, crase
- Erros recorrentes de vírgula ou concordância → no máximo 100
- Texto com vários erros gramaticais claros → no máximo 80

C2 — Compreensão do Tema e Repertório
- Avaliar: tese clara, repertório sociocultural fundamentado, citações pertinentes
- Repertório vago ou artificial → penalizar fortemente
- Introdução sem tese clara → no máximo 100

C3 — Argumentação
- Avaliar: progressão lógica, causa/consequência, profundidade argumentativa
- Argumentos superficiais, genéricos ou repetitivos → no máximo 120
- Texto com desenvolvimento fraco → no máximo 100

C4 — Coesão e Coerência
- Avaliar: conectivos, parágrafos encadeados, progressão temática, ausência de rupturas
- Conectivos fracos, repetitivos ou mal empregados → penalizar
- Rupturas lógicas visíveis → no máximo 120

C5 — Proposta de Intervenção
- Avaliar: agente + ação + meio + finalidade + detalhamento + respeito aos direitos humanos
- Proposta vaga, sem agente ou sem meio → no máximo 80
- Proposta genérica do tipo "o governo deve conscientizar a população" → máximo 80
- Ausência de proposta clara → 0

ESCALA DE SEVERIDADE ENEM (obrigatória):
- Texto com muitos erros gramaticais e argumentação fraca: 280–440
- Texto mediano, vários erros perceptíveis: 440–600
- Texto razoável, poucos erros mas falhas argumentativas: 600–720
- Texto bom, com falhas pontuais: 720–840
- Texto muito bom, quase sem erros, bem argumentado: 840–920
- Texto excepcional, rigorosamente correto, argumentação de elite: 920–1000

ATENÇÃO: notas 960 ou 1000 são exceções raças. Nunca atribua nota acima de 840 para texto com falhas perceptíveis.

========================
CRITÉRIOS DE AVALIAÇÃO — CONCURSO PÚBLICO
========================
Cada competência: 0 a 20. Total: soma exata das 5 competências (máx 100).
overall_score = c1 + c2 + c3 + c4 + c5

C1 — Domínio da Norma Culta (0–20)
C2 — Clareza e Objetividade (0–20)
C3 — Estrutura Dissertativa (0–20)
C4 — Adequação ao Tema (0–20)
C5 — Coesão e Coerência (0–20)

ESCALA DE SEVERIDADE CONCURSO:
- Texto com erros recorrentes de gramática/pontuação: 30–50
- Texto mediano, escrita razoável mas com falhas: 50–65
- Texto bom, poucos erros: 65–78
- Texto muito bom: 78–88
- Texto excepcional, quase impecável: 88–100

Nota 100 só em texto rigorosamente correto, objetivo, bem estruturado e sem erros.
NUNCA atribua nota próxima de 100 a texto com erros visíveis de gramática ou pontuação.

========================
CORREÇÕES PONTUAIS (obrigatório)
========================
Identifique erros reais no texto do usuário:
- Erros de acentuação
- Erros ortográficos
- Erros gramaticais (concordância, regência)
- Erros de pontuação relevantes (falta de vírgula obrigatória, etc.)
- Construções inadequadas

Para cada erro, gere um objeto com formato:
{ "original": "trecho errado", "correto": "trecho correto", "tipo": "tipo do erro" }

Se genuinamente não houver erros relevantes, use:
correcoes_pontuais = "Não foram identificadas correções pontuais relevantes neste texto. Confira os comentários gerais e a versão melhorada para aprimorar estrutura e clareza."

Não retorne essa mensagem se houver qualquer erro gramatical ou ortográfico visível no texto.

========================
SUMMARY (obrigatório)
========================
Deve ser um parágrafo analítico, técnico e objetivo de 2 a 4 frases.
- Indicar nível de domínio da norma culta
- Indicar qualidade argumentativa
- Apontar o principal problema do texto
- Ser coerente com a nota atribuída
- NÃO ser motivacional ou genérico
- NÃO elogiar texto mediano como bom

========================
VERSÃO MELHORADA (obrigatório)
========================
- Corrigir todos os erros do texto original
- Manter aderência total ao tema
- Melhorar argumentação, gramática e coesão
- Para ENEM: mínimo 1500 caracteres, com proposta de intervenção completa
- Para CONCURSO: texto formal, dissertativo, objetivo

========================
VALIDAÇÃO FINAL OBRIGATÓRIA
========================
Antes de gerar o JSON:
1) A nota é compatível com a qualidade real do texto?
2) O summary é coerente com a nota?
3) improved_version está preenchida e dentro do tema?
4) Se off_topic=true, todos os scores são 0?
5) correcoes_pontuais lista erros reais ou usa a mensagem padrão correta?
6) Para CONCURSO: overall_score ≤ 100?
7) Para ENEM: overall_score ≤ 1000 e múltiplo de 40?

========================
FORMATO DE SAÍDA (EXATO)
========================

{
  "type": "ENEM|CONCURSO",
  "off_topic": true|false,
  "off_topic_reason": "explicação objetiva se off_topic=true, caso contrário null",
  "overall_score": number,
  "competence_scores": {
    "c1": number,
    "c2": number,
    "c3": number,
    "c4": number,
    "c5": number
  },
  "competence_feedback": {
    "c1": "frase técnica e específica explicando a nota atribuída",
    "c2": "frase técnica e específica explicando a nota atribuída",
    "c3": "frase técnica e específica explicando a nota atribuída",
    "c4": "frase técnica e específica explicando a nota atribuída",
    "c5": "frase técnica e específica explicando a nota atribuída"
  },
  "summary": "parágrafo analítico técnico sobre o desempenho real — coerente com a nota",
  "strengths": ["item1", "item2"],
  "weaknesses": ["item1", "item2", "item3"],
  "correcoes_pontuais": [{"original": "...", "correto": "...", "tipo": "..."}] ou "mensagem padrão se sem erros",
  "actionable_feedback": ["ação prática 1", "ação prática 2", "ação prática 3"],
  "improved_version": "texto completo corrigido e aprimorado"
}

Não omita nenhuma chave. Não escreva nada fora desse JSON.
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
     * Checks if at least one provider has an active key for a given capability.
     */
    public function hasActiveKey(?string $capability = null): bool
    {
        // If the circuit breaker is active for this capability, we consider it "no active keys"
        // to trigger a job release/pause before even querying the DB.
        if ($capability && $this->isPaused($capability)) {
            return false;
        }

        try {
            if ($capability) {
                return ApiKey::getKeyForCapability($capability) !== null;
            }
            return ApiKey::where('is_active', true)->where('status', 'online')->exists();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error validating key for $capability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a capability is currently under circuit breaker (no keys available).
     */
    public function isPaused(string $capability): bool
    {
        return Cache::has("ai_circuit_breaker_{$capability}");
    }

    /**
     * Activates the circuit breaker for a specific capability.
     */
    public function pause(string $capability, int $seconds = 300): void
    {
        Log::warning("[AIService] CIRCUIT BREAKER ACTIVE for '$capability'. Pausing for $seconds seconds.");
        Cache::put("ai_circuit_breaker_{$capability}", true, $seconds);
    }

    /**
     * Deactivates the circuit breaker for a specific capability.
     */
    public function resume(string $capability): void
    {
        if ($this->isPaused($capability)) {
            Log::info("[AIService] CIRCUIT BREAKER CLEARED for '$capability'. Resuming operations.");
            Cache::forget("ai_circuit_breaker_{$capability}");
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
    protected function callAI(string $provider, ApiKey $apiKey, string $prompt, ?int $userId = null, ?string $module = null, float $temperature = 0.7): array
    {
        $provider = $apiKey->effective_provider;
        Log::info("DEBUG: Using API Key ID: {$apiKey->id} for provider: {$provider}");
        $startTime = microtime(true);

        try {
            $result = match ($provider) {
                'openai' => $this->callOpenAI($apiKey, $prompt, $temperature),
                'gemini' => $this->callGemini($apiKey, $prompt, $temperature),
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
                try {
                    app(\App\Services\AdminNotificationService::class)->notifyAiError($provider, $apiKey->preferred_model ?? 'unknown', 'Quota Exceeded', "Chave ID: {$apiKey->id}");
                } catch (\Throwable $t) {}
            } elseif (in_array($statusCode, [401, 403])) {
                $apiKey->update(['status' => 'offline']);
                try {
                    app(\App\Services\AdminNotificationService::class)->notifyAiError($provider, $apiKey->preferred_model ?? 'unknown', 'Offline/Invalid Key', "Chave ID: {$apiKey->id}");
                } catch (\Throwable $t) {}
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
                try {
                    app(\App\Services\AdminNotificationService::class)->notifyAiError($provider, $apiKey->preferred_model ?? 'unknown', 'Quota Exceeded (Stream)', "Chave ID: {$apiKey->id}");
                } catch (\Throwable $t) {}
            } elseif (in_array($statusCode, [401, 403])) {
                $apiKey->update(['status' => 'offline']);
                try {
                    app(\App\Services\AdminNotificationService::class)->notifyAiError($provider, $apiKey->preferred_model ?? 'unknown', 'Offline/Invalid Key (Stream)', "Chave ID: {$apiKey->id}");
                } catch (\Throwable $t) {}
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
    protected function callOpenAI(ApiKey $apiKey, string $prompt, float $temperature = 0.7): array
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
                'temperature' => $temperature,
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
    protected function callGemini(ApiKey $apiKey, string $prompt, float $temperature = 0.7): array
    {
        $model = $apiKey->preferred_model;
        
        // Se a chave estiver configurada com um modelo de embedding, ela não serve para geração de texto.
        // Lançamos uma exceção para o executeWithFailover capturar e tentar a próxima chave.
        if (empty($model) || str_contains($model, 'embedding')) {
             throw new \Exception("Chave '" . ($apiKey->name ?? $apiKey->id) . "' configurada com modelo incompatível para texto: {$model}");
        }

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
                'temperature' => $temperature,
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
     * Generates an embedding using Gemini gemini-embedding-001 (3072 dims) with Failover Engine.
     *
     * The $taskType informs Gemini of the embedding purpose, allowing internal 
     * vector optimization for the specific use case:
     *   - 'RETRIEVAL_DOCUMENT': used for document indexing (questions, concepts)
     *   - 'RETRIEVAL_QUERY': used for search — optimized for finding relevant documents
     *
     * @param  string      $text     Text to be embedded
     * @param  int|null    $userId   User ID for telemetry (null for system jobs)
     * @param  string      $taskType Gemini task type: RETRIEVAL_DOCUMENT or RETRIEVAL_QUERY
     * @return array|null  3072-dimensional vector, or null on error
     */
    public function generateEmbedding(string $text, ?int $userId = null, string $taskType = 'RETRIEVAL_DOCUMENT'): ?array
    {
        try {
            // Escolhe a capability baseada no uso:
            // RETRIEVAL_QUERY (Busca em tempo real) -> CAPABILITY_QUERY_EMBEDDING
            // RETRIEVAL_DOCUMENT (Indexação em lote) -> CAPABILITY_EMBEDDING
            $capability = ($taskType === 'RETRIEVAL_QUERY') 
                ? ApiKey::CAPABILITY_QUERY_EMBEDDING 
                : ApiKey::CAPABILITY_EMBEDDING;
            
            // Guard: don't attempt to embed empty strings (Gemini returns 400)
            if (empty(trim($text))) {
                return null;
            }

            return $this->executeWithFailover($capability, function ($apiKeyModel) use ($text, $userId, $taskType) {
                $startTime = microtime(true);
                $apiKey = $apiKeyModel->decrypted_key;
                
                // Gemini Embedding API — gemini-embedding-001 (3072 dims, suporta taskType)
                $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}";

                // O taskType orienta o modelo a gerar vetores otimizados para o cenário específico:
                // - RETRIEVAL_DOCUMENT: vetor "de documento" — captura todo o conteúdo para ser encontrado
                // - RETRIEVAL_QUERY: vetor "de busca" — captura a intenção de busca para encontrar documentos
                $payload = [
                    'content' => [
                        'parts' => [
                            ['text' => $text]
                        ]
                    ],
                    'taskType' => $taskType,
                ];

                // Jitter moved to executeWithFailover to avoid holding locks while sleeping.

                $response = Http::timeout(10)->post($url, $payload);
                $executionTime = microtime(true) - $startTime;

                if ($response->failed()) {
                    $statusCode = $response->status();
                    $errorMessage = $response->body();

                    $apiKeyModel->update([
                        'last_error_message' => "[$statusCode] Quota/Error (Embedding)",
                        'last_error_at' => now(),
                    ]);

                    if ($statusCode === 429) {
                        $apiKeyModel->update(['status' => 'quota_exceeded']);
                        try {
                            app(\App\Services\AdminNotificationService::class)->notifyAiError('gemini', 'gemini-embedding-001', 'Quota Exceeded (Embedding)', "Chave ID: {$apiKeyModel->id}");
                        } catch (\Throwable $t) {}
                    } elseif (in_array($statusCode, [401, 403])) {
                        $apiKeyModel->update(['status' => 'offline']);
                        try {
                            app(\App\Services\AdminNotificationService::class)->notifyAiError('gemini', 'gemini-embedding-001', 'Offline/Invalid Key (Embedding)', "Chave ID: {$apiKeyModel->id}");
                        } catch (\Throwable $t) {}
                    }

                    throw new \Exception("Gemini API Error: " . $errorMessage . " (Status Code: " . $statusCode . ")");
                }

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
                    
                    // Increment usage specifically for stats tracking
                    $apiKeyModel->incrementUsage();
                    
                    return $vector;
                }

                throw new \Exception("Resposta da API de Embedding bem-sucedida, mas vetor vazio ou não encontrado.");
            }, 'gemini');

        } catch (\Exception $e) {
            Log::error("[Xavier][Embedding] Exceção ao gerar Embedding com Failover: {$e->getMessage()}", [
                'taskType' => $taskType,
                'text_preview' => substr($text, 0, 100)
            ]);
            
            // Repropaga a exceção para que o Job possa decidir entre release() ou fail()
            throw $e;
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

                $result = $this->callAI($provider, $apiKey, $prompt, $userId, ApiKey::CAPABILITY_ESSAYS, 0.3);
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
     * Generates multiple embeddings in a single Batch API call.
     * DRRASTICALLY faster for multi-vector systems like Xavier 2.0.
     * 
     * @param  string[]   $texts    Array of text strings to be embedded
     * @param  int|null    $userId   User ID for telemetry
     * @param  string      $taskType Gemini task type (RETRIEVAL_DOCUMENT or RETRIEVAL_QUERY)
     * @return array[] Array of vectors
     */
    public function generateEmbeddingsBatch(array $texts, ?int $userId = null, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        if (empty($texts)) return [];
        
        // Guard: filter out empty strings while maintaining consistency if needed
        // but for now, we just return empty if all are empty or throw if some are empty.
        // Actually, let's just check if THE first one is empty for simplicity in this system
        // which usually embeds a single large block in batch.
        $nonEmptyTexts = array_filter($texts, fn($t) => !empty(trim($t)));
        if (empty($nonEmptyTexts)) return [];

        $capability = ($taskType === 'RETRIEVAL_QUERY') 
            ? ApiKey::CAPABILITY_QUERY_EMBEDDING 
            : ApiKey::CAPABILITY_EMBEDDING;

        return $this->executeWithFailover($capability, function ($apiKeyModel) use ($texts, $userId, $taskType) {
            $startTime = microtime(true);
            $apiKey = $apiKeyModel->decrypted_key;
            
            // Gemini Batch Embedding API
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:batchEmbedContents?key={$apiKey}";

            $requests = array_map(function($text) use ($taskType) {
                return [
                    'model' => 'models/gemini-embedding-001',
                    'content' => ['parts' => [['text' => $text]]],
                    'taskType' => $taskType,
                ];
            }, $texts);

            $payload = ['requests' => $requests];

            $response = Http::timeout(30)->post($url, $payload);
            $executionTime = microtime(true) - $startTime;

            if ($response->failed()) {
                $statusCode = $response->status();
                throw new \Exception("Gemini Batch API Error: " . $response->body() . " (Status: $statusCode)");
            }

            $data = $response->json();
            $embeddings = $data['embeddings'] ?? [];
            
            if (count($embeddings) !== count($texts)) {
                throw new \Exception("Gemini Batch returned incomplete results: expected " . count($texts) . ", got " . count($embeddings));
            }

            $vectors = array_map(function($e) {
                return $e['values'] ?? null;
            }, $embeddings);

            // Telemetry (Log first text as representative)
            $this->telemetryService->logRequest(
                $apiKeyModel,
                $texts[0] . " [+ " . (count($texts)-1) . " more]",
                ['content' => '[BATCH VECTOR DATA]', 'usage' => ['total_tokens' => $this->telemetryService->estimateTokens(implode(' ', $texts))]],
                $executionTime,
                $userId,
                null,
                'embedding_batch'
            );

            return $vectors;
        });
    }

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
                $keyName = $apiKey->vault?->nickname ?? ("Chave #{$apiKey->id}");
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
                'estimated_cost' => $result['estimated_cost'] ?? 0,
                'api_key_name' => $apiKey->vault?->nickname ?? "Chave #{$apiKey->id}"
            ];
        });
    }

    public function generateJson(string $prompt, string $capability, ?string $model = null, ?int $userId = null): array
    {
        $provider = $model ? $this->getProviderForModel($model) : null;

        return $this->executeWithFailover($capability, function ($apiKey) use ($prompt, $model, $userId, $capability) {
            $provider = $apiKey->provider;
            if ($model) {
                $apiKey->preferred_model = $model;
            }

            $result = $this->callAI($provider, $apiKey, $prompt, $userId, $capability);

            // Decode the JSON returned by the AI (may come as raw string with markdown blocks)
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
            $sims = $user->simulations()->where('status', 'finished')->where('time_elapsed', '>', 0)->latest()->take(10)->get();
            if ($sims->isNotEmpty()) {
                $totalTime = $sims->sum('time_elapsed');
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
     * Executes an AI request with a robust multi-level failover and recovery strategy.
     * 
     * STRATEGY:
     * - LEVEL 1 (Instant Recovery): If all keys are locked (busy), the worker stays in an internal
     *   wait loop for up to 15s. It polls specifically for an unlocked key every ~0.5s with jitter.
     *   This allows 'pouncing' on keys released by other workers without queue latency.
     * 
     * - LEVEL 2 (Queue Backoff): If the 15s wait fails, it throws a 'Busy' exception, which 
     *   triggers a 30s backoff in the background job (releasing it back to the queue).
     * 
     * - LEVEL 3 (Quota Blacklisting): If a key is picked but returns a 429/Quota error, it is 
     *   blacklisted for 60 minutes. Jobs will ignore this key and use others in the pool.
     */
    protected function executeWithFailover(string $capability, \Closure $closure, ?string $provider = null)
    {
        // LEVEL 0: Fetch available keys from the database ONCE. 
        // We don't want to hit the DB every 0.5s inside the wait loop.
        $apiKeys = ApiKey::getKeysForCapability($capability, $provider);

        if ($apiKeys->isEmpty()) {
            throw new AIServiceBusyException("No active AI providers found for: {$capability}");
        }

        $lastException = null;
        $keysLockedCount = 0;
        $startTime = microtime(true);
        $poolStatus = [
            'total' => $apiKeys->count(),
            'online' => 0,
            'blacklisted' => 0,
            'busy' => 0
        ];
        
        // Priority System: 
        // Background workers (CLI) should back off quickly (2s) to Level 2 (Queue Backoff)
        // while interactive users (Web) get more endurance (15s) to acquire a key.
        $isBackground = app()->runningInConsole();
        $timeout = 15.0; // Now 15s for everyone as requested

        while ((microtime(true) - $startTime) < $timeout) {
            // NEW: Jitter moved BEFORE picking a key and BEFORE locking.
            // This prevents workers from holding onto keys while they are sleeping.
            if ($isBackground) {
                $jitterMicro = random_int(1000000, 2000000); // 1s to 2s for CLI as requested
                Log::info("[AIService][executeWithFailover] Worker pausing for " . ($jitterMicro/1000000) . "s (jitter) BEFORE lock.");
                usleep($jitterMicro);
            }

            $keysLockedCount = 0;
            
            // Read the latest quota blacklist from cache (Level 3). 
            // This is very fast (Redis-backed) and ensures we skip newly 'burned' keys.
            $blacklist = \Illuminate\Support\Facades\Cache::get('api_key_blacklist', []);

            foreach ($apiKeys as $apiKey) {
                // Ignore keys that are currently in the Quota Blacklist (Level 3)
                if (in_array($apiKey->id, $blacklist)) {
                    $poolStatus['blacklisted']++;
                    Log::debug("[AIService][executeWithFailover] Skipping Key #{$apiKey->id} ({$apiKey->provider}): In Blacklist.");
                    continue;
                }

                $lockKey = "ai_provider_lock_{$apiKey->id}";
                $isLocked = \Illuminate\Support\Facades\Redis::get($lockKey);

                if ($isLocked) {
                    $keysLockedCount++;
                    $poolStatus['busy']++;
                    Log::debug("[AIService][executeWithFailover] Skipping Key #{$apiKey->id} ({$apiKey->provider}): Locked in Redis.");
                    continue;
                }

                $poolStatus['online']++;

                // Acquire distributed lock (1 min safety TTL)
                Log::debug("[AIService][executeWithFailover] Attempting to acquire lock for Key #{$apiKey->id}...");
                $lockAcquired = \Illuminate\Support\Facades\Redis::set($lockKey, '1', 'EX', 60, 'NX');
                if (!$lockAcquired) {
                    $keysLockedCount++;
                    Log::debug("[AIService][executeWithFailover] Lock acquisition FAILED for Key #{$apiKey->id}.");
                    continue;
                }

                Log::debug("[AIService][executeWithFailover] Lock ACQUIRED for Key #{$apiKey->id}.");

                try {
                    $attempts = 0;
                    $maxAttempts = 3;

                    Log::info("[AIService][executeWithFailover] -> POOL STATUS Leg : [Total: {$poolStatus['total']}] [BL: {$poolStatus['blacklisted']}] [Busy: {$poolStatus['busy']}] [Testing: Key #{$apiKey->id}]");

                    while ($attempts < $maxAttempts) {
                        try {
                            $reqStart = microtime(true);
                            $result = $closure($apiKey);
                            $duration = round((microtime(true) - $reqStart) * 1000);
                            
                            Log::info("[AIService][executeWithFailover] -> SUCCESS on Key #{$apiKey->id} ({$apiKey->provider}) [Duration: {$duration}ms]");
                            
                            return $result;
                        } catch (\Exception $e) {
                            $attempts++;
                            $lastException = $e;
                            $duration = round((microtime(true) - $reqStart) * 1000);
                            $errorBody = method_exists($e, 'getResponse') && $e->getResponse() ? (string)$e->getResponse()->getBody() : $e->getMessage();

                            Log::warning("[AIService][executeWithFailover] -> FAILURE on Key #{$apiKey->id} ({$apiKey->provider}) [Duration: {$duration}ms] [Attempt: {$attempts}]", [
                                'error' => $e->getMessage(),
                                'body' => substr($errorBody, 0, 1000)
                            ]);

                            if ($this->isRetriableError($e)) {
                                $isQuota = str_contains(strtolower($e->getMessage()), '429') || 
                                          str_contains(strtolower($e->getMessage()), 'quota');

                                if ($attempts < $maxAttempts && !$isQuota) {
                                    $sleepSeconds = pow(2, $attempts);
                                    Log::warning("[AIService] Temporary error on #{$apiKey->id}. Retrying task in {$sleepSeconds}s...");
                                    sleep($sleepSeconds);
                                    continue;
                                }

                                // Key exhausted or failed. Mark it as 'Resting' (Level 3).
                                Log::warning("[AIService] " . ($isQuota ? 'QUOTA EXCEEDED' : 'FAILURE') . " on #{$apiKey->id}. Falling over...");
                                $this->banKeyTemporarily($apiKey, $e, 60); // Keep 60 min as requested
                                break; 
                            }

                            throw $e;
                        }
                    }
                } finally {
                    Log::debug("[AIService][executeWithFailover] Releasing lock for Key #{$apiKey->id}.");
                    // Always release the lock for Level 1 workers to pounce.
                    \Illuminate\Support\Facades\Redis::del($lockKey);
                }
            }

            // LEVEL 1: If pool was busy, wait with jitter and retry internally.
            if ($keysLockedCount > 0 && (microtime(true) - $startTime) < $timeout) {
                $jitter = rand(100, 500) * 1000; // 100ms - 500ms jitter to prevent thundering herd
                usleep(500000 + $jitter); 
                continue;
            }

            break;
        }

        // --- CONGESTION LOGGING ---
        if (isset($keysLockedCount) && $keysLockedCount > 0) {
            $this->registerCongestion($capability, "Busy Pool ({$keysLockedCount} Locked)");
        }

        // LEVEL 2: Timeout reached. Release to Queue Backoff.
        throw new AIServiceBusyException(
            "AI Pool Congestion: All keys for '{$capability}' are currently busy or reached quota limit.",
            0,
            $lastException
        );
    }

    /**
     * Determines if the AI error is retriable with a different key.
     */
    protected function isRetriableError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        // 429 = Rate Limit / Quota Exceeded — try with another key.
        if (str_contains($message, '429') || str_contains($message, 'quota exceeded') || str_contains($message, 'rate limit')) {
            return true;
        }

        // 500, 502, 503, 504 = Server errors from Google/OpenAI.
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
     * Coloca a ID da key em um cache de blacklist rápido por X min
     * impedindo a extração excessiva do BD enquando o HealthChecker não roda.
     */
    protected function banKeyTemporarily(ApiKey $apiKey, \Exception $e, int $durationMinutes = 60): void
    {
        $bannedIds = Cache::get('api_key_blacklist', []);
        if (!in_array($apiKey->id, $bannedIds)) {
            $bannedIds[] = $apiKey->id;
        }

        Cache::put('api_key_blacklist', $bannedIds, now()->addMinutes($durationMinutes));
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
    public function sendRawPrompt(string $prompt, string $capability, ?int $userId = null): string
    {
        return $this->executeWithFailover($capability, function ($apiKey) use ($prompt, $userId) {
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

    /**
     * Registers a job that was released (backoff) due to API key congestion.
     * Stores in a Redis Hash so that it can be specifically removed upon success.
     */
    public function registerCongestion(string $jobName, $id = null): void
    {
        try {
            $key = "xavier:ai:active_congestion";
            $field = $id ? "{$jobName}:{$id}" : $jobName;

            $data = json_encode([
                'job' => $jobName,
                'id' => $id,
                'timestamp' => now()->toIso8601String(),
            ]);

            Redis::hset($key, $field, $data);
            Redis::expire($key, 3600); // 1 hour TTL
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("[AIService] Failed to register congestion in Redis: " . $e->getMessage());
        }
    }

    /**
     * Removes a job from the active congestion list (called upon success).
     */
    public function removeCongestion(string $jobName, $id = null): void
    {
        try {
            $key = "xavier:ai:active_congestion";
            $field = $id ? "{$jobName}:{$id}" : $jobName;
            Redis::hdel($key, $field);
        } catch (\Exception $e) {
            // Failure to clear UI log shouldn't crash the job
        }
    }

    public function getCongestionList(): array
    {
        try {
            $hashKey = "xavier:ai:active_congestion";
            $items = Redis::hvals($hashKey);
            $total = count($items);
            
            // Limit to 50 items to avoid crashing the dashboard
            $items = array_slice($items, 0, 50);

            $results = array_map(function($item) {
                $data = json_decode($item, true);
                if (isset($data['timestamp'])) {
                    $data['ago'] = \Carbon\Carbon::parse($data['timestamp'])->diffForHumans();
                }
                return $data;
            }, $items);

            // Sort by timestamp desc to show newest first
            usort($results, fn($a, $b) => strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? ''));

            return [
                'items' => $results,
                'total' => $total,
            ];
        } catch (\Exception $e) {
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Clears all congestion tracking.
     */
    public function clearCongestionList(): void
    {
        Redis::del("xavier:ai:congestion_list");       // Legacy list
        Redis::del("xavier:ai:active_congestion");     // New Hash
    }
}

