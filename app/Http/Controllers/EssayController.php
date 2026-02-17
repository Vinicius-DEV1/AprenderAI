<?php

namespace App\Http\Controllers;

use App\Models\Essay;
use App\Services\AIService;
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

        if ($essay->status !== 'in_progress') {
            return redirect()->route('essays.show', $essay);
        }

        // Logic handled by view/JS now (polling or starting)
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

        // Idempotency check?
        // If already has topic, user might be regenerating. That is allowed if count < 3.

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

    // Deprecated method - kept only if necessary for old references, but routes point to new methods now.
    // If strict removal is needed, I can remove it. But keeping it as a stub or removed is safer.
    // I'll remove it to be clean as per "Implementation Exata" which generally means clean code.
    // Actually, I'll remove generateTopic entirely as it contained the sync logic we want to kill.

    public function write(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

        if ($essay->status !== 'in_progress') {
            return redirect()->route('essays.show', $essay);
        }

        return view('essays.write', compact('essay'));
    }

    public function submit(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

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

        // Helper text for status
        $statusMessage = match ($essay->status) {
            'pending' => 'Aguardando envio...',
            'in_progress' => 'Em andamento',
            'evaluating' => 'Xavier está corrigindo sua redação...',
            'completed' => 'Correção concluída',
            'error' => 'Houve um erro na correção. Tente novamente.',
            default => 'Status desconhecido'
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
}

