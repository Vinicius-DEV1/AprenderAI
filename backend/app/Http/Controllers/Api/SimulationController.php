<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulation;
use App\Models\Question;
use App\Models\QuestionInteraction;
use App\Jobs\RespondToChatJob;
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

        $simulation->load([
            'answers.question.alternatives',
            'answers.question.subjects',
            'answers.question.notebooks' => fn($q) => $q->where('user_id', $request->user()->id),
            'essay'
        ]);

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

        // Guard essay quota if explicitly requested
        if (!empty($validated['include_essay']) && !$user->canCreateEssay()) {
            $essayCheck = app(\App\Services\QuotaService::class)->getUsage($user, 'essays');
            return response()->json([
                'message' => 'Limite de redações atingido.',
                'error' => 'Você atingiu o limite de redações do seu plano.',
                'quota' => [
                    'limit' => $essayCheck['limit'] ?? 0,
                    'used' => $essayCheck['used'] ?? 0,
                    'resource' => 'Redações' // Frontend lerá isso para o QuotaLimitModal
                ],
            ], 403);
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
                // Normalization of subject name (Resiliency)
                $subjectUpper = mb_strtoupper(trim($subject), 'UTF-8');
                $subjectSanitized = str_replace(
                    ['Á', 'À', 'Â', 'Ã', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'],
                    ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'],
                    $subjectUpper
                );

                // Map to related names (Aggregation)
                $searchNames = [$subjectUpper, $subjectSanitized];
                if (str_contains($subjectSanitized, 'PORTUGU')) {
                    $searchNames = array_merge($searchNames, ['LINGUA PORTUGUESA', 'LÍNGUA PORTUGUESA', 'PORTUGUES', 'PORTUGUÊS']);
                } elseif (str_contains($subjectSanitized, 'MATEM')) {
                    $searchNames = array_merge($searchNames, ['MATEMATICA', 'MATEMÁTICA']);
                } elseif (str_contains($subjectSanitized, 'FISIC')) {
                    $searchNames = array_merge($searchNames, ['FISICA', 'FÍSICA']);
                } elseif (str_contains($subjectSanitized, 'QUIMIC')) {
                    $searchNames = array_merge($searchNames, ['QUIMICA', 'QUÍMICA']);
                } elseif (str_contains($subjectSanitized, 'HISTOR')) {
                    $searchNames = array_merge($searchNames, ['HISTORIA', 'HISTÓRIA']);
                }

                $searchNames = array_unique($searchNames);

                $realNeeded = $qty - (int) ceil($qty * $legacyAiRatio);
                $available = \App\Models\Question::published()->where('type', 'enem')
                    ->whereHas('subjects', fn($q) => $q->whereIn('name', $searchNames))
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

        $updateData = [];

        // Support partial updates to prevent clearing existing data (UI metadata vs real answers)
        if (array_key_exists('answer', $validated)) {
            $updateData['user_answer'] = $validated['answer'];
            $updateData['is_correct'] = $answer->question->isCorrect($validated['answer'] ?? '');
        }

        if (array_key_exists('marked_for_review', $validated)) {
            $updateData['marked_for_review'] = $validated['marked_for_review'];
        }

        if (array_key_exists('time_spent', $validated)) {
            $updateData['time_spent'] = $validated['time_spent'];
        }

        if (!empty($updateData)) {
            $answer->update($updateData);
        }

        // Record atomic analytics log only if an actual answer was submitted
        if (!empty($validated['answer'])) {
            app(\App\Services\AnalyticsService::class)->logAnswer(
                $answer->question,
                $request->user()->id,
                $answer->is_correct,
                $answer->time_spent,
                $request,
                'simulado'
            );
        }

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

    public function heartbeat(Request $request, Simulation $simulation)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($simulation->status === 'in_progress') {
            $simulation->update(['last_activity_at' => now()]);
        }

        return response()->json(['success' => true]);
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

    /**
     * Get chat history for a specific question in a simulation.
     */
    public function chat(Request $request, Simulation $simulation, Question $question)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $interactions = QuestionInteraction::where('user_id', $request->user()->id)
            ->where('simulation_id', $simulation->id)
            ->where('question_id', $question->id)
            ->orderBy('created_at', 'asc')
            ->get(['role', 'message', 'created_at']);

        return response()->json($interactions);
    }

    /**
     * Send a new chat message to Xavier from within a simulation.
     */
    public function sendChat(Request $request, Simulation $simulation, Question $question)
    {
        if ($simulation->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $user = $request->user();

        // 1. Check AI Quota
        if (!$user->hasAiQuota()) {
            return response()->json([
                'status' => 'quota_exceeded',
                'message' => 'Você atingiu o limite de dúvidas do seu plano.',
                'upgrade_url' => '/plans'
            ]);
        }

        // 2. Save User Message
        QuestionInteraction::create([
            'simulation_id' => $simulation->id,
            'question_id' => $question->id,
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $request->message,
        ]);

        // 3. Increment usage
        $user->incrementAiUsage();

        // 4. Get history for context
        $history = QuestionInteraction::where('user_id', $user->id)
            ->where('simulation_id', $simulation->id)
            ->where('question_id', $question->id)
            ->orderBy('created_at', 'asc')
            ->get(['role', 'message'])
            ->toArray();

        // 5. Stream response directly
        $aiService = app(\App\Services\AI\AIService::class);

        return response()->stream(function () use ($aiService, $question, $simulation, $request, $history, $user) {
            // Disable buffering and compression for real-time streaming
            @ini_set('zlib.output_compression', 0);
            @ini_set('implicit_flush', 1);
            while (ob_get_level()) {
                ob_end_flush();
            }

            try {
                $stream = $aiService->streamChatAboutQuestion(
                    $question,
                    $simulation,
                    $request->message,
                    $history
                );

                $fullResponse = '';
                $chunkBuffer = "";
                $chunkCounter = 0;
                foreach ($stream as $chunk) {
                    $fullResponse .= $chunk;
                    $chunkBuffer .= $chunk;
                    $chunkCounter++;

                    // Send first chunk immediately, then every 3 chunks
                    if ($chunkCounter === 1 || $chunkCounter % 3 === 0 || str_contains($chunk, "\n")) {
                        // Correctly prefix every line with 'data: ' for SSE compatibility
                        $lines = explode("\n", $chunkBuffer);
                        foreach ($lines as $index => $line) {
                            echo "data: " . $line . "\n";
                        }
                        echo "\n"; // End of SSE event

                        $chunkBuffer = "";
                        if (ob_get_level() > 0)
                            ob_flush();
                        flush();
                    }
                }

                // Final flush
                if ($chunkBuffer !== "") {
                    echo "data: " . $chunkBuffer . "\n\n";
                    if (ob_get_level() > 0)
                        ob_flush();
                    flush();
                }

                QuestionInteraction::create([
                    'simulation_id' => $simulation->id,
                    'question_id' => $question->id,
                    'user_id' => $user->id,
                    'role' => 'assistant',
                    'message' => $fullResponse ?: 'Desculpe, ocorreu um erro na IA.',
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Simulation Chat streaming aborted: " . $e->getMessage());
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Content-Encoding' => 'none',
        ]);
    }
}
