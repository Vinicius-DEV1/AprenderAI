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
            'score' => $this->faker->numberBetween(400, 800),
            'status' => 'finished',
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'time_elapsed' => 3600,
            'configuration' => json_encode([]),
        ];
    }
}
