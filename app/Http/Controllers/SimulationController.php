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
            // 1. Create Simulation (Sync - Status: generating)
            $simulation = $this->simulationService->createPendingSimulation(
                $request->user(),
                $request->validated()
            );

            // 2. Dispatch Job (Async)
            \App\Jobs\GenerateSimulationQuestions::dispatch($simulation, $request->validated());

            // 3. Redirect to Show (Loading Screen)
            return redirect()->route('simulations.show', $simulation);

        }
        catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Erro ao iniciar simulado: ' . $e->getMessage()]);
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

        // Ensure we are reading the fresh state from DB (Race Condition Fix)
        $simulation->refresh();
        $simulation->load('correction');

        // \Illuminate\Support\Facades\Log::info("Polling Simulation {$simulation->id}: Checking status.");

        if (!$simulation->correction) {
            return response()->json(['status' => 'pending']);
        }

        // Prepare explanations per question
        $explanations = [];
        $answers = $simulation->answers;

        foreach ($answers as $answer) {
            $explanation = $simulation->correction->getExplanationForQuestion($answer->question_id);
            if ($explanation) {
                $explanations[$answer->question_id] = $explanation;
            }
        }

        // Determine status based on corrected_at or presence of explanations
        $status = $simulation->correction->corrected_at ? 'completed' : 'pending';

        return response()->json([
            'status' => $status,
            'simulation_status' => $simulation->status, // NEW: For generation polling
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

        // 1. Finalizar (Marca timestamp finished_at)
        $simulation->finishSimulation();

        // 2. Calcular Estatísticas Finais (Síncrono)
        $totalQuestions = $simulation->answers()->count();
        $correctAnswers = $simulation->answers()->where('is_correct', true)->count();
        $score = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

        // Opcional: Salvar score no banco se tiver coluna, ou confiar no cálculo on-the-fly.
        // Assumindo que finishSimulation() ou o modelo já tratam isso, ou que calculamos na view.
        // Se houver campos na tabela simulations para caching (score, correct_count), deveríamos atualizar aqui.
        // Como o prompt pede "Calcule e salve", vou verificar se o model tem esses campos, 
        // mas por segurança vou apenas redirecionar já que a view 'result' calcula de novo.
        // A view Result usa: $totalQuestions = $simulation->answers()->count(); ...

        return redirect()->route('simulations.result', $simulation)
            ->with('success', 'Prova finalizada com sucesso! Confira seu desempenho.');
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
