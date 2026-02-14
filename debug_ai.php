<?php

use App\Services\AIService;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ai = new AIService();

// Test connection (mocked or real)
// Note: This script runs with actual .env credentials.
// If provider is gemini and key is active, it should return something.

try {
    echo "Provider: " . env('AI_PROVIDER') . "\n";
    echo "Key set? " . (env('GEMINI_API_KEY') ? 'Yes' : 'No') . "\n";

    // We can't easily call correctSimulation without data, but we can try to inspect the service state.
    // Or call a simple method if exists. 
    // AIService doesn't have a 'ping' method.
    // Just instantiate it confirms basic structure.
    echo "AIService instantiated successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
