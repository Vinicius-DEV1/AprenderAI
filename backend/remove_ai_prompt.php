<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    Illuminate\Support\Facades\DB::table('system_prompts')->where('slug', 'ai_search_interpreter')->delete();
    Illuminate\Support\Facades\Cache::forget('system_prompt_ai_search_interpreter');
    echo "AI Search Prompt removed from DB and Cache cleared.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
