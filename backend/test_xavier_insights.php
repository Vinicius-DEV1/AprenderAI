<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AiSearchRequest;
use App\Models\AiSearchCache;
use Illuminate\Support\Facades\DB;

try {
    echo "Testing Index Queries...\n";

    echo "1. Simple Counts...\n";
    $totalRequests = AiSearchRequest::count();
    $successRequests = AiSearchRequest::where('status', 'completed')->count();
    echo "Total: $totalRequests, Success: $successRequests\n";

    echo "2. History Data Query...\n";
    $historyData = AiSearchRequest::select(
        DB::raw('DATE(created_at) as date'),
        DB::raw('count(*) as count'),
        DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success"),
        DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed")
    )
        ->where('created_at', '>=', now()->subDays(7))
        ->groupBy('date')
        ->orderBy('date')
        ->get();
    echo "History count: " . $historyData->count() . "\n";

    echo "3. Top Prompts Query...\n";
    $topPrompts = AiSearchRequest::select('prompt', DB::raw('count(*) as total'))
        ->groupBy('prompt')
        ->orderByDesc('total')
        ->limit(5)
        ->get();
    echo "Top prompts count: " . $topPrompts->count() . "\n";

    echo "4. Recent Requests Query...\n";
    $recent = AiSearchRequest::with('user:id,name')->orderByDesc('created_at')->limit(10)->get();
    echo "Recent count: " . $recent->count() . "\n";

    echo "All queries successful!\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
