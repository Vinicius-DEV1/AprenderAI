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

        try {
            $apiKey = ApiKey::getActiveKeyForProvider($provider);

            $prompt = $this->buildSimulationCorrectionPrompt($questionsAndAnswers, $plan);

            $response = $this->callAI($provider, $apiKey->decrypted_key, $prompt);

            $apiKey->incrementUsage();

            return [
                'provider' => $provider,
                'response' => $response,
            ];
        } catch (\Exception $e) {
            Log::error('AI Correction failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return null;
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

        try {
            $apiKey = ApiKey::getActiveKeyForProvider($provider);

            $prompt = $this->buildEssayCorrectionPrompt($title, $content, $plan);

            $response = $this->callAI($provider, $apiKey->decrypted_key, $prompt);

            $apiKey->incrementUsage();

            return [
                'provider' => $provider,
                'response' => $response,
            ];
        } catch (\Exception $e) {
            Log::error('Essay correction failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return null;
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
        $response = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Você é um professor especialista em correção de provas e redações do ENEM.'],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.7,
                ]);

        return $response->json();
    }

    protected function callGemini(string $apiKey, string $prompt): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}";

        $response = Http::withHeaders([
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
            throw new \Exception("Gemini API Error: " . $response->body());
        }

        $data = $response->json();

        // Extrair texto da resposta
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Tentar decodificar se for JSON esperado
        $json = json_decode($text, true);

        return $json ?? ['text' => $text];
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
        $instructions = match ($plan) {
            'free' => 'Retorne apenas um JSON com: {total_correct: X, total_questions: Y}',
            'basic' => 'Retorne JSON com: {total_correct: X, total_questions: Y, themes_to_improve: [array de temas]}',
            'plus' => 'Retorne JSON detalhado com: {total_correct, total_questions, themes_analysis: {tema: {correct, total, percentage}}, errors_explanation: [{question_id, why_wrong, correct_approach}], study_plan: [temas prioritários]}',
            default => 'Retorne apenas acertos/erros'
        };

        return "Corrija as seguintes questões e $instructions\n\n" . json_encode($questionsAndAnswers);
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

    public function generateJson(string $prompt): array
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
            $response = $this->callAI($provider, $apiKey->decrypted_key, $prompt);
            $apiKey->incrementUsage();

            $data = $response;

            // Normalize OpenAI response
            if ($provider === 'openai') {
                $content = $response['choices'][0]['message']['content'] ?? '';
                // Try to decode JSON from content
                $decoded = json_decode($content, true);
                $data = $decoded ?? ['text' => $content];
            }

            return [
                'provider' => $provider,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('AI Generation failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
