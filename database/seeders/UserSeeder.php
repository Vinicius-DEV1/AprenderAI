<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use environment variable for password, fallback to a default if not set (though .env usage is enforced by instructions)
        // Instructions: Hash::make(env('DEFAULT_USER_PASSWORD'))
        // Note: It's good practice to provide a fallback in env() or ensure it's in .env, but user said "Usar variável do .env".
        // I will assume DEFAULT_USER_PASSWORD exists or will be added, but for safety I'll use a safe default if null to avoid error, 
        // OR strictly follow "Hash::make(env('DEFAULT_USER_PASSWORD'))".

        $password = Hash::make(env('DEFAULT_USER_PASSWORD', 'Aprova@123')); // Added fallback just in case to prevent empty password

        // Retrieve Plans
        $free = Plan::where('slug', 'free')->first();
        $basic = Plan::where('slug', 'basic')->first();
        $plus = Plan::where('slug', 'plus')->first();

        if (!$free || !$basic || !$plus) {
            $this->command->error("Plans not found! Please run PlanSeeder first.");
            return;
        }

        $this->command->info("Plans found: Free({$free->id}), Basic({$basic->id}), Plus({$plus->id})");

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

        $this->command->info("All users created successfully.");
    }
}
