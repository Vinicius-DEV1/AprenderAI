<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'admin@aprenderai.com')->first();
if (!$user) { echo "Admin not found\n"; exit; }

echo "Admin ID: {$user->id}\n";
echo "hasPlusPlan: " . ($user->hasPlusPlan() ? 'YES' : 'NO') . "\n";
echo "hasStudyPlanPrerequisites: " . ($user->hasStudyPlanPrerequisites() ? 'YES' : 'NO') . "\n";
echo "totalQuestionsAnswered: " . $user->totalQuestionsAnswered() . "\n";

// Check StudyPlanGenerator canGenerate
$gen = app(\App\Services\Study\StudyPlanGenerator::class);
echo "canGenerate: " . ($gen->canGenerate($user) ? 'YES' : 'NO') . "\n";

// Check latest plan
$plan = $user->studyPlans()->latest()->first();
echo "Latest plan: " . ($plan ? "ID={$plan->id}, status={$plan->status}" : "NONE") . "\n";

// Check plan
$planModel = $user->activePlan();
echo "Active plan: " . ($planModel ? "slug={$planModel->slug}, name={$planModel->name}" : "NONE") . "\n";
