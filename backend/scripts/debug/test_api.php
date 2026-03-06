<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\StudyPlan;
use App\Jobs\GenerateStudyPlanJob;
use Illuminate\Support\Facades\DB;

function runTest($label, $callback)
{
    echo "=====================================\n";
    echo "RUNNING TEST: $label\n";
    echo "=====================================\n";
    $callback();
    echo "\n";
}

runTest("TEST 2 & 3: Database Status Transition", function () {
    $user = User::where('email', 'admin@aprenderai.com')->first();

    // Create a mock study plan stuck in processing
    $plan = StudyPlan::create([
        'user_id' => $user->id,
        'exam_type' => 'enem',
        'hours_per_day' => 4,
        'status' => 'processing',
        'started_at' => now()
    ]);

    echo "1. Initial Plan Status: " . $plan->status . "\n";

    try {
        // Dispatch job synchronously for testing
        GenerateStudyPlanJob::dispatchSync($plan->id);
    } catch (\Throwable $e) {
        echo "Job threw exception (Expected in controlled failure): " . $e->getMessage() . "\n";
    }

    $plan->refresh();
    echo "2. Final Plan Status: " . $plan->status . "\n";
    echo "3. Error Message (if failed): " . ($plan->error_message ?? 'None') . "\n";

    if ($plan->status === 'processing') {
        echo "❌ TEST FAILED: Plan is still stuck in processing.\n";
    } else {
        echo "✅ TEST PASSED: Plan transitioned to " . $plan->status . ".\n";
    }
});
