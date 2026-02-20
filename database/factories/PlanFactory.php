<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Gratuito',
            'slug' => 'gratuito',
            'price' => 0,
            'interval' => 'monthly',
            'simulations_limit' => 5,
            'essays_limit' => 0,
            'features' => [],
            'is_active' => true,
            'max_ai_questions' => 10,
        ];
    }

    /**
     * State: Plus plan with essays and study plan.
     */
    public function plus(): static
    {
        return $this->state(fn() => [
        'name' => 'Plus',
        'slug' => 'plus',
        'price' => 29.90,
        'simulations_limit' => 0,
        'essays_limit' => 10,
        'features' => ['study_plan', 'detailed_correction'],
        'max_ai_questions' => 100,
        ]);
    }

    /**
     * State: Basic plan.
     */
    public function basic(): static
    {
        return $this->state(fn() => [
        'name' => 'Básico',
        'slug' => 'basico',
        'price' => 14.90,
        'simulations_limit' => 20,
        'essays_limit' => 3,
        'features' => [],
        'max_ai_questions' => 30,
        ]);
    }
}
