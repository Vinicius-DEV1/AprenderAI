<?php

use App\Models\User;
use App\Models\Simulation;
use App\Services\SimulationCreationService;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Clean previous test data
Simulation::where('user_id', 3)->delete();

echo "--- STRICT VERIFICATION START ---\n";

$user = User::find(3); // 'plus@aprovaai.test'
echo "User Plan: " . ($user->plan()->first()->slug ?? 'None') . "\n";

$service = app(SimulationCreationService::class);

echo "Creating ENEM simulation (90 questions)...\n";
$start = microtime(true);

try {
    $simulation = $service->createSimulation($user, [
        'type' => 'enem',
        'total_questions' => 90,
        'subject_distribution' => [
            'português' => 45,
            'matemática' => 45
        ],
        'include_essay' => false
    ]);

    $end = microtime(true);
    $duration = $end - $start;

    echo "Creation Time: " . number_format($duration, 2) . "s\n";
    echo "Simulation ID: " . $simulation->id . "\n";
    echo "Simulation Status: " . $simulation->status . "\n";

    // VERIFICATIONS
    $totalAnswers = DB::table('simulation_answers')->where('simulation_id', $simulation->id)->count();
    echo "Total Answers: " . $totalAnswers . "\n";

    if ($totalAnswers !== 90) {
        echo "FAIL: Expected 90 answers, got $totalAnswers\n";
        exit(1);
    }

    if ($simulation->status === 'pending') {
        echo "FAIL: Simulation stuck in pending status.\n";
        exit(1);
    }

    // Check Source Distribution
    $sources = DB::table('questions')
        ->join('simulation_answers', 'questions.id', '=', 'simulation_answers.question_id')
        ->where('simulation_answers.simulation_id', $simulation->id)
        ->select('questions.source', DB::raw('count(*) as count'))
        ->groupBy('questions.source')
        ->get();

    echo "\nSource Distribution:\n";
    foreach ($sources as $s) {
        echo "{$s->source}: {$s->count}\n";
    }

    echo "\nSUCCESS: Simulation created correctly.\n";

} catch (\Exception $e) {
    echo "CRITICAL FAILURE: " . $e->getMessage() . "\n";
    exit(1);
}
