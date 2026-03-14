<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use App\Models\AiProcessingBatch;

$importId = 2534;
$import = QuestionImport::find($importId);

if (!$import) {
    echo "Import $importId not found.\n";
    exit;
}

echo "Batch: {$import->batch_name}\n";
echo "Status: {$import->status}\n";
echo "Questions: {$import->total_questions}\n";
echo "Approved: {$import->approved_count}\n";

$items = QuestionImportItem::where('import_id', $importId)->limit(5)->pluck('question_id');
echo "Sample Questions: " . $items->implode(', ') . "\n";

$aiBatches = AiProcessingBatch::where(function($q) use ($importId) {
    // We don't have a direct FK, but we can check if any question from this import was in an AI batch
})->latest()->take(5)->get();

// Let's find AI batches that processed questions from this import
$sampleQId = $items->first();
if ($sampleQId) {
    $aiBatchItems = \App\Models\AiBatchItem::where('question_id', $sampleQId)->get();
    echo "\nAI Batches for Question $sampleQId:\n";
    foreach ($aiBatchItems as $bi) {
        $b = AiProcessingBatch::where('batch_id', $bi->batch_id)->first();
        echo "- Batch ID: {$bi->batch_id} | Status: {$bi->status} | Overall Batch Status: " . ($b ? $b->status : 'N/A') . "\n";
    }
}
