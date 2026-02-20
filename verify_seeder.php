<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$q = App\Models\Question::whereNotNull('alternatives')->latest()->first();

if ($q) {
    echo "VERIFICATION SUCCESS: Found question ID {$q->id}.\n";
    echo "Alternatives type: " . gettype($q->alternatives) . "\n";
    if (is_array($q->alternatives)) {
        echo "Alternatives count: " . count($q->alternatives) . "\n";
        echo "Alternatives content: " . json_encode($q->alternatives) . "\n";
    } elseif (is_string($q->alternatives)) {
        echo "Alternatives string: " . $q->alternatives . "\n";
    } else {
        echo "Alternatives value: " . var_export($q->alternatives, true) . "\n";
    }

    echo "Correct Answer: " . $q->correct_answer . "\n";
} else {
    echo "VERIFICATION FAILED: No question found with alternatives.\n";
}
