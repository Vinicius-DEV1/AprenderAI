<?php

use App\Models\Simulation;
use App\Models\SimulationAnswer;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "--- DB CHECK ---\n";

$simulation = Simulation::where('user_id', 3)->latest()->first();

if (!$simulation) {
    echo "No simulation found for user 3.\n";
    exit;
}

echo "Simulation ID: " . $simulation->id . "\n";
echo "Status: " . $simulation->status . "\n";
echo "Created At: " . $simulation->created_at . "\n";

$totalAnswers = DB::table('simulation_answers')->where('simulation_id', $simulation->id)->count();
echo "Total Answers: " . $totalAnswers . "\n";

$sources = DB::table('questions')
    ->join('simulation_answers', 'questions.id', '=', 'simulation_answers.question_id')
    ->where('simulation_answers.simulation_id', $simulation->id)
    ->select('questions.source', DB::raw('count(*) as count'))
    ->groupBy('questions.source')
    ->get();

foreach ($sources as $s) {
    echo "{$s->source}: {$s->count}\n";
}
