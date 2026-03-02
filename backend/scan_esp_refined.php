<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    // Busca por marcadores linguísticos de Espanhol mais precisos
    $keywords = [' el ', ' la ', ' los ', ' las ', ' que ', ' con ', ' para ', ' por ', ' su ', ' es '];

    $questions = \App\Models\Question::where(function ($q) use ($keywords) {
        foreach ($keywords as $k) {
            $q->orWhere('statement', 'like', "%{$k}%");
        }
    })
        ->whereDoesntHave('subjects')
        ->limit(10)
        ->get(['id', 'statement']);

    echo "Potential Spanish questions (Refined Scan):\n";
    foreach ($questions as $q) {
        echo "ID: {$q->id} | Statement: " . substr(strip_tags($q->statement), 0, 80) . "...\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
