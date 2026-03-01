<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

$report = [
    'class_exists' => class_exists('App\Models\WritingRule'),
    'file_exists' => file_exists(base_path('app/Models/WritingRule.php')),
    'table_exists' => Schema::hasTable('writing_rules'),
    'essays_columns' => Schema::getColumnListing('essays'),
    'routes' => [],
];

Artisan::call('route:list', ['--path' => 'api/v1/essays']);
$report['routes'] = Artisan::output();

// Get last 5 lines of log that mention WritingRule
$logPath = storage_path('logs/laravel.log');
$logLines = [];
if (file_exists($logPath)) {
    $file = new SplFileObject($logPath);
    $file->seek(PHP_INT_MAX);
    $totalLines = $file->key();

    for ($i = max(0, $totalLines - 500); $i <= $totalLines; $i++) {
        $file->seek($i);
        $line = $file->current();
        if (strpos($line, 'WritingRule') !== false || strpos($line, 'local.ERROR') !== false) {
            $logLines[] = trim($line);
        }
    }
}
$report['relevant_logs'] = array_slice($logLines, -10);

echo json_encode($report, JSON_PRETTY_PRINT);
