<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\UserQuestionAnswer;
use App\Models\UserDailyStat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EngagementAndSimulationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@aprenderai.com')->first();
        if (!$admin) {
            $this->command->error("Admin user not found!");
            return;
        }

        // 1. Ensure we have some subjects and topics
        $subjects = Subject::all();
        if ($subjects->isEmpty()) {
            $subjects = collect([
                Subject::create(['name' => 'Matemática', 'slug' => 'matematica']),
                Subject::create(['name' => 'Português', 'slug' => 'portugues']),
                Subject::create(['name' => 'História', 'slug' => 'historia']),
            ]);
        }

        $topics = Topic::all();
        if ($topics->isEmpty()) {
            $topics = collect([
                Topic::create(['name' => 'Álgebra', 'slug' => 'algebra']),
                Topic::create(['name' => 'Gramática', 'slug' => 'gramatica']),
                Topic::create(['name' => 'Brasil Colônia', 'slug' => 'brasil-colonia']),
            ]);
        }

        // 2. Create a bunch of questions
        $this->command->info("Creating 100 questions (ENEM & Concursos)...");

        // ENEM Questions
        Question::factory()->count(50)->create([
            'type' => 'enem',
            'institution' => 'INEP',
            'year' => 2023,
            'is_active' => true,
        ])->each(function ($q) use ($subjects, $topics) {
            $q->subjects()->attach($subjects->random()->id);
            $q->topics()->attach($topics->random()->id);
        });

        // Concurso Questions
        Question::factory()->count(50)->concurso()->create([
            'type' => 'concurso',
            'year' => 2023,
            'organization' => 'FGV',
            'institution' => 'Senado Federal',
            'is_active' => true,
        ])->each(function ($q) use ($subjects, $topics) {
            $q->subjects()->attach($subjects->random()->id);
            $q->topics()->attach($topics->random()->id);
        });

        // 3. Populate Engagement Data for Admin (Last 14 days)
        $this->command->info("Populating engagement data for admin...");

        // Clear previous stats to avoid duplicates
        UserDailyStat::where('user_id', $admin->id)->delete();

        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);

            // Random number of questions per day (0 to 30)
            // Ensure a streak for the last 7 days
            $count = ($i < 7) ? rand(15, 30) : rand(0, 20);

            if ($count > 0) {
                $correct = rand((int) ($count * 0.7), $count); // High accuracy

                UserDailyStat::create([
                    'user_id' => $admin->id,
                    'date' => $date->toDateString(),
                    'total_answered' => $count,
                    'total_correct' => $correct,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                // Create individual answers
                $dailyQuestions = Question::inRandomOrder()->limit($count)->get();
                foreach ($dailyQuestions as $idx => $q) {
                    UserQuestionAnswer::create([
                        'user_id' => $admin->id,
                        'question_id' => $q->id,
                        'selected_answer' => ($idx < $correct) ? 'A' : 'B', // 'A' is correct in factory
                        'is_correct' => $idx < $correct,
                        'answered_at' => $date->copy()->addMinutes(rand(1, 480)),
                    ]);
                }
            }
        }

        $this->command->info("Data seeded successfully for admin@aprenderai.com!");
    }
}
