<?php

namespace App\Jobs;

use App\Models\Simulation;
use App\Models\Correction;
use App\Services\AIService;
use App\Services\Study\StudyStatsService; // NEW IMPORT
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CorrectSimulationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $simulation;

    public function __construct(Simulation $simulation)
    {
        $this->simulation = $simulation;
    }

    public function handle(AIService $aiService): void
    {
        // Eager load alternatives para evitar N+1 no loop de questões abaixo
        $this->simulation->load(['answers.question.alternatives', 'user.plan']);
        $user = $this->simulation->user;
        $plan = $user->plan ? $user->plan->slug : 'free';

        $totalQuestions = $this->simulation->answers->count();
        Log::info("Iniciando correção da simulação #{$this->simulation->id} - Total de questões: {$totalQuestions}");

        // 1. Preparar Todas as Questões
        $questionsToProcess = $this->simulation->answers->map(function ($answer) {
            $question = $answer->question;
            return [
                'question_id'   => $answer->question_id,
                'statement'     => $question->statement,
                // alternativesAsMap(): ['A'=>'texto', 'B'=>'texto'...]
                // Substituições da Collection Eloquent crua — formato correto para o prompt da IA
                'alternatives'  => $question->alternativesAsMap(),
                'user_answer'   => $answer->user_answer,
                // correct_answer: resolvido pelo accessor virtual em Question.php
                // que lê is_correct=true na tabela question_alternatives
                'correct_answer' => $question->correct_answer,
                'organization'  => $question->organization,
                'source'        => $question->source,
            ];
        })->toArray();

        // 2. Criar/Recuperar Registro de Correção (Inicialização)
        $correction = Correction::updateOrCreate(
        [
            'correctable_type' => Simulation::class ,
            'correctable_id' => $this->simulation->id,
        ],
        [
            'ai_provider' => 'pending',
            'correction_data' => [
                'total_correct' => 0,
                'total_questions' => $totalQuestions,
                'errors_explanation' => []
            ],
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'created_at' => now(),
        ]
        );

        // 3. Correção via AI com Batching
        $batches = array_chunk($questionsToProcess, 5); // Use questionsToProcess directly
        $mergedExplanations = [];
        $totalInput = 0;
        $totalOutput = 0;
        $providerUsed = 'openai'; // Fallback default

        foreach ($batches as $index => $batch) {
            $currentBatch = $index + 1;
            $totalBatches = count($batches);
            Log::info("Processando lote {$currentBatch} de {$totalBatches} para simulação #{$this->simulation->id}");

            // Call AI
            try {
                $result = $aiService->correctSimulation($batch, $plan, $this->simulation->user_id);

                if (!$result) {
                    Log::error("JOB ERROR: AI Service returned null for batch {$currentBatch}.");
                    continue;
                }

                $providerUsed = $result['provider'];
                $response = $result['response'];
                $usage = $result['usage'];

                $totalInput += $usage['input_tokens'] ?? 0;
                $totalOutput += $usage['output_tokens'] ?? 0;

                // Robust Extraction of explanations
                $newExplanations = $response['errors_explanation']
                    ?? $response['explanations']
                    ?? $response['questions']
                    ?? $response['data']
                    ?? [];

                if (empty($newExplanations) && isset($response['text'])) {
                    $nestedJson = json_decode(preg_replace('/^```(?:json)?\s+|\s+```$/i', '', trim($response['text'])), true);
                    $newExplanations = $nestedJson['errors_explanation'] ?? $nestedJson['explanations'] ?? $nestedJson ?? [];
                }

                if (empty($newExplanations) && isset($response[0]) && is_array($response[0])) {
                    $newExplanations = $response;
                }

                // Normalize IDs
                foreach ($newExplanations as &$explanation) {
                    if (isset($explanation['question_id'])) {
                        $explanation['question_id'] = (string)$explanation['question_id'];
                    }
                }

                $mergedExplanations = array_merge($mergedExplanations, $newExplanations);

                // Persistência Incremental
                $correction->update([
                    'ai_provider' => $providerUsed,
                    'input_tokens' => $totalInput,
                    'output_tokens' => $totalOutput,
                    'total_tokens' => $totalInput + $totalOutput,
                    'correction_data' => [
                        'total_correct' => $this->simulation->answers->where('is_correct', true)->count(),
                        'total_questions' => $totalQuestions,
                        'errors_explanation' => $mergedExplanations
                    ]
                ]);

            }
            catch (\Exception $e) {
                if (str_contains($e->getMessage(), '429')) {
                    Log::warning("JOB: 429 Quota Exceeded. Releasing for 60s.");
                    $this->release(60);
                    return;
                }
                Log::error("JOB: Error in batch {$currentBatch}: " . $e->getMessage());
            }
        }

        // 4. Finalização e Cálculos Locais
        $correctAnswers = $this->simulation->answers->where('is_correct', true)->count();
        $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;

        $scoresBySubject = [];
        $answersBySubject = $this->simulation->answers->groupBy('question.subject');
        foreach ($answersBySubject as $subject => $answers) {
            $total = $answers->count();
            $correct = $answers->where('is_correct', true)->count();
            $scoresBySubject[$subject] = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
        }

        // Finalizar registro de correcao
        $correction->update([
            'corrected_at' => now(),
            'ai_provider' => $providerUsed,
            'correction_data' => [
                'total_correct' => $correctAnswers,
                'total_questions' => $totalQuestions,
                'score' => $score,
                'scores_by_subject' => $scoresBySubject,
                'errors_explanation' => $mergedExplanations
            ]
        ]);

        Log::info("Correção #{$this->simulation->id} finalizada com sucesso. Total Tokens: " . ($totalInput + $totalOutput));

        // 5. Atualizar Estatísticas do Usuário para o Plano de Estudos (REFACTORED)
        try {
            $statsService = app(StudyStatsService::class);
            $statsService->updateUserStats($user, $this->simulation);
            Log::info("Estatísticas do usuário {$user->id} atualizadas com sucesso.");
        }
        catch (\Exception $e) {
            Log::error("Falha ao atualizar estatísticas do usuário: " . $e->getMessage());
        }
    }
}