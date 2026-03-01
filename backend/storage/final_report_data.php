<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

echo "--- FINAL VALIDATION REPORT ---\n";
echo "1. WritingRule Class: " . (class_exists('App\Models\WritingRule') ? 'OK' : 'FAIL') . "\n";
echo "2. WritingRule Table: " . (Schema::hasTable('writing_rules') ? 'OK' : 'FAIL') . "\n";
echo "3. Essays Missing Columns: ";
$needed = ['image_path', 'input_type', 'ocr_status', 'ocr_error', 'extracted_text'];
$missing = [];
foreach ($needed as $col) {
    if (!Schema::hasColumn('essays', $col))
        $missing[] = $col;
}
echo (empty($missing) ? 'NONE (OK)' : implode(', ', $missing)) . "\n";

echo "4. Migration Status:\n";
Artisan::call('migrate:status');
echo Artisan::output() . "\n";
