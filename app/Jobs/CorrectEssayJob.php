<?php

namespace App\Jobs;

use App\Models\Essay;
use App\Models\Correction;
use App\Services\AIService;
use App\Mail\EssayCorrectedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CorrectEssayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $essay;

    public function __construct(Essay $essay)
    {
        $this->essay = $essay;
    }

    public function handle(AIService $aiService): void
    {
        $user = $this->essay->user;
        $plan = $user->plan ? $user->plan->slug : 'free';

        // Chamar Serviço de IA
        $result = $aiService->correctEssay($this->essay->title, $this->essay->content, $plan);

        if (!$result) {
            Log::warning("Falha ao corrigir redação {$this->essay->id}: Sem resposta da IA ou sem chave.");
            // Redação fica pendente ou marca erro?
            // Vamos deixar pendente por enquanto para retry, ou falha.
            // Se falhar várias vezes, queue vai jogar para failed_jobs.
            return;
        }

        // Salvar Correção da IA
        Correction::create([
            'correctable_type' => Essay::class,
            'correctable_id' => $this->essay->id,
            'ai_provider' => $result['provider'],
            'correction_data' => $result['response'],
            'corrected_at' => now(),
        ]);

        // Extrair nota do JSON se possível para salvar no modelo Essay para fácil acesso
        $score = $result['response']['score'] ?? null;

        $this->essay->update([
            'status' => 'corrected',
            'score' => $score,
        ]);

        // Enviar E-mail
        try {
            Mail::to($user)->send(new EssayCorrectedMail($this->essay));
        } catch (\Exception $e) {
            Log::error("Erro ao enviar email de correção de redação: " . $e->getMessage());
        }
    }
}
