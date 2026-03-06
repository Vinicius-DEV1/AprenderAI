<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Busca na coluna 'subject' (texto legível) da tabela questions
    $results = \App\Models\Question::where('subject', 'like', '%esp%')
        ->orWhere('statement', 'like', '%espanhol%')
        ->limit(10)
        ->get(['id', 'subject', 'statement']);

    echo "Questions found with 'esp' in text:\n";
    foreach ($results as $r) {
        $shortStat = substr($r->statement, 0, 50);
        echo "ID: {$r->id} | Subject(text): {$r->subject} | Statement: {$shortStat}...\n";
    }

    if ($results->isEmpty()) {
        echo "No questions found in text search.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
