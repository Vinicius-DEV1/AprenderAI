<?php
$user = App\Models\User::where('email', 'admin@aprenderai.com')->first();
$plan = App\Models\Plan::where('slug', 'plus')->first();

if ($user && $plan) {
    // 1. Update user
    $user->update([
        'plan_id' => $plan->id,
        'plan_started_at' => now(),
        'plan_expires_at' => now()->addYear(),
    ]);

    // 2. Create or Update Subscription
    App\Models\Subscription::updateOrCreate(
        ['user_id' => $user->id],
        [
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'system',
            'gateway_id' => 'manual-assignment-' . time(),
            'current_period_start' => now(),
            'current_period_end' => now()->addYear(),
        ]
    );

    echo "SUCCESS: User {$user->email} assigned to PLUS plan.\n\n";

    // Output proof
    $sub = $user->subscriptions()->first();
    echo "--- SUBSCRIPTION DATA ---\n";
    echo "Plan ID: {$sub->plan_id}\n";
    echo "Status: {$sub->status}\n";
    echo "Started At: {$sub->current_period_start}\n";
    echo "Expires At: {$sub->current_period_end}\n";
} else {
    echo "ERROR: User or PLUS plan not found.\n";
}
