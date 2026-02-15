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
    protected $description = 'Limpa dados de simulações e questões ENEM, MANTENDO usuários e concurso';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando limpeza segura do banco de dados (MySQL Estrito)...');

        // 1. Verificação de Segurança (Users)
        $userCount = User::count();
        if ($userCount === 0) {
            $this->warn('Nenhum usuário encontrado. Continuando mesmo assim...');
        } else {
            $this->info("Encontrados {$userCount} usuários. Eles SERÃO PRESERVADOS.");
        }

        // 2. Verificação de Segurança (Concurso)
        $concursoCount = Question::where('type', 'concurso')->count();
        $this->info("Questões de Concurso atuais: {$concursoCount}. Elas SERÃO PRESERVADAS.");

        $enemCount = Question::where('type', 'enem')->count();
        $this->info("Questões ENEM atuais (serão apagadas): {$enemCount}");

        if (!$this->confirm('Deseja prosseguir com a limpeza? ISSO APAGARÁ SIMULADOS E QUESTÕES ENEM.', true)) {
            $this->info('Operação cancelada.');
            return;
        }

        // 3. MySQL Safe Reset (SET FOREIGN_KEY_CHECKS=0)
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Limpar Tabelas de Simulado (Ordem FK)
            $this->line('Limpando respostas de simulados...');
            if (Schema::hasTable('simulation_answers')) {
                SimulationAnswer::truncate();
            }

            $this->line('Limpando correções...');
            if (Schema::hasTable('corrections')) {
                Correction::truncate();
            }

            $this->line('Limpando redações...');
            if (Schema::hasTable('essays')) {
                Essay::truncate();
            }

            $this->line('Limpando simulados...');
            if (Schema::hasTable('simulations')) {
                Simulation::truncate(); // Reset ID auto-increment
            }

            $this->line('Limpando estatísticas de usuário...');
            if (Schema::hasTable('user_stats')) {
                UserStat::truncate();
            }

            // Limpar Questões ENEM (Delete, não Truncate, para filtrar)
            $this->line('Apagando questões ENEM...');
            Question::where('type', 'enem')->delete();

        } catch (\Exception $e) {
            $this->error('Erro durante a limpeza: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        } finally {
            // SEMPRE reativar FKs
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Validação Pós-Limpeza
        $finalUserCount = User::count();
        $finalConcursoCount = Question::where('type', 'concurso')->count();
        $finalEnemCount = Question::where('type', 'enem')->count();

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
        $this->info("Questões ENEM apagadas (restantes): {$finalEnemCount}");
        $this->info('------------------------------------------------');
    }
}
