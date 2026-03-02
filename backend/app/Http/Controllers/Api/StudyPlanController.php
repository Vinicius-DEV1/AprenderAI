<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyPlan;
use App\Services\Study\StudyDashboardService;
use App\Services\Study\StudyPlanGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class StudyPlanController extends Controller
{
    protected $dashboardService;
    protected $generator;

    public function __construct(
        StudyDashboardService $dashboardService,
        StudyPlanGenerator $generator
    ) {
        $this->dashboardService = $dashboardService;
        $this->generator = $generator;
    }

    public function index()
    {
        $user = Auth::user();

        // 1. Check Plan (Plus only)
        if (!$user->hasPlusPlan()) {
            return response()->json([
                'view_state' => 'paywall',
                'code' => 'PAYWALL',
                'message' => 'O Plano de Estudos Premium é exclusivo para usuários Plus. Faça upgrade para continuar!'
            ], 403);
        }

        // 2. Check Prerequisites (50 questions or 1 large sim)
        if (!$user->hasStudyPlanPrerequisites()) {
            return response()->json([
                'view_state' => 'empty',
                'code' => 'INSUFFICIENT_DATA',
                'message' => 'É necessário resolver pelo menos 50 questões ou 1 simulado com 50+ questões para que o Xavier possa criar seu plano.'
            ], 403);
        }

        // 3. Check if has plan
        $plan = $user->studyPlans()->latest()->first();
        if (!$plan) {
            return response()->json([
                'view_state' => 'wizard',
                'message' => 'Clique para gerar seu primeiro plano de estudos.'
            ]);
        }

        // 4. Cooldown Check (if plan exists, we might still be in cooldown for update)
        $canUpdate = $this->generator->canUpdate($user);
        $nextUpdateAt = $plan->next_update_at;
        $daysRemaining = $nextUpdateAt && $nextUpdateAt->isFuture()
            ? (int) now()->diffInDays($nextUpdateAt, false) + 1
            : 0;

        // 5. Build all dashboard data via service
        $data = $this->dashboardService->buildDashboardData($user, $plan);
        $data['view_state'] = 'dashboard';
        $data['can_update'] = $canUpdate;
        $data['next_update_at'] = $nextUpdateAt;
        $data['days_remaining'] = $daysRemaining;

        return response()->json($data);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // 1. Check Plan (Plus only)
        if (!$user->hasPlusPlan()) {
            return response()->json(['code' => 'PAYWALL', 'message' => 'Faça upgrade para o Plus.'], 403);
        }

        // 2. Check Prerequisites
        if (!$user->hasStudyPlanPrerequisites()) {
            return response()->json(['code' => 'INSUFFICIENT_DATA', 'message' => 'Dados insuficientes.'], 403);
        }

        // 3. Cooldown Check (14 days)
        if (!$this->generator->canGenerate($user)) {
            $plan = $user->studyPlans()->latest()->first();
            $nextDate = $plan?->next_update_at?->format('d/m/Y') ?? 'em breve';
            return response()->json(['code' => 'PLAN_LOCKED', 'message' => "Você poderá gerar um novo plano em {$nextDate}."], 403);
        }

        $validated = $request->validate([
            'hours_per_day' => 'required|integer|min:1|max:12',
            'exam_type' => 'required|string',
            'exam_name' => 'nullable|string',
            'exam_date' => 'nullable|date',
        ]);

        try {
            // 1. Create Placeholder
            $plan = $this->generator->createPlaceholder($user, $validated);

            // 2. Dispatch Job
            \App\Jobs\GenerateStudyPlanJob::dispatch($plan->id);

            // 3. Bust dashboard cache
            Cache::forget("study_plan_dashboard_{$user->id}");

            return response()->json([
                'status' => 'queued',
                'study_plan_id' => $plan->id,
                'message' => 'Plano em processamento...',
            ], 202);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function status(Request $request)
    {
        $user = Auth::user();
        $id = $request->input('id');

        if ($id) {
            $plan = $user->studyPlans()->find($id);
        } else {
            $plan = $user->studyPlans()->latest()->first();
        }

        if (!$plan) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => $plan->status,
            'message' => $plan->error_message,
            'generated_at' => $plan->generated_at,
            'study_plan_id' => $plan->id,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        // All validations same as store
        if (!$user->hasPlusPlan()) {
            return response()->json(['code' => 'PAYWALL', 'message' => 'Faça upgrade para o Plus.'], 403);
        }

        if (!$user->hasStudyPlanPrerequisites()) {
            return response()->json(['code' => 'INSUFFICIENT_DATA', 'message' => 'Dados insuficientes.'], 403);
        }

        // Enforce 14-day rule
        if (!$this->generator->canUpdate($user)) {
            $plan = $user->studyPlans()->latest()->first();
            $nextDate = $plan?->next_update_at?->format('d/m/Y') ?? 'em breve';
            return response()->json(['code' => 'PLAN_LOCKED', 'message' => "Seu plano só pode ser atualizado em {$nextDate}."], 403);
        }

        try {
            $this->generator->update($user);
            Cache::forget("study_plan_dashboard_{$user->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Plano atualizado com base no seu desempenho recente!'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
