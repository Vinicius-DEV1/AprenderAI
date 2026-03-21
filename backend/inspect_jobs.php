<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$jobs = \Illuminate\Support\Facades\DB::table('jobs')->get();
foreach ($jobs as $job) {
    echo "ID: {$job->id} | Queue: {$job->queue}\n";
    $payload = json_decode($job->payload, true);
    echo "Command Name: " . ($payload['displayName'] ?? 'Unknown') . "\n";
    if (isset($payload['data']['command'])) {
         $command = unserialize($payload['data']['command']);
         if (property_exists($command, 'batchId')) {
             echo "Batch ID: " . $command->batchId . "\n";
         }
    }
    echo "------------------\n";
}
