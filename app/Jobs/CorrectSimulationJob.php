<?php

namespace App\Jobs;

use App\Models\Simulation;
use App\Models\Correction;
use App\Services\AIService;
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
        $this->simulation->load(['answers.question', 'user.plan']);
        $user = $this->simulation->user;
        $plan = $user->plan ? $user->plan->slug : 'free';

        $totalQuestions = $this->simulation->answers->count();
        Log::info("Iniciando correção da simulação #{$this->simulation->id} - Total de questões: {$totalQuestions}");

        // 1. Preparar Todas as Questões (TEST MODE: 25 QUESTIONS)
        $allQuestions = $this->simulation->answers->take(25)->map(function ($answer) {
            return [
                'question_id' => $answer->question_id,
                'statement' => $answer->question->statement,
                'alternatives' => $answer->question->alternatives,
                'user_answer' => $answer->user_answer,
                'correct_answer' => $answer->question->correct_answer,
            ];
        })->toArray();

        // 2. Criar/Recuperar Registro de Correção (Inicialização)
        $correction = Correction::updateOrCreate(
            [
                'correctable_type' => Simulation::class,
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
                'created_at' => now(), // Garantir criação
            ]
        );

        // 3. Correção Local (Offline-First)
        $totalInput = 0;
        $totalOutput = 0;
        $providerUsed = 'openai'; // Fallback default

        foreach ($chunks as $index => $chunk) {
            $currentBatch = $index + 1;
            $totalBatches = count($chunks);
            Log::info("Processando lote {$currentBatch} de {$totalBatches} para simulação #{$this->simulation->id}");

            // Intensive Debug: Log input batch
            Log::info("==== JOB DEBUG: Sending Batch {$currentBatch} to AI ====");
            Log::info("DEBUG: Question IDs in this batch: " . implode(', ', array_column($chunk, 'question_id')));
            Log::debug("DEBUG: Full Batch Data: " . json_encode($chunk));

            // Call AI
            try {
                $result = $aiService->correctSimulation($chunk, $plan, $this->simulation->user_id);
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), '429')) {
                    Log::warning("JOB: 429 Quota Exceeded. Releasing job for 60 seconds.");
                    $this->release(60);
                    return; // Stop execution of this job instance
                }
                Log::error("JOB: Error in batch {$currentBatch}: " . $e->getMessage());
                $result = null;
            }

            if (!$result) {
                Log::error("JOB ERROR: AI Service returned null for batch {$currentBatch}. Possible cause: No active/online API keys.");
                continue;
            }

            if ($result) {
                $providerUsed = $result['provider'];
                $response = $result['response'];
                $usage = $result['usage'];

                // DEBUG: Log raw response to find why it is empty
                Log::debug("AI Raw Response Batch {$currentBatch}: " . json_encode($response));

                $totalInput += $usage['input_tokens'] ?? 0;
                $totalOutput += $usage['output_tokens'] ?? 0;

                // CRITICAL FIX: Robust Extraction
                // Tentar várias chaves possíveis que a IA pode usar
                $newExplanations = $response['errors_explanation'] 
                    ?? $response['explanations'] 
                    ?? $response['questions']
                    ?? $response['data']
                    ?? [];

                // Caso a AIService tenha retornado um array com chave 'text' contendo o JSON bruto
                if (empty($newExplanations) && isset($response['text'])) {
                    $nestedJson = json_decode(preg_replace('/^```(?:json)?\s+|\s+```$/i', '', trim($response['text'])), true);
                    $newExplanations = $nestedJson['errors_explanation'] ?? $nestedJson['explanations'] ?? $nestedJson ?? [];
                }
                
                // Se for array de objetos mas sem chave pai (root array)
                if (empty($newExplanations) && isset($response[0]) && is_array($response[0])) {
                    $newExplanations = $response;
                }

                // Normalizar IDs para garantir match (String vs Int)
                foreach ($newExplanations as &$explanation) {
                    if (isset($explanation['question_id'])) {
                        $explanation['question_id'] = (string) $explanation['question_id'];
                    }
                }
                
                // Refresh model to get latest data from DB
                $correction->refresh();
                $existingData = $correction->correction_data ?? [];
                $existingExplanations = $existingData['errors_explanation'] ?? [];
                
                $mergedExplanations = array_merge($existingExplanations, $newExplanations);
                
                // Update local accumulator
                $accumulatedExplanations = $mergedExplanations;

                // Persistência Incremental
                $correction->update([
                    'ai_provider' => $providerUsed,
                    'input_tokens' => $totalInput,
                    'output_tokens' => $totalOutput,
                    'total_tokens' => $totalInput + $totalOutput,
                    'tokens_used' => $totalInput + $totalOutput,
                    'correction_data' => [
                        'total_correct' => $this->simulation->answers->where('is_correct', true)->count(),
                        'total_questions' => $totalQuestions,
                        'errors_explanation' => $mergedExplanations
                    ]
                ]);
            } else {
                Log::error("Falha ao processar lote {$currentBatch} da simulação #{$this->simulation->id}. Pulando para o próximo.");
            }
        }

        // 4. Finalização e Cálculos Locais
        $correctAnswers = $this->simulation->answers->where('is_correct', true)->count();
        $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;

        // Calcular scores por matéria
        $scoresBySubject = [];
        $answersBySubject = $this->simulation->answers->groupBy('question.subject');
        foreach ($answersBySubject as $subject => $answers) {
            $total = $answers->count();
            $correct = $answers->where('is_correct', true)->count();
            $scoresBySubject[$subject] = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
        }

        // Finalizar Correção
        $correction->update([
            'corrected_at' => now(),
            // Dados já estão atualizados pelo loop
        ]);

        Log::info("Correção #{$this->simulation->id} finalizada com sucesso. Total Tokens: " . ($totalInput + $totalOutput));

        // 5. Atualizar Estatísticas do Usuário para o Plano de Estudos
        try {
            $studyPlanService = app(\App\Services\StudyPlanService::class);
            $studyPlanService->updateUserStats($user, $this->simulation);
            Log::info("Estatísticas do usuário {$user->id} atualizadas com sucesso.");
        } catch (\Exception $e) {
            Log::error("Falha ao atualizar estatísticas do usuário: " . $e->getMessage());
        }
    }
}
