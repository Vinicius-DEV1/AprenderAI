<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $count = \App\Models\Question::where('statement', 'like', '%espanhol%')->count();
    echo "Total questions with 'espanhol' in statement: {$count}\n";

    $samples = \App\Models\Question::where('statement', 'like', '%espanhol%')
        ->limit(5)
        ->get(['id', 'statement']);

    foreach ($samples as $s) {
        echo "ID: {$s->id} | Title: " . substr($s->statement, 0, 50) . "...\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
