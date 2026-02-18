<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClassifyAiQuestions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'questions:classify-ai {--limit=10 : Número de questões para processar} {--all : Processar todas as questões (ignora o limite)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Classifica a dificuldade das questões usando IA';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\AIService $aiService)
    {
        $query = \App\Models\Question::whereNull('difficulty_reasoning');

        $total = $query->count();
        $limit = $this->option('all') ? $total : (int)$this->option('limit');

        if ($total === 0) {
            $this->info("Nenhuma questão pendente de classificação.");
            return 0;
        }

        $this->info("Iniciando classificação de {$limit} de {$total} questões...");

        $questions = $query->limit($limit)->get();
        $bar = $this->output->createProgressBar(count($questions));

        foreach ($questions as $question) {
            $aiService->evaluateQuestionDifficulty($question);
            $bar->advance();
            // Small sleep to avoid rate limits if many
            usleep(200000); // 200ms
        }

        $bar->finish();
        $this->newLine();
        $this->info("Classificacao concluida!");

        return 0;
    }
}
