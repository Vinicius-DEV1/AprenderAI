<?php

use App\Models\User;
use App\Models\Simulation;
use App\Models\Question;
use App\Models\SimulationAnswer;
use Illuminate\Support\Facades\DB;

echo "--- Checking Simulations ---\n";

$totalSims = Simulation::count();
echo "Total Simulations in DB: {$totalSims}\n";

$users = User::all();
foreach ($users as $u) {
    echo "User {$u->id} ({$u->email}): " . $u->simulations()->count() . " sims\n";
}

if ($totalSims == 0) {
    echo "\n--- Creating Dummy Simulation for User 1 ---\n";
    $user = User::first();
    if (!$user) {
        echo "Creating user...\n";
        $user = User::factory()->create();
    }

    // Ensure we have questions with topics
    if (Question::count() == 0) {
        echo "Creating questions...\n";
        Question::factory(10)->create(['topic' => 'Algebra']);
    } else {
        // Update existing to have topic if needed (already done by fix_stats.php)
        Question::whereNull('topic')->update(['topic' => 'Geral']);
    }

    $questions = Question::take(5)->get();

    $sim = Simulation::create([
        'user_id' => $user->id,
        'type' => 'enem',
        'status' => 'in_progress',
        'started_at' => now(),
    ]);

    foreach ($questions as $q) {
        SimulationAnswer::create([
            'simulation_id' => $sim->id,
            'question_id' => $q->id,
            'user_answer' => $q->correct_answer, // All correct
            'is_correct' => true,
        ]);
    }

    echo "Created Simulation {$sim->id} with 5 correct answers.\n";

    echo "Finishing Simulation...\n";
    $sim->finishSimulation();
    echo "Simulation finished. Status: {$sim->status}\n";

    // Trigger stats update manually as Controller would via Job
    echo "Updating User Stats...\n";
    $service = app(\App\Services\StudyPlanService::class);
    $sim->load('answers.question');
    $service->updateUserStats($user, $sim);

    echo "Stats updated.\n";
}

echo "--- Done ---\n";
