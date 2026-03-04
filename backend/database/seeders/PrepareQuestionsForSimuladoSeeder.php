<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class PrepareQuestionsForSimuladoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Starting to force-publish ENEM questions...");

        // Ensure at least one topic exists
        $topic = Topic::firstOrCreate(['name' => 'Geral', 'slug' => 'geral']);

        $updatedCount = 0;

        Question::where('type', 'enem')->chunk(100, function ($questions) use (&$updatedCount, $topic) {
            foreach ($questions as $q) {
                $q->update([
                    'difficulty_reasoning' => $q->difficulty_reasoning ?: 'Dificuldade avaliada automaticamente para testes.',
                    'explanation' => $q->explanation ?: 'Explicação automática gerada para testes.',
                    'review_status' => 'approved',
                    'is_active' => true,
                ]);

                // Ensure it has at least one topic
                if ($q->topics()->count() === 0) {
                    $q->topics()->attach($topic->id);
                }

                $updatedCount++;
            }
        });

        $this->command->info("Successfully prepared $updatedCount ENEM questions for simulations.");
    }
}
