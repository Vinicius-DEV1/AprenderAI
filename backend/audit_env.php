<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$envKey = env('APP_KEY');
$configKey = config('app.key');

echo "--- APP_KEY AUDIT ---\n";
echo "ENV('APP_KEY')    : " . (empty($envKey) ? "EMPTY" : "LOADED (Len: " . strlen($envKey) . ", Starts with: " . substr($envKey, 0, 15) . "...)") . "\n";
echo "CONFIG('app.key'): " . (empty($configKey) ? "EMPTY" : "LOADED (Len: " . strlen($configKey) . ", Starts with: " . substr($configKey, 0, 15) . "...)") . "\n";
