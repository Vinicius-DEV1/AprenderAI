<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Question;

echo "Total ENEM questions in DB: " . Question::where('type', 'enem')->count() . PHP_EOL;
