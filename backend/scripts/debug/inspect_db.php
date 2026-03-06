<?php
$user = \App\Models\User::where('email', 'admin@aprenderai.com')->first();
echo "User Plan ID: " . $user->plan_id . "\n";
echo "Overrides - Essays: " . $user->max_essays_override . "\n";

$subs = \App\Models\Subscription::where('user_id', $user->id)->get();
echo "--- Subscriptions ---\n";
foreach ($subs as $sub) {
    echo "Sub ID: {$sub->id}, Status: {$sub->status}, Plan ID: {$sub->plan_id}, Starts: {$sub->current_period_start}, Ends: {$sub->current_period_end}\n";
    $cycles = \App\Models\SubscriptionCycle::where('subscription_id', $sub->id)->get();
    echo "  -- Cycles --\n";
    foreach ($cycles as $cycle) {
        echo "  Cycle ID: {$cycle->id}, Start: {$cycle->start_date}, End: {$cycle->end_date}, Limits: " . json_encode($cycle->limits) . "\n";
    }
}
$activeCycle = \App\Models\SubscriptionCycle::whereHas('subscription', function ($q) use ($user) {
    $q->where('user_id', $user->id)->where('status', 'active');
})->where('start_date', '<=', now())->where('end_date', '>=', now())->first();
echo "--- Active Cycle ---\n";
echo $activeCycle ? "Found Cycle ID: {$activeCycle->id}\n" : "No active cycle found!\n";
