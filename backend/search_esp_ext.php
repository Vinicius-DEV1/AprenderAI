<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $results = \App\Models\Question::where('knowledge_area', 'like', '%estrangeira%')
        ->orWhere('theme', 'like', '%estrangeira%')
        ->orWhere('topic', 'like', '%estrangeira%')
        ->limit(10)
        ->get(['id', 'knowledge_area', 'theme', 'topic', 'statement']);

    echo "Questions found with 'estrangeira' in metadata:\n";
    foreach ($results as $r) {
        $shortStat = substr($r->statement, 0, 50);
        echo "ID: {$r->id} | Area: {$r->knowledge_area} | Theme: {$r->theme} | Topic: {$r->topic} | Statement: {$shortStat}...\n";
    }

    if ($results->isEmpty()) {
        echo "No questions found with 'estrangeira'.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
