<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $providers = ['openai', 'gemini', 'grok'];

    public function hasActiveKey(): bool
    {
        foreach ($this->providers as $provider) {
            if (ApiKey::getActiveKeyForProvider($provider)) {
                return true;
            }
        }
        return false;
    }

    public function correctSimulation(array $questionsAndAnswers, string $plan): ?array
    {
        if (!$this->hasActiveKey()) {
            return null;
        }

        $provider = $this->getFirstAvailableProvider();
        if (!$provider) {
            return null;
        }

        $apiKey = ApiKey::getActiveKeyForProvider($provider);

        try {
            $prompt = $this->buildSimulationCorrectionPrompt($questionsAndAnswers, $plan);
            $result = $this->callAI($provider, $apiKey->decrypted_key, $prompt);

            return [
                'provider' => $provider,
                'response' => $result['content'],
                'usage' => $result['usage'] ?? [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'total_tokens' => 0,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('AI Correction failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return null;
        } finally {
            // Count usage regardless of success
            if (isset($apiKey)) {
                $apiKey->incrementUsage();
            }
        }
    }

    public function correctEssay(string $title, string $content, string $plan): ?array
    {
        if (!$this->hasActiveKey()) {
            return null;
        }

        $provider = $this->getFirstAvailableProvider();
        if (!$provider) {
            return null;
        }

        $apiKey = ApiKey::getActiveKeyForProvider($provider);

        try {
            $prompt = $this->buildEssayCorrectionPrompt($title, $content, $plan);
            $result = $this->callAI($provider, $apiKey->decrypted_key, $prompt);

            return [
                'provider' => $provider,
                'response' => $result['content'],
                'usage' => $result['usage'] ?? [
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'total_tokens' => 0,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Essay correction failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return null;
        } finally {
            // Count usage regardless of success
            if (isset($apiKey)) {
                 $apiKey->incrementUsage();
            }
        }
    }

    protected function callAI(string $provider, string $apiKey, string $prompt): array
    {
        return match ($provider) {
            'openai' => $this->callOpenAI($apiKey, $prompt),
            'gemini' => $this->callGemini($apiKey, $prompt),
            'grok' => $this->callGrok($apiKey, $prompt),
            default => throw new \Exception("Provider not supported: $provider")
        };
    }

    protected function callOpenAI(string $apiKey, string $prompt): array
    {
        $response = Http::timeout(120)
            ->connectTimeout(60)
            ->withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um professor especialista em correção do ENEM. Responda estritamente em JSON.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
             Log::error('OpenAI API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception("OpenAI API Error: " . $response->status());
        }

        $data = $response->json();
        
        // Parse content safely
        $contentString = $data['choices'][0]['message']['content'] ?? '{}';
        $content = json_decode($contentString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
             Log::error('OpenAI invalid JSON response', ['content' => $contentString]);
             $content = []; // Retorna vazio mas não quebra
        }

        // Extract usage
        $usage = $data['usage'] ?? [];
        
        return [
            'content' => $content ?? [],
            'usage' => [
                'input_tokens' => $usage['prompt_tokens'] ?? 0,
                'output_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ]
        ];
    }

    protected function callGemini(string $apiKey, string $prompt): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}";

        $response = Http::timeout(120)
            ->connectTimeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                ]
            ]);

        if ($response->failed()) {
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception("Gemini API Error: " . $response->body());
        }

        $data = $response->json();

        // Extrair texto da resposta
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Tentar decodificar se for JSON esperado
        $json = json_decode($text, true) ?? ['text' => $text];

        // Extract Usage Metadata (Gemini returns usageMetadata)
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

    protected function callGrok(string $apiKey, string $prompt): array
    {
        // Placeholder para Grok
        return [];
    }

    protected function getFirstAvailableProvider(): ?string
    {
        // Priorizar Gemini
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

    protected function buildSimulationCorrectionPrompt(array $questionsAndAnswers, string $plan): string
    {
        // Estrutura Base Obrigatória (Imutável)
        $baseStructure = "Retorne APENAS um JSON válido com esta estrutura exata: {
            'total_correct': int, 
            'total_questions': int, 
            'errors_explanation': [
                { 'question_id': id_da_questao, 'why_wrong': 'motivo do erro', 'correct_approach': 'como resolver' }
            ]
        }";

        // Instruções de Profundidade (Variável por Plano)
        $depthInstruction = match ($plan) {
            'free', 'basic' => "Para 'errors_explanation', forneça explicações CURTAS e DIRETAS (máximo 1 frase). Ex: 'A alternativa correta é B porque X.' foco apenas nas questões erradas.",
            'plus' => "Para 'errors_explanation', forneça explicações DETALHADAS e PEDAGÓGICAS. Explique o conceito por trás do erro e dê uma dica de estudo.",
            default => "Explicações concisas."
        };

        return "Corrija as questões abaixo. $baseStructure\n\n$depthInstruction\n\nDados:\n" . json_encode($questionsAndAnswers);
    }

    protected function buildEssayCorrectionPrompt(string $title, string $content, string $plan): string
    {
        $instructions = match ($plan) {
            'basic' => 'Retorne JSON com: {score: 0-1000, competencies: {C1-C5: 1-5}, feedback: string}',
            'plus' => 'Retorne JSON detalhado com: {score, competencies, feedback, detailed_suggestions: [], example_essay: string}',
            default => 'Redações não disponíveis neste plano'
        };

        return "Corrija a redação do ENEM com tema '$title' e $instructions\n\nTexto:\n$content";
    }
}
