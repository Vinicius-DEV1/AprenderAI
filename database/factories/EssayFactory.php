<?php

namespace Database\Factories;

use App\Models\Simulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EssayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'simulation_id' => Simulation::factory(),
            'type' => 'enem',
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'status' => 'pending',
            'score' => null,
            'time_limit' => 3600,
            'topic_description' => $this->faker->paragraph(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function corrected(): static
    {
        return $this->state(fn(array $attributes) => [
        'status' => 'corrected',
        'score' => $this->faker->numberBetween(400, 1000),
        'feedback' => $this->faker->paragraph(),
        'evaluated_at' => now(),
        ]);
    }
}
