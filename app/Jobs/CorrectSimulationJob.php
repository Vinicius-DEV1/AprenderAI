<?php

namespace App\Jobs;

use App\Models\Simulation;
use App\Models\Correction;
use App\Services\AIService;
use App\Mail\SimulationCorrectedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
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

        // 1. Preparar Todas as Questões
        $allQuestions = $this->simulation->answers->map(function ($answer) {
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

        // 3. Processamento em Lotes (Chunking) e Persistência Incremental
        $chunks = array_chunk($allQuestions, 5); // Lotes de 5
        $accumulatedExplanations = [];
        $totalInput = 0;
        $totalOutput = 0;
        $providerUsed = 'openai'; // Fallback default

        foreach ($chunks as $index => $chunk) {
            $currentBatch = $index + 1;
            $totalBatches = count($chunks);
            Log::info("Processando lote {$currentBatch} de {$totalBatches} para simulação #{$this->simulation->id}");

            // Call AI (Usage increment is handled in AIService::finally)
            $result = $aiService->correctSimulation($chunk, $plan);

            if ($result) {
                $providerUsed = $result['provider'];
                $response = $result['response'];
                $usage = $result['usage'];

                // Accumulate Tokens
                $totalInput += $usage['input_tokens'] ?? 0;
                $totalOutput += $usage['output_tokens'] ?? 0;

                // Merge Explanations
                if (isset($response['errors_explanation']) && is_array($response['errors_explanation'])) {
                    $accumulatedExplanations = array_merge($accumulatedExplanations, $response['errors_explanation']);
                }

                // Persistência Incremental (Salvar o progresso)
                $correction->update([
                    'ai_provider' => $providerUsed,
                    'input_tokens' => $totalInput,
                    'output_tokens' => $totalOutput,
                    'total_tokens' => $totalInput + $totalOutput,
                    'tokens_used' => $totalInput + $totalOutput, // Legacy
                    'correction_data' => [
                        'total_correct' => $this->simulation->answers->where('is_correct', true)->count(),
                        'total_questions' => $totalQuestions,
                        'errors_explanation' => $accumulatedExplanations
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

        // Atualizar Status da Simulação
        $this->simulation->update([
            'status' => 'corrected',
            'score' => $score,
            'scores_by_subject' => $scoresBySubject,
        ]);

        Log::info("Correção #{$this->simulation->id} finalizada com sucesso. Total Tokens: " . ($totalInput + $totalOutput));

        // Enviar E-mail
        try {
            Mail::to($user)->send(new SimulationCorrectedMail($this->simulation));
        } catch (\Exception $e) {
            Log::error("Erro ao enviar email de correção: " . $e->getMessage());
        }
    }
}
