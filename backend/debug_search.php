<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AI\AIService;
use App\Services\AI\QdrantService;

$ai = app(AIService::class);
$qdrant = app(QdrantService::class);

$query = "questões sobre gramatica";
echo "Query: $query\n";

$vector = $ai->generateEmbedding($query);
if (!$vector) {
    die("Failed to generate embedding.\n");
}

echo "Searching in questions_vectors...\n";
$res = $qdrant->post('/collections/questions_vectors/points/query', [
    'query' => ['nearest' => $vector],
    'using' => 'statement',
    'limit' => 5,
    'with_payload' => true
]);

echo "Results:\n";
foreach ($res['result']['points'] ?? [] as $p) {
    echo "[ID: {$p['id']}] Score: {$p['score']} - " . ($p['payload']['subject_name'] ?? 'N/A') . "\n";
}

if (empty($res['result']['points'])) {
    echo "No points found at all.\n";
}
