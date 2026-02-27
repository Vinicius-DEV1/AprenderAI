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
            'topic' => $this->faker->word,
            'theme' => $this->faker->word,
            'year' => 2023,
            'statement' => $this->faker->paragraph,
            'explanation' => $this->faker->sentence,
            'source' => 'ai_generated',
            'difficulty' => 'medium',
            'difficulty_reasoning' => $this->faker->sentence,
            'external_id' => $this->faker->uuid, // Required by DB constraint
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (Question $question) {
            // Create default alternatives for every factory question
            $options = ['A' => 'Opção A', 'B' => 'Opção B', 'C' => 'Opção C', 'D' => 'Opção D', 'E' => 'Opção E'];
            foreach ($options as $label => $content) {
                $question->alternatives()->create([
                    'label'      => $label,
                    'content'    => $content,
                    'is_correct' => $label === 'A', // Default is A
                ]);
            }
        });
    }

    /**
     * State: concurso-type question.
     */
    public function concurso(): static
    {
        return $this->state(fn() => [
        'type' => 'concurso',
        'organization' => 'CESPE',
        'institution' => 'TRT',
        'role' => 'Analista',
        ]);
    }

    /**
     * State: incomplete question (missing explanation/difficulty_reasoning).
     */
    public function incomplete(): static
    {
        return $this->state(fn() => [
        'explanation' => null,
        'difficulty_reasoning' => null,
        ]);
    }
}
