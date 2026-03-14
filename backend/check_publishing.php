<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;

$approved = Question::where('review_status', 'approved')->count();
$active = Question::where('review_status', 'approved')->where('is_active', true)->count();
$published = Question::published()->count();

echo "Approved Questions: $approved\n";
echo "Active & Approved: $active\n";
echo "Published (scope): $published\n";

if ($approved > 0) {
    echo "\nReasoning for non-published (of approved ones):\n";
    $missing_diff_reason = Question::where('review_status', 'approved')->where(fn($q) => $q->whereNull('difficulty_reasoning')->orWhereRaw("TRIM(difficulty_reasoning) = ''"))->count();
    $missing_expl = Question::where('review_status', 'approved')->where(fn($q) => $q->whereNull('explanation')->orWhereRaw("TRIM(explanation) = ''"))->count();
    $missing_diff = Question::where('review_status', 'approved')->whereNull('difficulty')->count();
    $missing_subjects = Question::where('review_status', 'approved')->whereDoesntHave('subjects')->count();
    $missing_topics = Question::where('review_status', 'approved')->whereDoesntHave('topics')->count();

    echo "Missing difficulty_reasoning: $missing_diff_reason\n";
    echo "Missing explanation: $missing_expl\n";
    echo "Missing difficulty: $missing_diff\n";
    echo "Missing subjects: $missing_subjects\n";
    echo "Missing topics: $missing_topics\n";
}
