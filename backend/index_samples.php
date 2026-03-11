<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;
use App\Jobs\IndexQuestionVectorJob;

echo "Indexing Sample Questions...\n";

$questions = Question::whereHas('subjects', function($q) {
    $q->where('name', 'LIKE', '%PORTUGUESA%');
})->limit(5)->get();

echo "Found " . $questions->count() . " sample questions.\n";

foreach ($questions as $question) {
    echo "Dispatching Indexing for Question #{$question->id}...\n";
    // Using dispatchSync so Laravel handles dependency injection in handle()
    IndexQuestionVectorJob::dispatchSync($question->id);
    echo "Done.\n";
}

echo "All sample questions indexed.\n";
