<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;

try {
    echo "Total questions: " . Question::count() . "\n";
    echo "Published questions: " . Question::published()->count() . "\n";
    
    $stats = [
        'total_questions' => Question::count(),
        'published_questions' => Question::published()->count(),
    ];
    echo "Stats JSON: " . json_encode($stats) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
