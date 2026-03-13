<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Question;

// A simple query to see if updated_at is actually NULL, empty, or what it is, for one specific pseudo-exam
$sample = Question::where('arquivo_origem', '!=', null)
    ->select('arquivo_origem', 'updated_at', 'created_at', 'extracted_at')
    ->limit(10)
    ->get()
    ->toArray();

echo json_encode($sample, JSON_PRETTY_PRINT);
