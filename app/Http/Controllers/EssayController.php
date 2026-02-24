<?php

namespace App\Http\Controllers;

use App\Models\Essay;
use App\Models\Simulation;
use App\Services\AI\AIService;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EssayController extends Controller
{
    protected $planService;
    protected $aiService;

    public function __construct(PlanService $planService, AIService $aiService)
    {
        $this->planService = $planService;
        $this->aiService = $aiService;
    }

    public function index(Request $request)
    {
        // Limit logic for UI
        $user = $request->user();
        $user->load('plan');

        $limit = $user->monthlyEssayLimit();
        $used = $user->monthlyEssayUsed();
        $canCreate = $user->canCreateEssay();

        $essays = Essay::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('essays.index', compact('essays', 'limit', 'used', 'canCreate'));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        // Strict limit check
        if (!$user->canCreateEssay()) {
            // Redirect with error
            return redirect()->route('essays.index')->with('error', 'Limite mensal atingido. Faça upgrade para continuar!');
        }

        return view('essays.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:enem,concurso',
            'time_limit' => 'required|integer|in:30,45,60,90,120',
        ]);

        $user = $request->user();

        if (!$user->canCreateEssay()) {
            return redirect()->route('essays.index')->with('error', 'Seu plano não permite criar redações este mês. Faça upgrade para continuar.');
        }

        // Create initial draft
        $essay = Essay::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'time_limit' => $data['time_limit'],
            'title' => 'Gerando tema...', // Temporary
            'content' => '', // Initialize empty content
            'status' => 'in_progress',
            'topic_regen_count' => 0,
            'started_at' => now(),
        ]);

        return redirect()->route('essays.topic', $essay);
    }

    public function showTopic(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

        // Ambiguous state fix: If topic is missing, we must be here, regardless of status being 'error' or 'in_progress'
        // But if we have a topic, we shouldn't be here
        if ($essay->topic_description && $essay->topic_description !== '') {
            return redirect()->route('essays.show', $essay);
        }

        return view('essays.topic', compact('essay'));
    }

    public function startTopicGeneration(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

        // Limit Check
        if ($essay->topic_regen_count >= 3) {
            return response()->json([
                'ok' => false,
                'message' => 'Limite de tentativas atingido.'
            ], 429);
        }

        // Dispatch Job
        \App\Jobs\GenerateEssayTopicJob::dispatch($essay->id);

        return response()->json([
            'ok' => true
        ]);
    }

    public function getTopicStatus(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

        return response()->json([
            'ok' => true,
            'status' => $essay->status,
            'title' => $essay->title,
            'topic_description' => $essay->topic_description,
            'topic_regen_count' => $essay->topic_regen_count
        ]);
    }

    public function write(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

        // State check: If submitted, we shouldn't be here
        if ($essay->submitted_at) {
            return redirect()->route('essays.show', $essay);
        }

        // State check: If no topic, go back to topic
        if (!$essay->topic_description) {
            return redirect()->route('essays.topic', $essay);
        }

        if (!$essay->started_at) {
            $essay->update(['started_at' => now()]);
            $essay->refresh(); // Ensure strict sync
        }

        return view('essays.write', compact('essay'));
    }

    public function submit(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

        // Prevent double submission
        if ($essay->submitted_at) {
            return redirect()->route('essays.show', $essay);
        }

        // Final limit check before submission
        if (!$request->user()->canCreateEssay()) {
            return redirect()->route('essays.index')->with('error', 'Limite mensal atingido não permite envio.');
        }

        $request->validate([
            'content' => 'required|string|min:50',
        ]);

        $essay->update([
            'content' => $request->content,
            'status' => 'evaluating',
            'submitted_at' => now(),
        ]);

        \App\Jobs\EvaluateEssayJob::dispatch($essay);

        return redirect()->route('essays.show', $essay)->with('success', 'Redação enviada para correção!');
    }

    public function show(Essay $essay)
    {
        $this->authorize('view', $essay);

        // 1. Topic Check: If no topic, go to topic generation
        if (is_null($essay->topic_description) || trim($essay->topic_description) === '') {
            return redirect()->route('essays.topic', $essay);
        }

        // 2. Submission Check: If not submitted, go to write
        if (is_null($essay->submitted_at)) {
            return redirect()->route('essays.write', $essay);
        }

        // 3. Status Display (Evaluating, Completed, Error)
        // Helper text for status (used in show.blade.php)
        $statusMessage = match ($essay->status) {
            'pending' => 'Aguardando processamento...',
            'evaluating' => 'Xavier está corrigindo sua redação...',
            'completed' => 'Correção concluída',
            'error' => 'Houve um erro na correção. Tente novamente.',
            default => 'Status: ' . $essay->status
        };

        return view('essays.show', compact('essay', 'statusMessage'));
    }

    public function retryEvaluation(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

        // Allow retry only if status is error and it was already submitted
        if ($essay->status !== 'error' || !$essay->submitted_at) {
            return back()->with('error', 'Esta redação não pode ser reenviada para correção.');
        }

        // Reset status
        $essay->update(['status' => 'evaluating']);

        // Dispatch Job
        \App\Jobs\EvaluateEssayJob::dispatch($essay);

        return back()->with('success', 'Correção enviada novamente.');
    }

    public function storeForSimulation(Request $request, Simulation $simulation)
    {
        // Authorization: user must own the simulation
        $this->authorize('view', $simulation);

        $user = $request->user();

        // Guard: only paid plans can use essay in simulation
        if (!$user->hasEssayAccess()) {
            abort(403, 'Recurso disponível apenas para os planos Básico e Plus.');
        }

        // If an essay already linked to this simulation exists, redirect to the right step
        $existing = Essay::where('simulation_id', $simulation->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            if (is_null($existing->topic_description) || trim($existing->topic_description) === '') {
                return redirect()->route('essays.topic', $existing);
            }
            if (is_null($existing->submitted_at)) {
                return redirect()->route('essays.write', $existing);
            }
            return redirect()->route('essays.show', $existing);
        }

        // Check monthly essay limit
        if (!$user->canCreateEssay()) {
            return redirect()->route('simulations.show', $simulation)
                ->with('error', 'Limite mensal de redações atingido. Faça upgrade para continuar.');
        }

        // Create new essay linked to this simulation
        $essay = Essay::create([
            'user_id' => $user->id,
            'simulation_id' => $simulation->id,
            'type' => $simulation->type === 'enem' ? 'enem' : 'concurso',
            'time_limit' => 60,
            'title' => 'Gerando tema...',
            'content' => '',
            'status' => 'in_progress',
            'topic_regen_count' => 0,
            'started_at' => now(),
        ]);

        return redirect()->route('essays.topic', $essay);
    }
}

