<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $questions = \App\Models\Question::where('type', 'enem')
        ->whereDoesntHave('subjects')
        ->get(['id', 'statement']);

    echo "Scanning " . $questions->count() . " orphan ENEM questions...\n";

    $foundEsp = 0;
    foreach ($questions as $q) {
        if (stripos($q->statement, 'espanhol') !== false || stripos($q->statement, 'la ') !== false || stripos($q->statement, 'el ') !== false) {
            // Basic detection for Spanish
            $foundEsp++;
            if ($foundEsp < 10) {
                echo "ID: {$q->id} | Potential Spanish! | Statement: " . substr($q->statement, 0, 50) . "...\n";
            }
        }
    }

    echo "\nTotal potential Spanish questions found in orphan pool: {$foundEsp}\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
