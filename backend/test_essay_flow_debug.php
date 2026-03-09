<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Essay;

$essay = Essay::latest()->first();

echo "\n============================================\n";
echo "LATEST ESSAY VERIFICATION (ID: " . $essay->id . ")\n";
echo "============================================\n";
echo "Status:    " . $essay->status . "\n";
echo "Score:     " . $essay->score . "\n";
echo "OffTopic:  " . ($essay->off_topic ? 'TRUE (FAIL)' : 'FALSE (PASS)') . "\n";
echo "Feedback:  " . (is_array($essay->feedback_json) ? 'Array' : gettype($essay->feedback_json)) . "\n";

if (is_array($essay->feedback_json)) {
    echo "Keys in JSON: " . implode(', ', array_keys($essay->feedback_json)) . "\n";
    echo "Summary Start: " . substr($essay->feedback_json['summary'] ?? 'N/A', 0, 100) . "...\n";
}

echo "============================================\n";
