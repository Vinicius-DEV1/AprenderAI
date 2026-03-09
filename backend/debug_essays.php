<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Essay;

$essays = Essay::latest()->take(10)->get();

foreach ($essays as $e) {
    echo "ID: " . $e->id . "\n";
    echo "Score: " . $e->score . "\n";
    echo "OffTopic: " . ($e->off_topic ? 'TRUE' : 'FALSE') . "\n";
    echo "Feedback JSON keys: " . implode(', ', array_keys($e->feedback_json ?? [])) . "\n";
    if (isset($e->feedback_json['competencies'])) {
        echo "Competencies type: " . gettype($e->feedback_json['competencies']) . "\n";
    }
    echo "-------------------\n";
}
