<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Simulation;
use App\Models\Essay;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class RealisticUsageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Generate or fetch test user
        $user = User::updateOrCreate(
            ['email' => 'aluno_teste@aprovaai.com'],
            [
                'name' => 'Aluno Realista Teste',
                'password' => Hash::make('Aprova@123'),
                'role' => 'user',
                'email_verified_at' => now(),
            ]
        );

        // Ensure idempotency by resetting only this specific user's realistic data pool
        $user->simulations()->delete();
        $user->essays()->delete();

        $this->command->info("Test User created/updated and previous simulation/essay data cleared.");

        // FIXED DATE for idempotency: 2026-01-01
        $baseDate = Carbon::create(2026, 1, 1, 10, 0, 0);

        // 2. Insert 10 Finished Simulations
        $simScores = [450, 480, 510, 520, 590, 610, 660, 710, 750, 800];
        for ($i = 0; $i < 10; $i++) {
            $simulationDate = $baseDate->copy()->addDays($i * 4);
            Simulation::updateOrCreate(
                [
                    'user_id' => $user->id,
                    // Treat created_at string representation uniquely for idempotency purposes
                    'created_at' => $simulationDate->format('Y-m-d H:i:s'),
                    'type' => 'enem'
                ],
                [
                    'status' => 'finished',
                    'score' => $simScores[$i],
                    'updated_at' => $simulationDate->format('Y-m-d H:i:s'),
                    'configuration' => []
                ]
            );
        }

        $this->command->info("10 Simulations seeded.");

        // 3. Insert 7 ENEM Essays
        $enemScores = [500, 560, 640, 720, 780, 840, 920];
        for ($i = 0; $i < 7; $i++) {
            $essayDate = $baseDate->copy()->addDays($i * 6);
            Essay::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'enem',
                    'created_at' => $essayDate->format('Y-m-d H:i:s')
                ],
                [
                    'title' => 'Redação ENEM Simulada',
                    'content' => 'Conteúdo de teste ' . $i,
                    'topic_description' => 'Tema ' . $i,
                    'status' => 'corrected',
                    'score' => $enemScores[$i],
                    'feedback' => 'Feedback progressivo ' . ($i + 1),
                    'updated_at' => $essayDate->format('Y-m-d H:i:s')
                ]
            );
        }

        $this->command->info("7 ENEM Essays seeded.");

        // 4. Insert 5 Concurso Essays 
        $concursoScores = [4.5, 5.2, 6.8, 8.1, 9.4]; // Assuming score is out of 10 usually for concursos, or out of 100
        for ($i = 0; $i < 5; $i++) {
            $concursoDate = $baseDate->copy()->addDays($i * 8);
            Essay::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'type' => 'concurso',
                    'created_at' => $concursoDate->format('Y-m-d H:i:s')
                ],
                [
                    'title' => 'Redação Concurso Simulada',
                    'content' => 'Conteúdo de teste concurso ' . $i,
                    'topic_description' => 'Tema Concurso ' . $i,
                    'status' => 'corrected',
                    'score' => $concursoScores[$i],
                    'feedback' => 'Feedback progressivo concurso ' . ($i + 1),
                    'updated_at' => $concursoDate->format('Y-m-d H:i:s')
                ]
            );
        }

        $this->command->info("5 Concurso Essays seeded.");
        $this->command->info("Realistic Usage Seeder successfully executed!");
    }
}
