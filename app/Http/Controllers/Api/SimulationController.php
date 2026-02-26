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
            'title' => 'required|string|max:255',
            'subject_ids' => 'array',
            'topic_ids' => 'array',
            'questions_count' => 'integer|min:1|max:90',
            'type' => 'nullable|string|in:concurso,enem,vestibular,custom',
            'include_essay' => 'boolean',
            'total_questions' => 'nullable|integer',
            'subject_distribution' => 'nullable|array',
            'organization' => 'nullable|array',
            'institution' => 'nullable|array',
            'role' => 'nullable|array',
        ]);

        // Guard essay inclusion for free users
        if (!$user->hasEssayAccess()) {
            $validated['include_essay'] = false;
        }

        // Ensure total_questions mapping for the service
        if (!isset($validated['questions_count']) && isset($validated['total_questions'])) {
            $validated['questions_count'] = $validated['total_questions'];
        }
        if (!isset($validated['total_questions']) && isset($validated['questions_count'])) {
            $validated['total_questions'] = $validated['questions_count'];
        }

        // 1. Create Simulation using the formal service (Sync — Status: generating)
        $simulation = $this->simulationService->createPendingSimulation($user, $validated);

        // 2. Dispatch background job
        \App\Jobs\GenerateSimulationQuestions::dispatch($simulation, $validated);

        return new SimulationResource($simulation);
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
