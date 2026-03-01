<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

echo "--- 1. LARAVEL LOG (LAST ERROR) ---\n";
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    // Read last 2000 characters to find the last error block
    $log = file_get_contents($logPath);
    $lastErrorPos = strrpos($log, 'local.ERROR');
    if ($lastErrorPos !== false) {
        echo substr($log, $lastErrorPos, 2000) . "\n";
    } else {
        echo "No 'local.ERROR' found in logs.\n";
    }
} else {
    echo "Log file not found.\n";
}

echo "\n--- 2. ROUTE LIST (ESSAYS) ---\n";
Artisan::call('route:list', ['--path' => 'api/v1/essays']);
echo Artisan::output() . "\n";

echo "\n--- 3. SCHEMA essays ---\n";
try {
    $columns = DB::select('SHOW COLUMNS FROM essays');
    foreach ($columns as $column) {
        echo sprintf("%-20s | %-20s | %-10s\n", $column->Field, $column->Type, $column->Null);
    }
} catch (\Exception $e) {
    echo "Error showing columns: " . $e->getMessage() . "\n";
}

echo "\n--- 4. CLASS EXISTS WritingRule ---\n";
echo "App\Models\WritingRule: " . (class_exists('App\Models\WritingRule') ? 'YES' : 'NO') . "\n";
echo "File app/Models/WritingRule.php exists: " . (file_exists(base_path('app/Models/WritingRule.php')) ? 'YES' : 'NO') . "\n";
