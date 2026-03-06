<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$essay = \App\Models\Essay::where('status', 'error')->whereNotNull('topic_description')->latest()->first();
if ($essay) {
    echo "Essay ID: " . $essay->id . "\n";
    echo "Error payload: " . $essay->topic_description . "\n";
} else {
    echo "No crashed themes found.\n";
}
