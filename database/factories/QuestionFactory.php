<?php

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'type' => 'enem',
            'subject' => $this->faker->randomElement(['matemática', 'português']),
            'topic' => $this->faker->word,
            'theme' => $this->faker->word,
            'year' => 2023,
            'statement' => $this->faker->sentence,
            'alternatives' => json_encode(['A' => 'Op1', 'B' => 'Op2', 'C' => 'Op3', 'D' => 'Op4', 'E' => 'Op5']),
            'correct_answer' => 'A',
            'explanation' => 'Explicação',
            'source' => 'ai_generated',
            'difficulty' => 'medium',
        ];
    }
}
