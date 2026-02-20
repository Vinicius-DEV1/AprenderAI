<?php

namespace Database\Factories;

use App\Models\Simulation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SimulationFactory extends Factory
{
    protected $model = Simulation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'enem',
            'score' => null,
            'status' => 'pending', // Default to pending
            'started_at' => now(),
            'finished_at' => null,
            'time_elapsed' => 0,
            'configuration' => json_encode([]),
        ];
    }

    public function finished(): static
    {
        return $this->state(fn(array $attributes) => [
        'status' => 'finished',
        'finished_at' => now(),
        'score' => $this->faker->numberBetween(400, 800),
        'time_elapsed' => 3600,
        ]);
    }
}
