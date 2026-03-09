<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\AI\AIService;
use App\Models\Essay;

$essay = Essay::latest()->first();

if (!$essay) {
    die("No essay found.");
}

$aiService = app(AIService::class);

echo "--- OFF TOPIC DETECTOR DEBUG ---\n";
echo "Theme/Title: " . $essay->title . "\n";

try {
    // We use reflection to call the protected method
    $reflection = new \ReflectionMethod(AIService::class, 'buildOffTopicPrompt');
    $reflection->setAccessible(true);

    $prompt = $reflection->invoke($aiService, $essay->title, $essay->content, $essay->type);
    echo "PROMPT RETURNED BY BUILDER:\n";
    echo "=================================================\n";
    echo empty($prompt) ? "[EMPTY STRING RETURNED]\n" : $prompt . "\n";
    echo "=================================================\n";

    echo "Executing real detectOffTopic...\n";
    $result = $aiService->detectOffTopic($essay->title, $essay->content, $essay->type, $essay->user_id);

    echo "RESULT FROM DETECT OFF TOPIC:\n";
    print_r($result);
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
