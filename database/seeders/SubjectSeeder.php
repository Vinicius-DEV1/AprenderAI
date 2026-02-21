<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Português', 'type' => 'shared'],
            ['name' => 'Matemática', 'type' => 'shared'],
            ['name' => 'História', 'type' => 'shared'],
            ['name' => 'Geografia', 'type' => 'shared'],
            ['name' => 'Biologia', 'type' => 'shared'],
            ['name' => 'Física', 'type' => 'shared'],
            ['name' => 'Química', 'type' => 'shared'],
            ['name' => 'Direito Administrativo', 'type' => 'concurso'],
            ['name' => 'Direito Constitucional', 'type' => 'concurso'],
            ['name' => 'Raciocínio Lógico', 'type' => 'concurso'],
        ];

        foreach ($subjects as $subject) {
            $normalizedName = trim(strtoupper($subject['name']));

            Subject::updateOrCreate(
            ['name' => $normalizedName],
            [
                'slug' => Str::slug($normalizedName),
                'type' => $subject['type']
            ]
            );
        }
    }
}
