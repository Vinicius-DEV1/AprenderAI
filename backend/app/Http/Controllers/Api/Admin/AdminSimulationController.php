<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SimulationPreset;
use App\Models\SimulationRule;
use App\Services\SimulationEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSimulationController extends Controller
{
    public function index()
    {
        $presets = SimulationPreset::with('rules')->get();
        return response()->json($presets);
    }

    public function store(Request $request)
    {
        \Illuminate\Support\Facades\Log::debug('AdminSimulationController@store reached', [
            'payload' => $request->all()
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:enem,banca',
            'is_active' => 'boolean',
            'rules' => 'array',
            'rules.*.category' => 'required|string',
            'rules.*.configuration' => 'required|array',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $preset = SimulationPreset::create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'type' => $validated['type'],
                    'is_active' => $validated['is_active'] ?? true,
                ]);

                if (isset($validated['rules'])) {
                    foreach ($validated['rules'] as $ruleData) {
                        $preset->rules()->create($ruleData);
                    }
                }

                return response()->json($preset->load('rules'), 201);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao salvar Preset: ' . $e->getMessage());
            return response()->json(['message' => 'Erro interno ao salvar'], 500);
        }
    }

    public function show(SimulationPreset $preset)
    {
        return response()->json($preset->load('rules'));
    }

    public function update(Request $request, SimulationPreset $preset)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'type' => 'string|in:enem,banca',
            'is_active' => 'boolean',
            'rules' => 'array',
            'rules.*.category' => 'required|string',
            'rules.*.configuration' => 'required|array',
        ]);

        return DB::transaction(function () use ($validated, $preset) {
            $preset->update($validated);

            if (isset($validated['rules'])) {
                // For simplicity, we'll replace all rules
                $preset->rules()->delete();
                foreach ($validated['rules'] as $ruleData) {
                    $preset->rules()->create($ruleData);
                }
            }

            return response()->json($preset->load('rules'));
        });
    }

    public function destroy(SimulationPreset $preset)
    {
        $preset->delete();
        return response()->json(null, 204);
    }

    public function debugPool(Request $request, SimulationEngine $engine)
    {
        $validated = $request->validate([
            'tipo' => 'required|string',
            'configuration' => 'required|array'
        ]);

        $config = $validated['configuration'];

        // Normalize naming for the engine (frontend sends total_questions, engine expects questions)
        if (!isset($config['questions']) && isset($config['total_questions'])) {
            $config['questions'] = $config['total_questions'];
        }

        // If no user is logged in (admin), we will just use the current admin user
        $user = $request->user();

        try {
            // Select questions using the exact engine method
            // But we intercept logging or just return the final list
            $questions = $engine->selectQuestions($user, $config, $validated['tipo']);

            // Build a small report
            $pool = $questions->map(function ($q) {
                return [
                    'id' => $q->id,
                    'external_id' => $q->external_id,
                    'difficulty' => $q->difficulty,
                    'type' => $q->type,
                    'source' => $q->source,
                    'subjects' => $q->subjects->pluck('name'),
                    'topics' => $q->topics->pluck('name'),
                    'organization' => $q->organization,
                    'statement_snippet' => \Illuminate\Support\Str::limit(strip_tags($q->statement), 100)
                ];
            });

            // Count distribution matched
            $pickedDistribution = [];
            foreach ($pool as $q) {
                foreach ($q['subjects'] as $sub) {
                    $pickedDistribution[$sub] = ($pickedDistribution[$sub] ?? 0) + 1;
                }
            }

            return response()->json([
                'success' => true,
                'requested_config' => $config,
                'returned_count' => $questions->count(),
                'picked_distribution' => $pickedDistribution,
                'pool' => $pool
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
