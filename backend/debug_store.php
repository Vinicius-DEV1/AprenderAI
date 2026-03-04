<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'admin@aprenderai.com')->first();
if (!$user) {
    echo "Admin not found\n";
    exit;
}

auth()->login($user);

$validated = [
    'hours_per_day' => 4,
    'exam_type' => 'enem',
    'exam_name' => '',
    'exam_date' => null,
];

try {
    $gen = app(\App\Services\Study\StudyPlanGenerator::class);

    echo "==> Calling createPlaceholder...\n";
    $plan = $gen->createPlaceholder($user, $validated);
    echo "==> Plan created: ID={$plan->id}, status={$plan->status}\n";

    echo "==> Dispatching GenerateStudyPlanJob...\n";
    \App\Jobs\GenerateStudyPlanJob::dispatch($plan->id);
    echo "==> Job dispatched OK\n";

} catch (\Exception $e) {
    echo "==> EXCEPTION: " . get_class($e) . "\n";
    echo "==> Message: " . $e->getMessage() . "\n";
    echo "==> File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    echo "==> Trace:\n" . $e->getTraceAsString() . "\n";
}
