<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Essay;
use Illuminate\Support\Facades\Schema;

echo "Final Column Check for 'essays' table:\n";
$missing = ['image_path', 'input_type', 'ocr_status', 'ocr_error', 'extracted_text'];
foreach ($missing as $col) {
    echo "Column '$col' exists: " . (Schema::hasColumn('essays', $col) ? 'YES' : 'NO') . "\n";
}

echo "\nWritingRule table exists: " . (Schema::hasTable('writing_rules') ? 'YES' : 'NO') . "\n";
echo "WritingRule model exists: " . (class_exists('App\Models\WritingRule') ? 'YES' : 'NO') . "\n";
