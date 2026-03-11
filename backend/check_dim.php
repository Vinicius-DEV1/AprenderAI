<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$aiService = app(\App\Services\AI\AIService::class);
$vector = $aiService->generateEmbedding('teste de dimensao');

if ($vector) {
    echo "Vector dimension: " . count($vector) . "\n";
    echo "First 5 values: " . json_encode(array_slice($vector, 0, 5)) . "\n";
} else {
    echo "Failed to generate embedding.\n";
}
