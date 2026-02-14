<?php
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

try {
    $password = Hash::make('Aprova@123');

    $free = Plan::where('slug', 'free')->firstOrFail();
    $basic = Plan::where('slug', 'basic')->firstOrFail();
    $plus = Plan::where('slug', 'plus')->firstOrFail();

    echo "Plans found: Free({$free->id}), Basic({$basic->id}), Plus({$plus->id})\n";

    // Free User
    User::updateOrCreate(['email' => 'free@aprovaai.test'], [
        'name' => 'Admin Free',
        'password' => $password,
        'plan_id' => $free->id,
        'plan_started_at' => now(),
        'plan_expires_at' => null,
        'simulations_used_this_month' => 0,
        'essays_used_this_month' => 0,
        'usage_reset_at' => now()->addMonth(),
    ]);

    // Basic User
    $uBasic = User::updateOrCreate(['email' => 'basic@aprovaai.test'], [
        'name' => 'Admin Basic',
        'password' => $password,
        'plan_id' => $basic->id,
        'plan_started_at' => now(),
        'plan_expires_at' => now()->addMonth(),
        'simulations_used_this_month' => 0,
        'essays_used_this_month' => 0,
        'usage_reset_at' => now()->addMonth(),
    ]);

    // Basic Subscription
    Subscription::updateOrCreate(
        ['user_id' => $uBasic->id],
        [
            'plan_id' => $basic->id,
            'status' => 'active',
            'provider' => 'manual',
            'provider_subscription_id' => 'manual_basic_' . $uBasic->id,
            'started_at' => now(),
            'ends_at' => now()->addMonth()
        ]
    );

    // Plus User
    $uPlus = User::updateOrCreate(['email' => 'plus@aprovaai.test'], [
        'name' => 'Admin Plus',
        'password' => $password,
        'plan_id' => $plus->id,
        'plan_started_at' => now(),
        'plan_expires_at' => now()->addMonth(),
        'simulations_used_this_month' => 0,
        'essays_used_this_month' => 0,
        'usage_reset_at' => now()->addMonth(),
    ]);

    // Plus Subscription
    Subscription::updateOrCreate(
        ['user_id' => $uPlus->id],
        [
            'plan_id' => $plus->id,
            'status' => 'active',
            'provider' => 'manual',
            'provider_subscription_id' => 'manual_plus_' . $uPlus->id,
            'started_at' => now(),
            'ends_at' => now()->addMonth()
        ]
    );

    echo "All users created successfully.\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
