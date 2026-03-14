<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;

$questions = Question::where('review_status', 'approved')
    ->take(5)
    ->get();

foreach ($questions as $q) {
    echo "ID: {$q->id}\n";
    echo "Review Status: {$q->review_status}\n";
    echo "Difficulty: {$q->difficulty}\n";
    echo "Reasoning: |" . ($q->difficulty_reasoning ?? 'NULL') . "|\n";
    echo "Explanation: |" . ($q->explanation ?? 'NULL') . "|\n";
    echo "Subjects Count: " . $q->subjects->count() . "\n";
    echo "Topics Count: " . $q->topics->count() . "\n";
    echo "Published Scope: " . (Question::published()->where('id', $q->id)->exists() ? 'YES' : 'NO') . "\n";
    echo "-------------------\n";
}
