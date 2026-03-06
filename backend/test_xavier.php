<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $essay = \App\Models\Essay::whereNotNull('type')->first();
    if (!$essay) {
        // Create dummy essay if none exists for testing
        $essay = \App\Models\Essay::create([
            'user_id' => 1,
            'type' => 'enem',
            'status' => 'creating'
        ]);
    }

    echo "Testing Xavier for Essay ID: {$essay->id}...\n";
    $result = app(\App\Services\AI\AIService::class)->generateEssayTopic($essay);

    echo "SUCCESS!\n";
    echo "Topic: " . ($result['title'] ?? 'N/A') . "\n";
    echo "Description: " . ($result['description'] ?? 'N/A') . "\n";

} catch (\Exception $e) {
    echo "FAILURE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
