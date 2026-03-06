<?php

namespace App\Jobs;

use App\Models\Essay;
use App\Services\AI\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateEssayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $essay;

    public $tries = 5;
    public $timeout = 120;

    public function __construct(Essay $essay)
    {
        $this->essay = $essay;
    }

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function handle(AIService $aiService): void
    {
        Log::info("Starting evaluation for Essay ID: {$this->essay->id}");

        // Reload to get fresh status
        $this->essay->refresh();

        if ($this->essay->status === 'completed') {
            Log::info("Essay ID: {$this->essay->id} already completed. Skipping.");
            return;
        }

        try {
            $this->essay->update(['status' => 'evaluating']);

            // --- OFF-TOPIC GATE ---
            $evalContent = $this->essay->input_type === 'image' && $this->essay->extracted_text
                ? $this->essay->extracted_text
                : $this->essay->content;

            $offTopicResult = $aiService->detectOffTopic(
                $this->essay->title,
                $evalContent,
                $this->essay->type,
                $this->essay->user_id
            );

            $isOffTopic = (
                data_get($offTopicResult, 'off_topic') === true
                || data_get($offTopicResult, 'offtopic') === true
                || data_get($offTopicResult, 'is_offtopic') === true
                || data_get($offTopicResult, 'on_topic') === false
                || (is_array(data_get($offTopicResult, 'labels')) && in_array('offtopic', data_get($offTopicResult, 'labels'), true))
            );

            // Fail-closed: if detectOffTopic result is empty or invalid
            if (empty($offTopicResult)) {
                $isOffTopic = true;
            }

            if ($isOffTopic) {
                $fallbackFixes = "Não foram identificadas correções pontuais relevantes neste texto. Confira os comentários gerais e a versão melhorada para aprimorar estrutura e clareza.";
                $fallbackMsg = $aiService->generateImprovedEssayForTopic($this->essay->title, $this->essay->type);

                $this->essay->update([
                    'status' => 'completed',
                    'score' => 0,
                    'competencies' => [],
                    'off_topic' => true,
                    'off_topic_reason' => $offTopicResult['reason'] ?? 'Você fugiu do tema proposto.',
                    'final_score_locked' => true,
                    'evaluated_at' => now(),
                    'ai_suggestions' => $fallbackFixes,
                    'feedback_json' => [
                        'score' => 0,
                        'summary' => 'Fuga do tema: nota 0.',
                        'strengths' => [],
                        'weaknesses' => ['Fuga do tema proposto.'],
                        'corrections' => [],
                        'correcoes_pontuais' => $fallbackFixes,
                        'improved_version' => $fallbackMsg,
                        'competencies' => []
                    ],
                ]);

                Log::info("[EvaluateEssayJob] Essay ID: {$this->essay->id} detected as OFF-TOPIC. User ID: {$this->essay->user_id}, Score: 0");
                return; // LOCK - Stop processing
            }
            // --- END OFF-TOPIC GATE ---

            $result = $aiService->evaluateEssay(
                $this->essay->title,
                $evalContent,
                $this->essay->type,
                $this->essay->user_id
            );

            if ($result && isset($result['response'])) {
                $response = $result['response'];

                // Regra A: JSON inválido ou parse falhou
                // Consideramos falha se as chaves principais não existirem
                if (!isset($response['overall_score']) && !isset($response['improved_version']) && !isset($response['competence_scores'])) {
                    $response['off_topic'] = true;
                    $response['off_topic_reason'] = 'Falha na formatação da resposta da IA (JSON inválido).';
                }

                // Robust Off-topic check in the normal path
                $isOffTopicNormalPath = (
                    data_get($response, 'off_topic') === true
                    || data_get($response, 'offtopic') === true
                    || data_get($response, 'is_offtopic') === true
                    || data_get($response, 'on_topic') === false
                    || (is_array(data_get($response, 'labels')) && in_array('offtopic', data_get($response, 'labels'), true))
                );

                if ($isOffTopicNormalPath) {
                    $finalFields = ['score', 'overall_score', 'final_score', 'total_score', 'grade'];
                    foreach ($finalFields as $field) {
                        if (array_key_exists($field, $response)) {
                            $response[$field] = 0;
                        }
                    }
                    $response['score'] = 0;

                    // Regra B: Forçar c1..c5 a 0
                    if (isset($response['competence_scores']) && is_array($response['competence_scores'])) {
                        foreach ($response['competence_scores'] as $key => $val) {
                            $response['competence_scores'][$key] = 0;
                        }
                    }

                    // Se usar o formato antigo competencies
                    if (isset($response['competencies']) && is_array($response['competencies'])) {
                        foreach ($response['competencies'] as &$comp) {
                            if (is_array($comp) && isset($comp['score'])) {
                                $comp['score'] = 0;
                            }
                        }
                    }
                }

                // Improved Version Fallback
                $improved = trim((string) ($response['improved_version'] ?? ''));
                if ($improved === '') {
                    $improved = trim((string) (
                        $response['rewrite']
                        ?? $response['improved_text']
                        ?? $response['suggested_paragraph']
                        ?? ''
                    ));
                }

                // Regra C: Validar se o texto gerado não é curto demais
                $isTooShort = false;
                $len = mb_strlen($improved);
                if ($len < 200) {
                    $isTooShort = true;
                }
                if ($this->essay->type === 'enem' && $len < 1200) {
                    $isTooShort = true;
                }

                if ($isOffTopicNormalPath || $improved === '' || $isTooShort) {
                    $improved = $aiService->generateImprovedEssayForTopic($this->essay->title, $this->essay->type);
                }
                $response['improved_version'] = $improved;

                // Correções Pontuais Fallback
                $fixes = $response['correcoes_pontuais'] ?? $response['ai_suggestions'] ?? $response['corrections'] ?? '';
                if (is_array($fixes) && empty($fixes)) {
                    $fixes = '';
                } elseif (!is_array($fixes)) {
                    $fixes = trim((string) $fixes);
                }

                if ($fixes === '') {
                    $fixes = trim((string) ($response['bullet_fixes'] ?? $response['line_edits'] ?? ''));
                }

                if ($fixes === '') {
                    $fixes = "Não foram identificadas correções pontuais relevantes neste texto. Confira os comentários gerais e a versão melhorada para aprimorar estrutura e clareza.";
                }
                $response['correcoes_pontuais'] = $fixes;

                $response = $this->normalizeAiResponse($response, $this->essay->type);

                // --- FAIL-CLOSED GATE FINAL (MODEL LEVEL) ---
                if ($isOffTopicNormalPath) {
                    $this->essay->score = 0;

                    // Zero any other score-related attributes if they exist
                    $attributes = $this->essay->getAttributes();
                    foreach (['overall_score', 'final_score', 'total_score', 'grade'] as $field) {
                        if (array_key_exists($field, $attributes)) {
                            $this->essay->{$field} = 0;
                        }
                    }
                } else {
                    // If not off-topic, ensure model score is updated from response if it was null
                    if ($this->essay->score === null) {
                        $this->essay->score = $response['score'] ?? $response['overall_score'] ?? 0;
                    }
                }

                $this->essay->update([
                    'status' => 'completed',
                    'score' => $this->essay->score, // Use the value set in the model
                    'off_topic' => $isOffTopicNormalPath,
                    'feedback_json' => $response,
                    'evaluated_at' => now(),
                    'ai_suggestions' => $fixes,
                    'improved_version' => $improved, // Ensure model field is updated too if it exists
                ]);

                Log::info("[EvaluateEssayJob] Essay ID: {$this->essay->id} evaluated. User ID: {$this->essay->user_id}, isOffTopic: " . ($isOffTopicNormalPath ? 'true' : 'false') . ", final score: " . ($this->essay->score));
            } else {
                Log::error("Essay ID: {$this->essay->id} evaluation returned empty result.");
                throw new \Exception("Empty result from AI Service (Provider: " . ($result['provider'] ?? 'unknown') . ")");
            }

        } catch (\Throwable $e) {
            Log::error("Essay ID: {$this->essay->id} evaluation failed: " . $e->getMessage());
            // Rethrow to trigger Laravel retry mechanism
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Essay ID: {$this->essay->id} FAILED after all retries. Exception: " . $exception->getMessage());

        $this->essay->update([
            'status' => 'error',
            // Optional: Save error message if you have a column for it, 
            // but requirement says "without forbidden terms". 
            // 'feedback' => 'Não foi possível corrigir agora. Tente novamente.' 
        ]);
    }

    /**
     * Normalizes AI JSON response keys to match what the React frontend expects.
     */
    private function normalizeAiResponse(array $response, string $type): array
    {
        // 1. Map overall_score to score
        if (!isset($response['score']) && isset($response['overall_score'])) {
            $response['score'] = $response['overall_score'];
        }

        // 2. Map actionable_feedback to summary
        if (!isset($response['summary']) && isset($response['actionable_feedback'])) {
            $feedback = $response['actionable_feedback'];
            $response['summary'] = is_array($feedback) ? implode("\n\n", $feedback) : (string) $feedback;
        }

        // 3. Map competence_scores to competencies matching frontend schema {c1: ...}
        if (isset($response['competence_scores']) && is_array($response['competence_scores'])) {
            $maxScore = $type === 'enem' ? 200 : 20;
            $competencies = [];

            // Expected frontend format: competencies object keyed 'c1'..'c5', each with 'score'
            foreach (['c1', 'c2', 'c3', 'c4', 'c5'] as $c) {
                if (isset($response['competence_scores'][$c])) {
                    $competencies[$c] = [
                        'score' => (int) $response['competence_scores'][$c],
                        'max_score' => $maxScore,
                        'justification' => $response['actionable_feedback'][$c] ?? '' // fallback
                    ];
                }
            }
            if (!empty($competencies)) {
                $response['competencies'] = $competencies;
            }
        }

        // 4. Map corrections
        if (!isset($response['corrections'])) {
            $response['corrections'] = $response['correcoes_pontuais'] ?? [];
        }

        return $response;
    }
}
