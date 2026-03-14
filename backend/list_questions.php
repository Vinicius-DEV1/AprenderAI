<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Question;

$total = Question::count();
echo "Total Questions: $total\n";

$latest = Question::latest()->take(5)->pluck('id');
echo "Latest IDs: " . $latest->implode(', ') . "\n";

$oneAt2534 = Question::find(2534);
if ($oneAt2534) {
    echo "Found ID 2534!\n";
} else {
    echo "ID 2534 REALLY not found.\n";
}
