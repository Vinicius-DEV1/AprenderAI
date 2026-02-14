<?php

namespace App\Http\Controllers;

use App\Models\Simulation;
use App\Models\Question;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SimulationController extends Controller
{
    protected PlanService $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
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

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:enem,concurso',
            'subject_distribution' => 'required|array|min:1',
            'subject_distribution.*' => 'required|integer|min:0',
            'include_essay' => 'boolean',
            'total_questions' => 'required|integer|min:40|max:100',
            'custom_time' => 'nullable|integer|min:600|max:43200',
        ]);

        $user = $request->user();

        // 0) Checagem do plano
        $check = $this->planService->checkSimulationLimit($user);
        if (!$check['can_create']) {
            return redirect()->route('dashboard')->with('error', $check['message']);
        }

        $total = (int) $request->total_questions;
        $distribution = $request->input('subject_distribution', []);

        // 1) Validar soma da distribuição
        $sum = collect($distribution)->sum(fn($v) => (int) $v);
        if ($sum !== $total) {
            return back()
                ->withInput()
                ->withErrors([
                    'subject_distribution' => "A soma da distribuição ({$sum}) deve ser igual ao total de questões ({$total}).",
                ]);
        }

        // 2) Criar simulação
        $simulation = Simulation::create([
            'user_id' => $user->id,
            'type' => $request->type,
            'configuration' => [
                'questions' => $total,
                'subject_distribution' => $distribution,
                'include_essay' => $request->boolean('include_essay'),
                'time_limit' => $request->type === 'enem'
                    ? ($request->boolean('include_essay') ? 19800 : 16200)
                    : (int) $request->input('custom_time', 10800),
            ],
            'status' => 'pending',
        ]);

        // 3) Selecionar questões (com fallback)
        $questions = $this->selectQuestions($request);

        // 4) Garantir que montou exatamente o total (senão desfaz)
        if ($questions->count() !== $total) {
            $simulation->delete();

            return back()
                ->withInput()
                ->withErrors([
                    'total_questions' =>
                        "Banco insuficiente para montar {$total} questões com essa distribuição. " .
                        "Foram selecionadas {$questions->count()}. Cadastre mais questões e tente novamente.",
                ]);
        }

        // 5) Criar answers vazias em lote
        $now = now();

        $rows = $questions->map(fn($q) => [
            'simulation_id' => $simulation->id,
            'question_id' => $q->id,
            'user_answer' => null,
            'is_correct' => false,
            'time_spent' => 0,
            'marked_for_review' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('simulation_answers')->insert($rows);

        // 6) Incrementar uso e iniciar
        $user->incrementSimulationUsage();
        $simulation->startSimulation();

        return redirect()->route('simulations.show', $simulation);
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

        $simulation->load(['answers.question', 'user.plan']);

        $totalQuestions = $simulation->answers()->count();
        $correctAnswers = $simulation->answers()->where('is_correct', true)->count();
        $percentageScore = $totalQuestions > 0 ? ($correctAnswers / $totalQuestions) * 100 : 0;

        return view('simulations.result', compact('simulation', 'totalQuestions', 'correctAnswers', 'percentageScore'));
    }

    /**
     * Seleciona questões por distribuição e completa faltantes com qualquer subject do mesmo type (sem repetir IDs).
     */
    protected function selectQuestions(Request $request): Collection
    {
        $type = $request->type;
        $total = (int) $request->total_questions;
        $distribution = $request->subject_distribution;

        $questions = collect();

        // 1) Por subject
        foreach ($distribution as $subject => $count) {
            $count = (int) $count;
            if ($count <= 0)
                continue;

            $selected = Question::where('type', $type)
                ->where('subject', $subject)
                ->whereNotIn('id', $questions->pluck('id'))
                ->inRandomOrder()
                ->limit($count)
                ->get();

            $questions = $questions->merge($selected);
        }

        // 2) Completa se faltou
        $missing = $total - $questions->count();
        if ($missing > 0) {
            $extra = Question::where('type', $type)
                ->whereNotIn('id', $questions->pluck('id'))
                ->inRandomOrder()
                ->limit($missing)
                ->get();

            $questions = $questions->merge($extra);
        }

        // 3) Embaralhar para garantir ordem aleatória (primeira questão não será sempre a mesma)
        return $questions->shuffle()->values()->take($total);
    }
}