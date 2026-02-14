<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $plans = \App\Models\Plan::count();
    echo "Plans: $plans\n";

    $questions = \App\Models\Question::count();
    echo "Questions: $questions\n";

    $users = \App\Models\User::count();
    echo "Users: $users\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
