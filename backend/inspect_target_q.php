<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;

$qId = 2534;
$q = Question::with('subjects', 'topics')->find($qId);

if (!$q) {
    echo "Question $qId not found.\n";
    exit;
}

echo "Question ID: {$q->id}\n";
echo "Organization: {$q->organization}\n";
echo "Review Status: {$q->review_status}\n";
echo "Is Active: " . ($q->is_active ? 'YES' : 'NO') . "\n";
echo "Reasoning: |" . ($q->difficulty_reasoning ?? 'EMPTY') . "|\n";
echo "Explanation: |" . ($q->explanation ?? 'EMPTY') . "|\n";
echo "Subjects: " . $q->subjects->pluck('name')->implode(', ') . "\n";
echo "Topics: " . $q->topics->pluck('name')->implode(', ') . "\n";
echo "Published Scope: " . (Question::published()->where('id', $qId)->exists() ? 'YES' : 'NO') . "\n";
