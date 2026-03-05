<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionAlternative;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Models\Simulation;
use App\Models\SimulationAnswer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * ESTE SEEDER É TEMPORÁRIO E DEVE SER DELETADO APÓS OS TESTES.
 * Objetivo: Testar os módulos de simulado e plano de estudo com volume de dados.
 */
class TempTestingSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('ESTE SEEDER É TEMPORÁRIO E SERÁ DELETADO EM BREVE.');

        // 1. Garantir Matérias e Tópicos
        $portugues = Subject::firstOrCreate(['slug' => 'portugues'], ['name' => 'PORTUGUÊS', 'type' => 'concurso']);
        $matematica = Subject::firstOrCreate(['slug' => 'matematica'], ['name' => 'MATEMÁTICA', 'type' => 'concurso']);

        $topicoPort = Topic::firstOrCreate(['slug' => 'gramatica-geral'], ['name' => 'GRAMÁTICA GERAL']);
        $topicoMat = Topic::firstOrCreate(['slug' => 'algebra-basica'], ['name' => 'ÁLGEBRA BÁSICA']);

        $this->command->info('Criando 100 questões de Português...');
        $this->createQuestions($portugues, $topicoPort, 100, 'Português');

        $this->command->info('Criando 100 questões de Matemática...');
        $this->createQuestions($matematica, $topicoMat, 100, 'Matemática');

        // 2. Criar Simulados para o Admin
        $admin = User::where('email', 'admin@aprenderai.com')->first();

        if ($admin) {
            $this->command->info('Criando simulados para o usuário admin...');
            $this->createSimulationsForUser($admin, [$portugues, $matematica]);
        } else {
            $this->command->error('Usuário admin@aprenderai.com não encontrado. Pulei a criação de simulados.');
        }

        $this->command->info('Seeder temporário finalizado com sucesso!');
    }

    private function createQuestions(Subject $subject, Topic $topic, int $count, string $label): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $question = Question::create([
                'type' => 'concurso',
                'format' => 'multiple_choice',
                'difficulty' => collect(['easy', 'medium', 'hard'])->random(),
                'difficulty_reasoning' => "Raciocínio de dificuldade para questão de {$label} #{$i}. Esta questão aborda conceitos fundamentais de {$topic->name}.",
                'year' => 2024,
                'statement' => "Enunciado da questão de {$label} número {$i}. Qual a alternativa correta sobre {$topic->name}?",
                'explanation' => "Explicação detalhada da questão de {$label} #{$i}. O conceito de {$topic->name} é essencial para o concurso.",
                'source' => 'Simulado Temp',
                'organization' => 'Banca X',
                'institution' => 'Órgão Y',
                'review_status' => 'approved',
                'external_id' => 'TEMP_' . Str::upper(Str::random(10)),
            ]);

            $question->subjects()->attach($subject->id);
            $question->topics()->attach($topic->id);

            $labels = ['A', 'B', 'C', 'D', 'E'];
            $correctLetter = collect($labels)->random();

            foreach ($labels as $letter) {
                QuestionAlternative::create([
                    'question_id' => $question->id,
                    'label' => $letter,
                    'content' => "Conteúdo da alternativa {$letter} para a questão #{$i} de {$label}.",
                    'is_correct' => ($letter === $correctLetter),
                ]);
            }
        }
    }

    private function createSimulationsForUser(User $user, array $subjects): void
    {
        // Criar 3 simulados de exemplo
        for ($s = 1; $s <= 3; $s++) {
            $startedAt = Carbon::now()->subDays(4 - $s)->subHours(rand(1, 10));
            $simulation = Simulation::create([
                'user_id' => $user->id,
                'type' => 'custom',
                'configuration' => [
                    'subjects' => collect($subjects)->pluck('id')->toArray(),
                    'question_count' => 20,
                ],
                'started_at' => $startedAt,
                'finished_at' => (clone $startedAt)->addMinutes(rand(30, 90)),
                'status' => 'finished',
                'score' => rand(60, 95),
                'scores_by_subject' => [
                    'Português' => rand(70, 100),
                    'Matemática' => rand(50, 90),
                ],
                'analysis_by_theme' => [
                    'Gramática' => 'Bom desempenho',
                    'Álgebra' => 'Necessita revisão',
                ],
            ]);

            // Pegar algumas questões criadas
            $questions = Question::whereIn('source', ['Simulado Temp'])->inRandomOrder()->limit(20)->get();

            foreach ($questions as $index => $question) {
                $isCorrect = (rand(0, 10) > 3); // 70% de chance de acerto
                $correctAlt = $question->alternatives()->where('is_correct', true)->first();
                $wrongAlt = $question->alternatives()->where('is_correct', false)->first();

                SimulationAnswer::create([
                    'simulation_id' => $simulation->id,
                    'question_id' => $question->id,
                    'selected_alternative_id' => $isCorrect ? $correctAlt->id : $wrongAlt->id,
                    'is_correct' => $isCorrect,
                    'time_spent' => rand(30, 200),
                    'order' => $index + 1,
                ]);
            }

            // Atualizar time_elapsed
            $simulation->update([
                'time_elapsed' => $simulation->finished_at->diffInSeconds($simulation->started_at),
            ]);
        }
    }
}
