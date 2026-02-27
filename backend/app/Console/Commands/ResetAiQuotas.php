<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ResetAiQuotas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:reset-quotas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reseta a cota de perguntas de IA dos usuários a cada 30 dias';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando reset de cotas de IA...');

        // Usuários que nunca tiveram reset ou cujo reset foi há mais de 30 dias
        $cutoffDate = now()->subDays(30);

        $query = \App\Models\User::whereNull('last_reset_at')
            ->orWhere('last_reset_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count > 0) {
            $query->update([
                'ai_questions_count' => 0,
                'last_reset_at' => now(),
            ]);
            $this->info("Cotas resetadas para {$count} usuários.");
        }
        else {
            $this->info('Nenhum usuário precisando de reset hoje.');
        }

        return Command::SUCCESS;
    }
}
