<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AI\QueryLexicalAnalyser;
use App\Services\AI\AIService;
use App\Services\AI\EmbeddingTextBuilder;

$lexical = app(QueryLexicalAnalyser::class);
$textBuilder = app(EmbeddingTextBuilder::class);
$aiService = app(AIService::class);

$prompt = "enem";
echo "Prompt: $prompt\n";

$analysis = $lexical->analyse($prompt);
echo "Analysis: " . json_encode($analysis, JSON_PRETTY_PRINT) . "\n";

$positivePrompt = implode(' ', $analysis['positive_terms']);
echo "Positive Prompt: '$positivePrompt'\n";

$normalizedQuery = $textBuilder->buildForQuery($positivePrompt);
echo "Normalized Query: '$normalizedQuery'\n";

try {
    echo "Attempting to generate embedding for '$normalizedQuery'...\n";
    $vector = $aiService->generateEmbedding($normalizedQuery, 5, 'RETRIEVAL_QUERY');
    echo "Success! Vector size: " . count($vector) . "\n";
} catch (\Exception $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
}
