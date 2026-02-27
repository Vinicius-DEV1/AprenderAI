<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'user',
            'plan_id' => null,
            'ai_questions_count' => 0,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
        'email_verified_at' => null,
        ]);
    }

    /**
     * State: user with a free plan assigned.
     */
    public function withFreePlan(): static
    {
        return $this->state(function () {
            $plan = \App\Models\Plan::firstOrCreate(
            ['slug' => 'gratuito'],
            [
                'name' => 'Gratuito',
                'price' => 0,
                'interval' => 'monthly',
                'simulations_limit' => 5,
                'essays_limit' => 0,
                'features' => [],
                'is_active' => true,
                'max_ai_questions' => 10,
            ]
            );

            return ['plan_id' => $plan->id];
        });
    }

    /**
     * State: user with Plus plan.
     */
    public function withPlusPlan(): static
    {
        return $this->state(function () {
            $plan = \App\Models\Plan::firstOrCreate(
            ['slug' => 'plus'],
            [
                'name' => 'Plus',
                'price' => 29.90,
                'interval' => 'monthly',
                'simulations_limit' => 0,
                'essays_limit' => 10,
                'features' => ['study_plan', 'detailed_correction'],
                'is_active' => true,
                'max_ai_questions' => 100,
            ]
            );

            return [
                'plan_id' => $plan->id,
                'plan_started_at' => now(),
                'plan_expires_at' => now()->addMonth(),
            ];
        });
    }

    /**
     * State: admin user.
     */
    public function admin(): static
    {
        return $this->state(fn() => [
        'role' => 'admin',
        ]);
    }
}
