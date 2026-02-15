<?php

namespace App\Http\Controllers;

use App\Models\Simulation;
use App\Models\Question;
use App\Http\Requests\StoreSimulationRequest;
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
        $simulations = Simulation::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('simulations.index', compact('simulations'));
    }

    public function create(Request $request)
    {
        $check = $this->planService->checkSimulationLimit($request->user());

        if (!$check['can_create']) {
            return redirect()->route('dashboard')->with('error', $check['message']);
        }

        return view('simulations.create');
    }

    public function store(StoreSimulationRequest $request)
    {
        try {
            $simulation = $this->simulationService->createSimulation(
                $request->user(),
                $request->validated()
            );

            return redirect()->route('simulations.show', $simulation);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function show(Simulation $simulation)
    {
        $this->authorize('view', $simulation);

        $simulation->load(['answers.question']);

        if ($simulation->isFinished()) {
            return redirect()->route('simulations.result', $simulation);
        }

        return view('simulations.show', compact('simulation'));
    }

    public function checkCorrectionStatus(Simulation $simulation)
    {
        $this->authorize('view', $simulation);

        $simulation->load('correction');

        if (!$simulation->correction) {
            return response()->json(['status' => 'pending']);
        }

        // Preparar dados de explicação por questão
        $explanations = [];
        $answers = $simulation->answers;

        foreach ($answers as $answer) {
            $explanation = $simulation->correction->getExplanationForQuestion($answer->question_id);
            if ($explanation) {
                // Parse markdown to HTML if needed, or send raw text
                $explanations[$answer->question_id] = $explanation;
            }
        }

        return response()->json([
            'status' => 'completed',
            'data' => $explanations
        ]);
    }

    public function saveAnswer(Request $request, Simulation $simulation)
    {
        $this->authorize('update', $simulation);

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'nullable|string|size:1',
            'marked_for_review' => 'boolean',
            'time_spent' => 'integer|min:0',
        ]);

        $answer = $simulation->answers()
            ->where('question_id', $request->question_id)
            ->first();

        if (!$answer) {
            return response()->json(['success' => false, 'message' => 'Questão não encontrada'], 404);
        }

        $answer->update([
            'user_answer' => $request->answer,
            'marked_for_review' => $request->boolean('marked_for_review'),
            'time_spent' => $request->time_spent ?? 0,
            'is_correct' => $answer->question->isCorrect($request->answer ?? ''),
        ]);

        return response()->json(['success' => true, 'message' => 'Resposta salva']);
    }

    public function finish(Request $request, Simulation $simulation)
    {
        $this->authorize('update', $simulation);

        $simulation->finishSimulation();

        \App\Jobs\CorrectSimulationJob::dispatch($simulation);

        return redirect()->route('simulations.result', $simulation)
            ->with('success', 'Prova finalizada! Sua correção está sendo processada e você receberá um e-mail em breve.');
    }

    public function result(Simulation $simulation)
    {
        $this->authorize('view', $simulation);

        $simulation->load(['answers.question', 'user.plan', 'correction']);

        $totalQuestions = $simulation->answers()->count();
        $correctAnswers = $simulation->answers()->where('is_correct', true)->count();
        $percentageScore = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

        return view('simulations.result', compact('simulation', 'totalQuestions', 'correctAnswers', 'percentageScore'));
    }
}
