<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
if (!$user) {
    echo "No user found\n";
    exit;
}
echo "User ID: " . $user->id . "\n";
$user->load(['plan', 'subscriptions.plan', 'logs' => fn($q) => $q->latest()->take(20)]);
$user->loadCount(['simulations', 'essays', 'promptLogs']);

echo "\n--- Subscriptions ---\n";
echo json_encode($user->subscriptions);

echo "\n\n--- Logs ---\n";
echo json_encode($user->logs);

echo "\n\n--- AI Prompt Logs ---\n";
echo json_encode($user->promptLogs()->latest()->take(2)->get());

echo "\n\n--- AI Stats ---\n";
echo "Count: " . $user->prompt_logs_count . "\n";
echo "Cost: " . $user->promptLogs()->sum('estimated_cost') . "\n";
