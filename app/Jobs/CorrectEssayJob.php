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
        $result = $aiService->correctEssay($this->essay->title, $this->essay->content, $plan, $this->essay->user_id);

        if (!$result) {
            Log::warning("Falha ao corrigir redação {$this->essay->id}: Sem resposta da IA ou sem chave.");
            
            // FALLBACK: Marcar erro visual para o usuário ou criar correção de erro
            // Se criarmos correção com 'error', o front precisa saber lidar.
            // Pela segurança, vamos criar uma correção com mensagem de indisponibilidade
            
            Correction::create([
                'correctable_type' => Essay::class,
                'correctable_id' => $this->essay->id,
                'ai_provider' => 'system_fallback',
                'correction_data' => [
                    'score' => 0,
                    'competencies' => [],
                    'feedback' => 'O sistema de correção por IA está instável no momento. Por favor, tente novamente mais tarde ou entre em contato com o suporte.',
                    'detailed_suggestions' => [],
                    'example_essay' => ''
                ],
                'corrected_at' => now(),
            ]);

            $this->essay->update([
                'status' => 'corrected', // Ou 'error' se o sistema suportar
                'score' => 0,
            ]);
            
            return;
        }

        // Caminho Feliz
        Correction::create([
            'correctable_type' => Essay::class,
            'correctable_id' => $this->essay->id,
            'ai_provider' => $result['provider'],
            'correction_data' => $result['response'],
            'corrected_at' => now(),
        ]);

        // Extrair nota do JSON se possível
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
