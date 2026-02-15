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

            // Calcular score mesmo sem IA (baseado em is_correct já salvo)
            $totalQuestions = $this->simulation->answers->count();
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

            $this->simulation->update([
                'status' => 'corrected',
                'score' => $score,
                'scores_by_subject' => $scoresBySubject,
            ]);

            return;
        }

        // Calcular score com base nos dados
        $totalQuestions = $this->simulation->answers->count();
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

        // Salvar Correção da IA
        Correction::create([
            'correctable_type' => Simulation::class,
            'correctable_id' => $this->simulation->id,
            'ai_provider' => $result['provider'],
            'correction_data' => $result['response'],
            'input_tokens' => $result['usage']['input_tokens'] ?? 0,
            'output_tokens' => $result['usage']['output_tokens'] ?? 0,
            'total_tokens' => $result['usage']['total_tokens'] ?? 0,
            'tokens_used' => $result['usage']['total_tokens'] ?? 0, // Legacy fallback
            'corrected_at' => now(),
        ]);

        $this->simulation->update([
            'status' => 'corrected',
            'score' => $score,
            'scores_by_subject' => $scoresBySubject,
        ]);

        // Enviar E-mail
        try {
            Mail::to($user)->send(new SimulationCorrectedMail($this->simulation));
        } catch (\Exception $e) {
            Log::error("Erro ao enviar email de correção: " . $e->getMessage());
        }
    }
}
