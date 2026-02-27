<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement(['Matemática', 'Português', 'História', 'Geografia', 'Ciências']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'enem',
        ];
    }

    /**
     * State: specific subject name.
     */
    public function named(string $name): static
    {
        return $this->state(fn() => [
        'name' => $name,
        'slug' => Str::slug($name),
        ]);
    }
}
