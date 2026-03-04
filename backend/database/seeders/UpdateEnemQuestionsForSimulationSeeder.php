<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class UpdateEnemQuestionsForSimulationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure subjects exist
        $math = Subject::firstOrCreate(['name' => 'Matemática', 'slug' => 'matematica']);
        $port = Subject::firstOrCreate(['name' => 'Língua Portuguesa', 'slug' => 'lingua-portuguesa']);

        // 2. Get all ENEM questions
        $enemQuestions = Question::where('type', 'enem')->get();

        if ($enemQuestions->isEmpty()) {
            $this->command->warn("No ENEM questions found to update.");
            return;
        }

        $this->command->info("Updating " . $enemQuestions->count() . " ENEM questions...");

        // 3. Divide them and attach subjects
        $half = (int) ($enemQuestions->count() / 2);

        foreach ($enemQuestions as $index => $question) {
            // Clear existing subjects to avoid duplicates or mixed subjects if that's preferred
            $question->subjects()->detach();

            if ($index < $half) {
                $question->subjects()->attach($math->id);
            } else {
                $question->subjects()->attach($port->id);
            }
        }

        $this->command->info("ENEM questions updated: 1-{$half} to Matemática, rest to Língua Portuguesa.");
    }
}
