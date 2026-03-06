<?php
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create 60 UserQuestionAnswers for admin user
$admin = App\Models\User::where('email', 'admin@aprenderai.com')->first();
if (!$admin) { echo "Admin not found\n"; exit(1); }

// Check existing answers
$existing = App\Models\UserQuestionAnswer::where('user_id', $admin->id)->count();
echo "Existing answers: $existing\n";

if ($existing >= 50) {
    echo "Admin already has 50+ answers. No action needed.\n";
    exits(0);
}

// Get random questions
$questions = App\Models\Question::inRandomOrder()->limit(60)->get();
if ($questions->count() < 10) {
    echo "Not enough questions in database (found: " . $questions->count() . ")\n";
    exit(1);
}

$created = 0;
foreach ($questions as $q) {
    // Check if answer already exists
    $exists = App\Models\UserQuestionAnswer::where('user_id', $admin->id)
        ->where('question_id', $q->id)
        ->exists();
    if ($exists) continue;

    // Determine a random answer (50% chance of being correct)
    $isCorrect = rand(0, 1) === 1;
    $userAnswer = $isCorrect ? $q->correct_answer : ($q->correct_answer === 'A' ? 'B' : 'A');

    App\Models\UserQuestionAnswer::create([
        'user_id' => $admin->id,
        'question_id' => $q->id,
        'user_answer' => $userAnswer,
        'is_correct' => $isCorrect,
        'answered_at' => now()->subDays(rand(0, 14)),
    ]);

    // Also update UserTopicStat
    $subject = $q->subject ?? 'Geral';
    $topic = $q->topic ?? 'Geral';
    $stat = App\Models\UserTopicStat::firstOrCreate(
        ['user_id' => $admin->id, 'subject' => $subject, 'topic' => $topic],
        ['attempts' => 0, 'correct' => 0, 'accuracy' => 0]
    );
    $stat->attempts += 1;
    if ($isCorrect) $stat->correct += 1;
    $stat->accuracy = $stat->attempts > 0 ? round(($stat->correct / $stat->attempts) * 100, 2) : 0;
    $stat->last_attempt_at = now();
    $stat->save();

    $created++;
}

echo "Created $created new answers for admin.\n";
$total = App\Models\UserQuestionAnswer::where('user_id', $admin->id)->count();
echo "Total answers now: $total\n";
echo "Prerequisites met: " . ($admin->fresh()->hasStudyPlanPrerequisites() ? 'YES' : 'NO') . "\n";
