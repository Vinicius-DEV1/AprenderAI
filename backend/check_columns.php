<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$question = \App\Models\Question::first();
if ($question) {
    echo "Columns: " . implode(', ', array_keys($question->getAttributes())) . "\n";
    echo "Updated At: " . ($question->updated_at ?? 'NULL') . "\n";
    echo "Created At: " . ($question->created_at ?? 'NULL') . "\n";
} else {
    echo "No questions found.\n";
}
