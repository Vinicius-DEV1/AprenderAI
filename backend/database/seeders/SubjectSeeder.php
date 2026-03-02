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
            ['name' => 'Lingua Portuguesa', 'type' => 'shared'],
            ['name' => 'Matematica', 'type' => 'shared'],
            ['name' => 'Historia', 'type' => 'shared'],
            ['name' => 'Geografia', 'type' => 'shared'],
            ['name' => 'Biologia', 'type' => 'shared'],
            ['name' => 'Fisica', 'type' => 'shared'],
            ['name' => 'Quimica', 'type' => 'shared'],
            ['name' => 'Direito Administrativo', 'type' => 'concurso'],
            ['name' => 'Direito Constitucional', 'type' => 'concurso'],
            ['name' => 'Raciocinio Logico', 'type' => 'concurso'],
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
