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

        // Preparar dados para IA
        $questionsAndAnswers = $this->simulation->answers->map(function ($answer) {
            return [
                'question_id' => $answer->question_id,
                'statement' => $answer->question->statement,
                'alternatives' => $answer->question->alternatives,
                'user_answer' => $answer->user_answer,
                'correct_answer' => $answer->question->correct_answer,
            ];
        })->toArray();

        // Chamar Serviço de IA
        $result = $aiService->correctSimulation($questionsAndAnswers, $plan);

        if (!$result) {
            Log::warning("Falha ao corrigir simulação {$this->simulation->id}: Sem resposta da IA ou sem chave.");
            // Mesmo sem IA, calcular nota baseada no gabarito (já temos is_correct no banco se foi salvo antes?
            // SimulationController::saveAnswer salva is_correct baseada na comparação simples.
            // Então a nota básica JÁ EXISTE.
            // A IA serve para o feedback detalhado.

            $this->simulation->update(['status' => 'corrected']); // Marca como corrigido mesmo sem IA extra
            return;
        }

        // Salvar Correção da IA
        Correction::create([
            'correctable_type' => Simulation::class,
            'correctable_id' => $this->simulation->id,
            'ai_provider' => $result['provider'],
            'correction_data' => $result['response'],
            'corrected_at' => now(),
        ]);

        $this->simulation->update(['status' => 'corrected']);

        // Enviar E-mail
        try {
            Mail::to($user)->send(new SimulationCorrectedMail($this->simulation));
        } catch (\Exception $e) {
            Log::error("Erro ao enviar email de correção: " . $e->getMessage());
        }
    }
}
