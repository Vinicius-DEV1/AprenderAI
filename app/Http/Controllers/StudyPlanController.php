<?php

namespace App\Http\Controllers;

use App\Models\StudyPlan;
use App\Services\StudyPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudyPlanController extends Controller
{
    protected $studyPlanService;

    public function __construct(StudyPlanService $studyPlanService)
    {
        $this->studyPlanService = $studyPlanService;
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

        // 4. Dashboard
        return view('study_plans.dashboard', compact('plan'));
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
            $plan = $this->studyPlanService->createPlaceholder($user, $validated);

            // 2. Dispatch Job
            \App\Jobs\GenerateStudyPlanJob::dispatch($plan->id);

            // 3. Return Response associated with Request Type
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'queued',
                    'study_plan_id' => $plan->id,
                    'message' => 'Plano em processamento...'
                ], 202);
            }

            return redirect()->route('study-plan.index')->with('success', 'Seu plano está sendo gerado! Aguarde alguns instantes no dashboard.');

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
            'message' => $plan->error_message, // Null if not failed
            'generated_at' => $plan->generated_at,
            'study_plan_id' => $plan->id
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        try {
            $this->studyPlanService->update($user);
            return redirect()->route('study-plan.index')->with('success', 'Plano atualizado com base no seu desempenho recente!');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
