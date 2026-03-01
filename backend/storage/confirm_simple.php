<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$report = [
    'confirmations' => [
        'writing_rule_model_class' => class_exists('App\Models\WritingRule'),
        'writing_rule_file' => file_exists(base_path('app/Models/WritingRule.php')),
        'writing_rules_table' => Schema::hasTable('writing_rules'),
        'essays_table' => Schema::hasTable('essays'),
    ],
    'missing_columns_essays' => [],
];

$required = ['image_path', 'input_type', 'ocr_status', 'ocr_error', 'extracted_text'];
foreach ($required as $col) {
    if (!Schema::hasColumn('essays', $col)) {
        $report['missing_columns_essays'][] = $col;
    }
}

// Get the actual error log entry
$logPath = storage_path('logs/laravel.log');
$lastError = 'Not found';
if (file_exists($logPath)) {
    $content = file_get_contents($logPath);
    $marker = 'local.ERROR:';
    $pos = strrpos($content, $marker);
    if ($pos !== false) {
        $lastError = substr($content, $pos, 1000);
    }
}
$report['last_error_snippet'] = $lastError;

echo json_encode($report, JSON_PRETTY_PRINT);
