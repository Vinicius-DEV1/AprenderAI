<?php

namespace Database\Seeders;

use App\Models\SimulationModel;
use App\Models\SimulationEngineRule;
use App\Models\SimulationDisciplineDistribution;
use Illuminate\Database\Seeder;

/**
 * Seeds the 4 built-in simulation models that match the existing frontend UI.
 * The admin can modify all values from /admin after seeding.
 */
class SimulationModelSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedModel(
            slug: 'enem_prova_completa',
            nome: 'ENEM – Prova Completa',
            tipo: 'enem',
            totalQuestoes: 90,
            tempoMinutos: 270, // 4h30m
            pctIa: 10,
            noRepeat: 10,
            diffMode: 'balanceado',
            distributions: [
                ['disciplina' => 'MATEMÁTICA', 'percentual' => 50, 'ordem' => 1],
                ['disciplina' => 'PORTUGUÊS', 'percentual' => 50, 'ordem' => 2],
            ]
        );

        $this->seedModel(
            slug: 'enem_matematica',
            nome: 'ENEM – Só Matemática',
            tipo: 'enem',
            totalQuestoes: 90,
            tempoMinutos: 270,
            pctIa: 10,
            noRepeat: 10,
            diffMode: 'balanceado',
            distributions: [
                ['disciplina' => 'MATEMÁTICA', 'percentual' => 100, 'ordem' => 1],
            ]
        );

        $this->seedModel(
            slug: 'enem_portugues',
            nome: 'ENEM – Só Português',
            tipo: 'enem',
            totalQuestoes: 90,
            tempoMinutos: 270,
            pctIa: 10,
            noRepeat: 10,
            diffMode: 'balanceado',
            distributions: [
                ['disciplina' => 'PORTUGUÊS', 'percentual' => 100, 'ordem' => 1],
            ]
        );

        $this->seedModel(
            slug: 'concurso_flexivel',
            nome: 'Concurso / Multidisciplinar',
            tipo: 'concurso',
            totalQuestoes: 60,
            tempoMinutos: 180, // 3h default
            pctIa: 15,
            noRepeat: 20,
            diffMode: 'balanceado',
            distributions: [] // Concurso is user-defined, distributions come from request
        );
    }

    // -----------------------------------------------------------------------

    private function seedModel(
        string $slug,
        string $nome,
        string $tipo,
        int $totalQuestoes,
        int $tempoMinutos,
        float $pctIa,
        int $noRepeat,
        string $diffMode,
        array $distributions
    ): void {
        // Idempotent: skip if already exists
        if (SimulationModel::where('slug', $slug)->exists()) {
            return;
        }

        $model = SimulationModel::create([
            'slug' => $slug,
            'nome' => $nome,
            'tipo' => $tipo,
            'ativo' => true,
        ]);

        $rule = SimulationEngineRule::create([
            'model_id' => $model->id,
            'total_questoes' => $totalQuestoes,
            'tempo_minutos' => $tempoMinutos,
            'percentual_ia' => $pctIa,
            'nao_repetir_ultimos_simulados' => $noRepeat,
            'difficulty_mode' => $diffMode,
            'ativo' => true,
        ]);

        foreach ($distributions as $d) {
            SimulationDisciplineDistribution::create([
                'rule_id' => $rule->id,
                'disciplina' => $d['disciplina'],
                'percentual' => $d['percentual'],
                'dificuldade' => $d['dificuldade'] ?? null,
                'ordem' => $d['ordem'] ?? 0,
            ]);
        }
    }
}
