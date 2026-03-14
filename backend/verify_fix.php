<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;

// 1. Check if any question is approved but incomplete
$ghosts = Question::where('review_status', 'approved')->get()->filter(fn($q) => $q->isIncomplete());

echo "Ghosts (Approved but Incomplete): " . $ghosts->count() . "\n";
foreach ($ghosts as $g) {
    echo "- ID: {$g->id} | Missing: ";
    $missing = [];
    if (empty(trim($g->explanation ?? ''))) $missing[] = 'Explanation';
    if (empty(trim($g->difficulty_reasoning ?? ''))) $missing[] = 'Reasoning';
    if ($g->subjects()->doesntExist()) $missing[] = 'Subjects';
    if ($g->topics()->doesntExist()) $missing[] = 'Topics';
    echo implode(', ', $missing) . "\n";
}

// 2. Check publication scope consistency
$approvedAndCompleteCount = Question::where('review_status', 'approved')
    ->where('is_active', true)
    ->get()
    ->filter(fn($q) => !$q->isIncomplete())
    ->count();

$publishedCount = Question::published()->count();

echo "Approved, Active & Complete: $approvedAndCompleteCount\n";
echo "Published Scope Count: $publishedCount\n";

if ($approvedAndCompleteCount <= $publishedCount) {
    echo "SUCCESS: Publication scope is consistent with approved/complete items.\n";
} else {
    echo "WARNING: Discrepancy detected between approved items and published scope.\n";
}
