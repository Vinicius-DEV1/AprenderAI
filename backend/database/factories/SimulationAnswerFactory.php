<?php

namespace Database\Factories;

use App\Models\SimulationAnswer;
use App\Models\Simulation;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class SimulationAnswerFactory extends Factory
{
    protected $model = SimulationAnswer::class;

    public function definition(): array
    {
        return [
            'simulation_id' => Simulation::factory(),
            'question_id' => Question::factory(),
            'user_answer' => 'A',
            'is_correct' => $this->faker->boolean,
            'time_spent' => 60,
        ];
    }
}
