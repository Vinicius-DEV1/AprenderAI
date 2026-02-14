<?php

namespace App\Http\Controllers;

use App\Models\Essay;
use App\Services\PlanService;
use Illuminate\Http\Request;

class EssayController extends Controller
{
    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    public function index(Request $request)
    {
        $essays = Essay::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('essays.index', compact('essays'));
    }

    public function create(Request $request)
    {
        $check = $this->planService->checkEssayLimit($request->user());

        if (!$check['can_create']) {
            return redirect()->route('dashboard')->with('error', $check['message']);
        }

        return view('essays.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'theme' => 'required|string|max:255',
            'content' => 'required|string|min:100',
        ]);

        $user = $request->user();

        $essay = Essay::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'theme' => $request->theme,
            'content' => $request->content,
            'status' => 'draft',
        ]);

        return redirect()->route('essays.show', $essay)
            ->with('success', 'Redação salva como rascunho!');
    }

    public function submit(Request $request, Essay $essay)
    {
        $this->authorize('update', $essay);

        $essay->update([
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Incrementar uso
        $request->user()->incrementEssayUsage();

        // Dispatch job de correção
        \App\Jobs\CorrectEssayJob::dispatch($essay);

        return redirect()->route('essays.show', $essay)
            ->with('success', 'Redação enviada para correção! Você será notificado por e-mail.');
    }

    public function show(Essay $essay)
    {
        $this->authorize('view', $essay);

        $essay->load(['user.plan', 'correction']);

        return view('essays.show', compact('essay'));
    }
}
