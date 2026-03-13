<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Question;

// Search for the problematic question statement
$question = Question::where('statement', 'like', '%Nesse caso, o piso exerce sobre o caixote uma força%')
    ->select('id', 'statement')
    ->first();

if ($question) {
    echo "ID: {$question->id}\n";
    echo "STATEMENT:\n{$question->statement}\n";
} else {
    echo "Not found.\n";
}
