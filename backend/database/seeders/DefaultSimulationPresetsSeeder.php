<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultSimulationPresetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $enem = \App\Models\SimulationPreset::updateOrCreate(
            ['name' => 'ENEM Padrão (90/10)'],
            [
                'description' => 'Preset oficial para simulados ENEM. Proporção clássica de 90% banco e 10% IA.',
                'type' => 'enem',
                'is_active' => true,
            ]
        );

        $enem->rules()->updateOrCreate(
            ['category' => 'subject_distribution'],
            ['configuration' => ['ai_ratio' => 0.1, 'human_ratio' => 0.9]]
        );

        $enem->rules()->updateOrCreate(
            ['category' => 'general_config'],
            ['configuration' => ['allow_repeated' => false, 'difficulty_curve' => 'bell_curve']]
        );

        $concurso = \App\Models\SimulationPreset::updateOrCreate(
            ['name' => 'Concursos: Banca Geral'],
            [
                'description' => 'Preset para concursos públicos com foco em questões de bancas variadas.',
                'type' => 'banca',
                'is_active' => true,
            ]
        );

        $concurso->rules()->updateOrCreate(
            ['category' => 'subject_distribution'],
            ['configuration' => ['ai_ratio' => 0.2, 'human_ratio' => 0.8]]
        );

        $concurso->rules()->updateOrCreate(
            ['category' => 'general_config'],
            ['configuration' => ['allow_repeated' => false, 'difficulty_curve' => 'linear']]
        );
    }
}
