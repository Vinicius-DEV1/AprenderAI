<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Topic;
use App\Services\AI\AIService;
use App\Services\AI\QdrantService;

$aiService = app(AIService::class);
$qdrant = app(QdrantService::class);

echo "Indexing Topics as Concepts...\n";

$topics = Topic::all();
echo "Found " . $topics->count() . " topics.\n";

foreach ($topics as $topic) {
    if (empty($topic->name)) continue;
    
    echo "Processing: {$topic->name}... ";
    $vector = $aiService->generateEmbedding($topic->name);
    
    if ($vector) {
        $qdrant->ensureConceptsCollection();
        $success = $qdrant->upsertConcept($topic->name, $vector, ['name' => $topic->name, 'id' => $topic->id]);
        echo $success ? "OK\n" : "FAILED (Qdrant)\n";
    } else {
        echo "FAILED (Embedding)\n";
    }
}

echo "Done.\n";
