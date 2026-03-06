<?php
require '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$u = App\Models\User::where('email', 'admin@aprenderai.com')->first();
if (!$u) {
    echo "User NOT FOUND\n";
    exit(1);
}

echo "role: " . $u->role . "\n";
echo "plan_id: " . (int) $u->plan_id . "\n";
echo "plan_name: " . ($u->plan?->name ?? 'none') . "\n";
echo "plan_slug: " . ($u->plan?->slug ?? 'none') . "\n";
echo "answers: " . (int) $u->totalQuestionsAnswered() . "\n";
echo "prereqs: " . ($u->hasStudyPlanPrerequisites() ? 'YES' : 'NO') . "\n";
echo "plus: " . ($u->hasPlusPlan() ? 'YES' : 'NO') . "\n";
echo "canAccess: " . ($u->canAccessStudyPlan() ? 'YES' : 'NO') . "\n";

$prompt = App\Models\Prompt::where('key', 'study_plan_generator')->first();
echo "study_plan_prompt_exists: " . ($prompt ? 'YES' : 'NO') . "\n";
if ($prompt) {
    echo "prompt_length: " . strlen($prompt->content) . "\n";
}

$studyPlan = $u->studyPlans()->latest()->first();
echo "has_study_plan: " . ($studyPlan ? 'YES' : 'NO') . "\n";
if ($studyPlan) {
    echo "study_plan_status: " . $studyPlan->status . "\n";
    $planJson = $studyPlan->plan_json;
    if ($planJson) {
        echo "plan_json_keys: " . implode(', ', array_keys((array) $planJson)) . "\n";
    }
}
