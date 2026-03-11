<?php

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Starting processing...\n";

// Get default subject and topic
$subject = Subject::first();
$topic = Topic::first();

if (!$subject || !$topic) {
    echo "Error: Subject or Topic not found. Run seeders first.\n";
    exit(1);
}

$questions = Question::all();
$total = $questions->count();
$processed = 0;

foreach ($questions as $q) {
    $q->review_status = 'approved';
    $q->is_active = true;
    
    if (empty($q->difficulty)) $q->difficulty = 'Dificuldade Média';
    if (empty($q->explanation)) $q->explanation = 'Resolução sob demanda.';
    if (empty($q->difficulty_reasoning)) $q->difficulty_reasoning = 'Análise automática de complexidade.';
    
    $q->save();
    
    // Ensure relations
    if ($q->subjects->isEmpty()) {
        $q->subjects()->attach($subject->id);
    }
    if ($q->topics->isEmpty()) {
        $q->topics()->attach($topic->id);
    }
    
    $processed++;
    if ($processed % 100 === 0) {
        echo "Processed {$processed}/{$total}...\n";
    }
}

echo "Finished. Total processed: {$processed}\n";
