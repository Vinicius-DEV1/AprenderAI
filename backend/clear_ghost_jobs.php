<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$deleted = \Illuminate\Support\Facades\DB::table('jobs')
    ->where('payload', 'like', '%AIBatchTriageJob%')
    ->delete();

echo "Deleted {$deleted} ghost AIBatchTriageJob jobs from the queue.\n";
