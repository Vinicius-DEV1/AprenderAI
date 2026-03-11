<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AI\QdrantService;

$qdrant = app(QdrantService::class);

echo "Checking Qdrant Collections...\n";

// Recreate Concepts Collection
$conceptsCollection = config('xavier.qdrant.collections.concepts', 'concepts_vectors');
echo "Recreating {$conceptsCollection} (3072)...\n";
$qdrant->delete("/collections/{$conceptsCollection}");
$qdrant->put("/collections/{$conceptsCollection}", [
    'vectors' => ['size' => 3072, 'distance' => 'Cosine']
]);

// Recreate Questions Collection
$questionsCollection = config('xavier.qdrant.collections.questions', 'questions_vectors');
echo "Recreating {$questionsCollection} (3072)...\n";
$qdrant->delete("/collections/{$questionsCollection}");
$qdrant->put("/collections/{$questionsCollection}", [
    'vectors' => [
        'statement'   => ['size' => 3072, 'distance' => 'Cosine'],
        'concept'     => ['size' => 3072, 'distance' => 'Cosine'],
        'explanation' => ['size' => 3072, 'distance' => 'Cosine'],
    ]
]);

echo "Done recreating collections.\n";
