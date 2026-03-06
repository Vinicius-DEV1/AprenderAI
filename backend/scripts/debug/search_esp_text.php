<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $results = \App\Models\Question::where('theme', 'like', '%esp%')
        ->orWhere('topic', 'like', '%esp%')
        ->orWhere('statement', 'like', '%espanhol%')
        ->limit(10)
        ->get(['id', 'theme', 'topic', 'year', 'institution']);

    echo "Questions found related to Spanish:\n";
    foreach ($results as $r) {
        echo "ID: {$r->id} | Theme: {$r->theme} | Topic: {$r->topic} | Year: {$r->year} | Institution: {$r->institution}\n";
    }

    if ($results->isEmpty()) {
        echo "No questions found matching 'esp' in text columns.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
