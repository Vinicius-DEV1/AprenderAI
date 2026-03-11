<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Simulation;

$simulations = Simulation::withCount('answers')->with('answers')->limit(5)->get();

foreach ($simulations as $s) {
    echo "ID: {$s->id}, Status: {$s->status}, Answers Count: {$s->answers_count}, Correct: " . $s->answers->where('is_correct', true)->count() . "\n";
}
