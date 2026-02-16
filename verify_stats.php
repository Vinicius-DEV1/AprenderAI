<?php

use App\Models\User;
use App\Models\UserTopicStat;
use App\Models\Simulation;

echo "--- Verifying Stats ---\n";

// Find user with most finished simulations
$u = User::withCount([
    'simulations' => function ($q) {
        $q->where('status', 'finished');
    }
])->orderBy('simulations_count', 'desc')->first();

if (!$u || $u->simulations_count == 0) {
    echo "No user found with finished simulations.\n";
    // Check pending?
    $u = User::withCount('simulations')->orderBy('simulations_count', 'desc')->first();
    echo "User {$u->id} has {$u->simulations_count} total sims. Checking statuses...\n";
    $statuses = $u->simulations()->pluck('status')->unique();
    echo "Statuses: " . $statuses->implode(', ') . "\n";
    exit;
}

echo "UserID: {$u->id} (Sims: {$u->simulations_count})\n";
$count = UserTopicStat::where('user_id', $u->id)->count();
echo "Total Stats Count: {$count}\n";

echo "\n--- Top 5 Weakest ---\n";
$weak = UserTopicStat::where('user_id', $u->id)
    ->orderBy('accuracy', 'asc')
    ->take(5)
    ->get(['subject', 'topic', 'attempts', 'accuracy']);

foreach ($weak as $s) {
    printf("%s - %s: %d attempts, %.1f%%\n", $s->subject, $s->topic, $s->attempts, $s->accuracy);
}

echo "\n--- Top 5 Strongest ---\n";
$strong = UserTopicStat::where('user_id', $u->id)
    ->orderBy('accuracy', 'desc')
    ->take(5)
    ->get(['subject', 'topic', 'attempts', 'accuracy']);

foreach ($strong as $s) {
    printf("%s - %s: %d attempts, %.1f%%\n", $s->subject, $s->topic, $s->attempts, $s->accuracy);
}
echo "--- Done ---\n";
