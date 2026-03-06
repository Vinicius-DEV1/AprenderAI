<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $lastRequest = \App\Models\AiSearchRequest::orderByDesc('created_at')->first();

    if ($lastRequest) {
        echo "Prompt: {$lastRequest->prompt}\n";
        echo "Status: {$lastRequest->status}\n";
        echo "Filters: " . json_encode($lastRequest->filters, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "No requests found.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
