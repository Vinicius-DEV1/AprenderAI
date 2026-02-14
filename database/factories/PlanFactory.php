<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
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
        ];
    }
}
