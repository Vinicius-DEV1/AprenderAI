<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulation;
use App\Http\Resources\SimulationResource;
use App\Services\PlanService;
use App\Services\SimulationCreationService;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    protected PlanService $planService;
    protected SimulationCreationService $simulationService;

    public function __construct(PlanService $planService, SimulationCreationService $simulationService)
    {
        $this->planService = $planService;
        $this->simulationService = $simulationService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('plan');

        $check = $this->planService->checkSimulationLimit($user);

        $simulations = $user->simulations()->latest()->paginate(10);

        return SimulationResource::collection($simulations)->additional([
            'simulationLimit' => [
                'can_create' => $check['can_create'],
                'remaining' => isset($check['limit']) ? max(0, $check['limit'] - ($check['used'] ?? 0)) : 999999,
                'total' => $check['limit'] ?? 0,
                'used' => $check['used'] ?? 0,
            ]
        ]);
    }

    public function show(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        // Fix backend bug: Start simulation if it was just generated
        if ($simulation->status === 'pending' && $simulation->answers()->count() > 0) {
            $simulation->startSimulation();
            $simulation->refresh();
        }

        $simulation->load(['answers.question.alternatives', 'answers.question.subjects']);

        return new SimulationResource($simulation);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $check = $this->planService->checkSimulationLimit($user);

        // Security check for limits
        if (!$check['can_create']) {
            return response()->json([
                'message' => $check['message'],
                'error' => $check['message'],
                'quota' => [
                    'limit' => $check['limit'] ?? 0,
                    'used' => $check['used'] ?? 0,
                ],
            ], 403);
        }

        $validated = $request->validate([
            // ---- New engine-driven approach ----
            'model_slug' => 'nullable|string|max:64',
            // ---- Old direct approach (still supported for flexibility) ----
            'type' => 'nullable|string|in:concurso,enem,vestibular,custom',
            'title' => 'nullable|string|max:255',
            'total_questions' => 'nullable|integer|min:1|max:200',
            'subject_distribution' => 'nullable|array',
            'organization' => 'nullable|array',
            'institution' => 'nullable|array',
            'role' => 'nullable|array',
            'include_essay' => 'boolean',
        ]);

        // Guard essay inclusion for free users
        if (!$user->hasEssayAccess()) {
            $validated['include_essay'] = false;
        }

        // ── Engine-driven path ────────────────────────────────────────────
        if (!empty($validated['model_slug'])) {
            try {
                /** @var \App\Services\SimulationEngine $engine */
                $engine = app(\App\Services\SimulationEngine::class);
                $resolved = $engine->resolveModel($validated['model_slug']);

                // Build full configuration from DB rules
                $config = $engine->buildConfiguration($resolved, [
                    'include_essay' => $validated['include_essay'] ?? false,
                    'organization' => $validated['organization'] ?? [],
                    'institution' => $validated['institution'] ?? [],
                    'role' => $validated['role'] ?? [],
                    // Concurso-flex: allow caller to override distributions per request
                    'subject_distribution_override' => $validated['subject_distribution'] ?? null,
                    'nao_repetir_ultimos' => $resolved['rule']->nao_repetir_ultimos_simulados,
                ]);

                // For concurso_flexivel the caller may supply per-request subject_distribution
                if ($resolved['model']->tipo === 'concurso' && !empty($validated['subject_distribution'])) {
                    $config['subject_distribution'] = $validated['subject_distribution'];
                    $config['questions'] = array_sum($validated['subject_distribution']);
                }

                // Validate question availability BEFORE creating the simulation record
                $tipo = $resolved['model']->tipo;
                try {
                    $engine->validateQuestionAvailability($user, $config, $tipo);
                } catch (\RuntimeException $ve) {
                    return response()->json(['message' => $ve->getMessage()], 422);
                }

                /** @var \App\Models\Simulation $simulation */
                $simulation = \App\Models\Simulation::create([
                    'user_id' => $user->id,
                    'type' => $tipo,
                    'configuration' => $config,
                    'status' => 'pending',
                ]);

                \App\Jobs\GenerateSimulationQuestions::dispatch($simulation, array_merge($config, [
                    'model_slug' => $validated['model_slug'],
                    'tipo' => $resolved['model']->tipo,
                ]));

                return new \App\Http\Resources\SimulationResource($simulation);

            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        // ── Legacy direct path (backward-compatible) ──────────────────────
        if (!isset($validated['total_questions']) && isset($validated['subject_distribution'])) {
            $validated['total_questions'] = array_sum($validated['subject_distribution']);
        }

        // Validate question availability BEFORE creating the simulation record (legacy path)
        $legacyType = $validated['type'] ?? 'enem';
        $legacyDist = $validated['subject_distribution'] ?? [];
        $legacyTotal = (int) ($validated['total_questions'] ?? 0);
        $legacyAiRatio = 0.10;
        $insufficientSubject = null;

        if ($legacyType === 'enem' && !empty($legacyDist)) {
            foreach ($legacyDist as $subject => $qty) {
                $realNeeded = $qty - (int) ceil($qty * $legacyAiRatio);
                $available = \App\Models\Question::where('type', 'enem')
                    ->whereHas('subjects', fn($q) => $q->where('name', $subject))
                    ->count();

                if ($available < $realNeeded) {
                    $insufficientSubject = "Matéria: $subject — disponíveis: $available, necessárias (banco real): $realNeeded";
                    break;
                }
            }
        }

        if ($insufficientSubject) {
            return response()->json([
                'message' => "Quantidade insuficiente de questões disponíveis para este tipo de simulado. $insufficientSubject.",
            ], 422);
        }

        $simulation = $this->simulationService->createPendingSimulation($user, $validated);
        \App\Jobs\GenerateSimulationQuestions::dispatch($simulation, $validated);

        return new \App\Http\Resources\SimulationResource($simulation);
    }


    public function status(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $simulation->refresh();
        $answersCount = $simulation->answers()->count();

        return response()->json([
            'simulation_status' => $simulation->status,
            'answers_count' => $answersCount,
            'is_finished' => $simulation->isFinished(),
        ]);
    }

    public function answer(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'nullable|string|size:1',
            'marked_for_review' => 'boolean',
            'time_spent' => 'integer|min:0',
        ]);

        $answer = $simulation->answers()
            ->where('question_id', $validated['question_id'])
            ->first();

        if (!$answer) {
            return response()->json(['error' => 'Question not found in this simulation'], 404);
        }

        $answer->update([
            'user_answer' => $validated['answer'],
            'marked_for_review' => $validated['marked_for_review'] ?? $answer->marked_for_review,
            'time_spent' => $validated['time_spent'] ?? 0,
            'is_correct' => $answer->question->isCorrect($validated['answer'] ?? ''),
        ]);

        return response()->json(['success' => true]);
    }

    public function finish(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $simulation->finishSimulation();

        return new SimulationResource($simulation);
    }

    public function submit(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.alternative_id' => 'required|integer',
            'time_spent' => 'integer',
        ]);

        $simulation->update([
            'status' => 'completed',
            'time_spent' => $validated['time_spent'] ?? $simulation->time_spent,
            'completed_at' => now(),
        ]);

        return new SimulationResource($simulation);
    }
}
