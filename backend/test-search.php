<?php

// Save to /tmp/test-search.php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$prompt = "inglês";

$textBuilder = app(\App\Services\AI\EmbeddingTextBuilder::class);
$aiService = app(\App\Services\AI\AIService::class);
$qdrant = app(\App\Services\AI\QdrantService::class);
$hybridSearch = app(\App\Services\AI\HybridSearchService::class);
$reranker = app(\App\Services\AI\ReRankService::class);

$normalizedQuery = $textBuilder->buildForQuery($prompt);
echo "Normalized Query: {$normalizedQuery}\n";

$queryVector = $aiService->generateEmbedding($normalizedQuery, 1);
if (!$queryVector) {
    die("Failed to generate embedding.\n");
}
echo "Generated Vector (Length: " . count($queryVector) . ")\n";

$conceptThreshold = (float) config('xavier.embeddings.concept_detection_threshold', 0.45);
$conceptMatches = $qdrant->searchConcepts($queryVector, 5, $conceptThreshold);

echo "Concept Matches:\n";
foreach ($conceptMatches as $match) {
    echo "- " . ($match['payload']['concept_slug'] ?? 'Unknown') . " (Score: {$match['score']})\n";
}

$detectedConcepts = array_filter(array_map(fn($m) => $m['payload']['concept_slug'] ?? null, $conceptMatches));
$expandedConceptIds = app(\App\Services\AI\QueryExpansionService::class)->expand($detectedConcepts, 1);

echo "Expanded Concepts: " . implode(', ', $expandedConceptIds) . "\n";

$queryVectors = [
    'statement'   => $queryVector,
    'concept'     => $queryVector,
    'explanation' => $queryVector,
];

$candidates = $hybridSearch->search($queryVectors, $expandedConceptIds, [], 10);
echo "Candidates from Hybrid Search (Qdrant):\n";
foreach ($candidates as $c) {
    echo "- Question ID: {$c['question_id']} (Score: {$c['score']})\n";
}

$rankedItems = $reranker->rerank($candidates, 5);
echo "Final Ranked Items:\n";
foreach ($rankedItems as $idx => $item) {
    $q = \App\Models\Question::find($item['question_id']);
    echo "Rank " . ($idx + 1) . ": Question ID {$item['question_id']} [Score: {$item['composite_score']}] - " . substr($q->statement ?? '', 0, 100) . "...\n";
}
