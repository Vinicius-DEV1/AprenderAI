<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Simulation;
use App\Models\UserQuestionAnswer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();
try {
    $user = User::firstOrCreate(
        ['email' => 'admin@aprenderai.com'],
        ['name' => 'Admin AprenderAI', 'password' => Hash::make('Aprova@123'), 'role' => 'admin']
    );

    // Ensure user has admin role
    $user->role = 'admin';
    $user->save();

    // Create a simulation and answers to satisfy prerequisites
    $sim = Simulation::create([
        'user_id' => $user->id,
        'type' => 'general',
        'configuration' => json_encode([]),
        'status' => 'completed',
        'score' => 70,
        'completed_at' => now(),
        'total_questions' => 90,
        'correct_answers' => 63
    ]);

    for ($i = 1; $i <= 60; $i++) {
        UserQuestionAnswer::create([
            'user_id' => $user->id,
            'question_id' => $i,
            'simulation_id' => $sim->id,
            'selected_answer' => 'A',
            'is_correct' => rand(0, 1),
            'time_spent_seconds' => rand(30, 120),
            'answered_at' => now()
        ]);
    }

    DB::commit();
    echo "SUCCESS: Admin user seeded with answers.\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
