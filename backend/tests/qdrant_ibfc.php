<?php
$qdrant = app(\App\Services\AI\QdrantService::class);
$ai = app(\App\Services\AI\AIService::class);
$vec = $ai->generateEmbedding('portugues', null, 'RETRIEVAL_QUERY');

// Test 1: Restriction matching
$restMatches = $qdrant->searchConcepts($ai->generateEmbedding('ibfc', null, 'RETRIEVAL_QUERY'), 3, 0.50);
dump("Concepts matching 'ibfc':", $restMatches);

// Test 2: Actual Qdrant Question Search WITH exact filter that HybridSearchService builds
dump("Hybrid search match filter ANY IBFC:");
$filter = [
    'must' => [
        [
            'key' => 'organization',
            'match' => ['any' => ['IBFC']]
        ]
    ]
];
$queryVectors = ['statement' => $vec];
$candidates = $qdrant->searchQuestions($queryVectors, $filter, 10, 0.1);
foreach ($candidates as $c) {
    dump("QID: {$c['id']}, Score: {$c['score']}, ORG: " . ($c['payload']['organization'] ?? 'null'));
}

// Test 3: Is it possible AOCP has 'organization' => 'IBFC' in payload? No.
