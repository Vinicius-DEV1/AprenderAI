<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Simulation;
use App\Models\SimulationAnswer;
use App\Models\Essay;
use App\Models\UserStat;
use App\Models\Question;
use App\Models\Correction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetContentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aprovaai:reset-content';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpa SOMENTE dados de simulações e questões do ENEM. Preserva Usuários e Concursos.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando limpeza CIRÚRGICA do ENEM (MySQL Estrito)...');

        // 1. Verificação de Segurança (Users)
        $userCount = User::count();
        $this->info("Usuários encontrados: {$userCount} (SERÃO PRESERVADOS)");

        // 2. Verificação de Segurança (Concurso)
        $concursoCount = Question::where('type', 'concurso')->count();
        $this->info("Questões de Concurso: {$concursoCount} (SERÃO PRESERVADAS)");

        // 3. Alvo (Enem)
        $enemCount = Question::where('type', 'enem')->count();
        $simEnemCount = Simulation::where('type', 'enem')->count();
        
        $this->info("------------------------------------------------");
        $this->info("ALVOS PARA EXCLUSÃO:");
        $this->info("- Questões ENEM: {$enemCount}");
        $this->info("- Simulados ENEM: {$simEnemCount}");
        $this->info("- Todas as Redações (Essays)");
        $this->info("- Estatísticas de Usuário (UserStats)");
        $this->info("------------------------------------------------");

        if (!$this->confirm('CONFIRMA A EXCLUSÃO DESSES DADOS? (Isso não pode ser desfeito)', true)) {
            $this->info('Operação cancelada.');
            return;
        }

        // 3. MySQL/SQLite Safe Reset
        try {
            $connection = DB::getDefaultConnection(); // 'sqlite' or 'mysql'
            $driver = DB::connection($connection)->getDriverName();

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }

            // A. Limpar Redações (Geral)
            $this->line('Limpando Redações (Essays)...');
            if (Schema::hasTable('essays')) {
                Essay::truncate();
            }

            // B. Limpar Estatísticas
            $this->line('Limpando UserStats...');
            if (Schema::hasTable('user_stats')) {
                UserStat::truncate();
            }

            // C. Limpar Simulados ENEM
            $this->line('Identificando Simulados ENEM para exclusão...');
            
            $enemSimIds = Simulation::where('type', 'enem')->pluck('id');
            
            if ($enemSimIds->isNotEmpty()) {
                $this->line("Excluindo respostas de " . $enemSimIds->count() . " simulados ENEM...");
                SimulationAnswer::whereIn('simulation_id', $enemSimIds)->delete();

                $this->line("Excluindo correções de simulados ENEM...");
                Correction::where('correctable_type', Simulation::class)
                    ->whereIn('correctable_id', $enemSimIds)
                    ->delete();

                $this->line("Excluindo simulados ENEM...");
                Simulation::whereIn('id', $enemSimIds)->delete();
            } else {
                $this->line("Nenhum simulado ENEM encontrado.");
            }

            // D. Limpar Questões ENEM
            $this->line('Excluindo Questões ENEM...');
            $deletedQuestions = Question::where('type', 'enem')->delete();
            $this->line("{$deletedQuestions} questões ENEM excluídas.");

        } catch (\Exception $e) {
            $this->error('Erro durante a limpeza: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        } finally {
            // SEMPRE reativar FKs
            if (isset($driver) && $driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }

        // Validação Pós-Limpeza
        $finalUserCount = User::count();
        $finalConcursoCount = Question::where('type', 'concurso')->count();
        $finalEnemCount = Question::where('type', 'enem')->count();
        $finalEnemSimCount = Simulation::where('type', 'enem')->count();

        if ($finalUserCount !== $userCount) {
            $this->error("CRÍTICO: Contagem de usuários mudou! Antes: {$userCount}, Depois: {$finalUserCount}");
        }

        if ($finalConcursoCount !== $concursoCount) {
            $this->error("CRÍTICO: Contagem de questões CONCURSO mudou! Antes: {$concursoCount}, Depois: {$finalConcursoCount}");
        }

        $this->info('------------------------------------------------');
        $this->info('Limpeza concluída com sucesso!');
        $this->info("Usuários preservados: {$finalUserCount}");
        $this->info("Questões Concurso preservadas: {$finalConcursoCount}");
        $this->info("Questões ENEM restantes: {$finalEnemCount} (Esperado: 0)");
        $this->info("Simulados ENEM restantes: {$finalEnemSimCount} (Esperado: 0)");
        $this->info('------------------------------------------------');
    }
}
