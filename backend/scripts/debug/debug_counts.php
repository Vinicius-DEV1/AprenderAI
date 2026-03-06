<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'admin@aprenderai.com')->first();
if ($user) {
    echo "Admin ID: {$user->id}\n";
    echo "Direct Answers: " . $user->questionAnswers()->count() . "\n";
    echo "Through Answers: " . $user->simulationAnswers()->count() . "\n";
    echo "Total Method: " . $user->totalQuestionsAnswered() . "\n";

    // Check subject stats logic
    $subjectStats = \Illuminate\Support\Facades\DB::table('simulations')
        ->join('simulation_answers', 'simulations.id', '=', 'simulation_answers.simulation_id')
        ->join('question_subject', 'simulation_answers.question_id', '=', 'question_subject.question_id')
        ->join('subjects', 'question_subject.subject_id', '=', 'subjects.id')
        ->where('simulations.user_id', $user->id)
        ->select('subjects.name', \Illuminate\Support\Facades\DB::raw('COUNT(*) as total'))
        ->groupBy('subjects.id', 'subjects.name')
        ->get();

    foreach ($subjectStats as $stat) {
        echo "Subject: {$stat->name} | Total: {$stat->total}\n";
    }
} else {
    echo "Admin not found\n";
}
