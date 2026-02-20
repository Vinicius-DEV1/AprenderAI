<?php

namespace App\Http\Controllers;

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
            return view('study_plans.paywall');
        }

        // 2. Check Prerequisites (At least 1 completed sim)
        if (!$user->hasCompletedSimulation()) {
            return view('study_plans.empty');
        }

        // 3. Check if has plan
        $plan = $user->studyPlans()->latest()->first();
        if (!$plan) {
            return view('study_plans.wizard');
        }

        // 4. Build all dashboard data via service
        $data = $this->dashboardService->buildDashboardData($user, $plan);

        return view('study_plans.dashboard', $data);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

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

            // 4. Return Response
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'queued',
                    'study_plan_id' => $plan->id,
                    'message' => 'Plano em processamento...',
                ], 202);
            }

            return redirect()->route('study-plan.index')
                ->with('success', 'Seu plano está sendo gerado! Aguarde alguns instantes no dashboard.');

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return back()->with('error', $e->getMessage());
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

        // Enforce 14-day rule
        if (!$this->generator->canUpdate($user)) {
            $plan = $user->studyPlans()->latest()->first();
            $nextDate = $plan?->next_update_at?->format('d/m/Y') ?? 'em breve';
            $message = "Seu plano só pode ser atualizado em {$nextDate}. O cronograma semanal permanece protegido até essa data.";

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 403);
            }
            return back()->with('error', $message);
        }

        try {
            $this->generator->update($user);

            // Bust dashboard cache after update
            Cache::forget("study_plan_dashboard_{$user->id}");

            return redirect()->route('study-plan.index')
                ->with('success', 'Plano atualizado com base no seu desempenho recente!');

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}