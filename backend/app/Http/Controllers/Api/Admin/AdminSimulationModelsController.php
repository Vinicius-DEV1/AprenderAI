<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimulationModel;
use App\Models\SimulationEngineRule;
use App\Models\SimulationDisciplineDistribution;
use Illuminate\Http\Request;

/**
 * Admin controller for managing simulation models (the rules engine backend).
 * Routes: /api/v1/admin/simulation-models
 */
class AdminSimulationModelsController extends Controller
{
    /**
     * List all simulation models with their active rule and distributions.
     */
    public function index()
    {
        $models = SimulationModel::with(['activeRule.distributions'])->get()->map(function ($model) {
            $rule = $model->activeRule;
            return [
                'id' => $model->id,
                'slug' => $model->slug,
                'nome' => $model->nome,
                'tipo' => $model->tipo,
                'ativo' => $model->ativo,
                'rule' => $rule ? [
                    'id' => $rule->id,
                    'total_questoes' => $rule->total_questoes,
                    'tempo_minutos' => $rule->tempo_minutos,
                    'percentual_ia' => $rule->percentual_ia,
                    'nao_repetir_ultimos_simulados' => $rule->nao_repetir_ultimos_simulados,
                    'difficulty_mode' => $rule->difficulty_mode,
                    'distributions' => $rule->distributions->map(fn($d) => [
                        'id' => $d->id,
                        'disciplina' => $d->disciplina,
                        'percentual' => $d->percentual,
                        'dificuldade' => $d->dificuldade,
                        'ordem' => $d->ordem,
                    ]),
                ] : null,
            ];
        });

        return response()->json(['models' => $models]);
    }

    /**
     * Get a single model.
     */
    public function show(SimulationModel $simulationModel)
    {
        $simulationModel->load(['rules.distributions']);

        return response()->json(['model' => $simulationModel]);
    }

    /**
     * Update the active rule of a model.
     * POST /api/v1/admin/simulation-models/{id}/rule
     */
    public function updateRule(Request $request, SimulationModel $simulationModel)
    {
        $validated = $request->validate([
            'total_questoes' => 'required|integer|min:1|max:500',
            'tempo_minutos' => 'required|integer|min:1',
            'percentual_ia' => 'required|numeric|min:0|max:100',
            'nao_repetir_ultimos_simulados' => 'required|integer|min:0',
            'difficulty_mode' => 'required|in:balanceado,progressivo,aleatorio',
            'distributions' => 'required|array|min:0',
            'distributions.*.disciplina' => 'required|string',
            'distributions.*.percentual' => 'required|numeric|min:0|max:100',
            'distributions.*.dificuldade' => 'nullable|string',
            'distributions.*.ordem' => 'nullable|integer',
        ]);

        // Deactivate old rules
        SimulationEngineRule::where('model_id', $simulationModel->id)->update(['ativo' => false]);

        // Create new active rule
        $rule = SimulationEngineRule::create([
            'model_id' => $simulationModel->id,
            'total_questoes' => $validated['total_questoes'],
            'tempo_minutos' => $validated['tempo_minutos'],
            'percentual_ia' => $validated['percentual_ia'],
            'nao_repetir_ultimos_simulados' => $validated['nao_repetir_ultimos_simulados'],
            'difficulty_mode' => $validated['difficulty_mode'],
            'ativo' => true,
        ]);

        foreach ($validated['distributions'] as $i => $d) {
            SimulationDisciplineDistribution::create([
                'rule_id' => $rule->id,
                'disciplina' => $d['disciplina'],
                'percentual' => $d['percentual'],
                'dificuldade' => $d['dificuldade'] ?? null,
                'ordem' => $d['ordem'] ?? $i,
            ]);
        }

        return response()->json([
            'message' => 'Regra atualizada com sucesso.',
            'rule' => $rule->load('distributions'),
        ]);
    }

    /**
     * Toggle active status of a simulation model.
     */
    public function toggle(SimulationModel $simulationModel)
    {
        $simulationModel->update(['ativo' => !$simulationModel->ativo]);

        return response()->json(['ativo' => $simulationModel->ativo]);
    }
}
