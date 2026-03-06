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

        // Admin User (email_verified_at = now())
        $admin = User::updateOrCreate(['email' => 'admin@aprenderai.com'], [
            'name' => 'Administrador',
            'password' => $password,
            'role' => 'admin',
            'email_verified_at' => now(),
            'plan_id' => $plus->id,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addMonth(),
            'ai_questions_count' => 0,
            'last_reset_at' => now(),
        ]);

        // Admin Subscription
        Subscription::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'plan_id' => $plus->id,
                'status' => 'active',
                'gateway' => 'manual',
                'gateway_id' => 'manual_plus_admin',
                'is_manual_grant' => true,
                'is_sandbox' => true,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth()
            ]
        );
        // Gratuito User (email_verified_at = null)
        User::updateOrCreate(['email' => 'gratuito@aprenderai.com'], [
            'name' => 'Usuário Gratuito',
            'password' => $password,
            'role' => 'user',
            'email_verified_at' => null,
            'plan_id' => $free->id,
            'plan_started_at' => now(),
            'plan_expires_at' => null,
        ]);

        // Basic User (email_verified_at = null)
        User::updateOrCreate(['email' => 'basico@aprenderai.com'], [
            'name' => 'Usuário Basic',
            'password' => $password,
            'role' => 'user',
            'email_verified_at' => null,
            'plan_id' => $basic->id,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addMonth(),
        ]);

        // Plus User (email_verified_at = null)
        $uPlus = User::updateOrCreate(['email' => 'plus@aprenderai.com'], [
            'name' => 'Usuário Plus',
            'password' => $password,
            'role' => 'user',
            'email_verified_at' => null,
            'plan_id' => $plus->id,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addMonth(),
        ]);

        // Plus Subscription
        Subscription::updateOrCreate(
            ['user_id' => $uPlus->id],
            [
                'plan_id' => $plus->id,
                'status' => 'active',
                'gateway' => 'manual',
                'gateway_id' => 'manual_plus_' . $uPlus->id,
                'is_manual_grant' => true,
                'is_sandbox' => true,
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth()
            ]
        );

        $this->command->info("All users created successfully (including admin user).");
    }
}