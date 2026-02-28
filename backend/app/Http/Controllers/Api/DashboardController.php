<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simulation;
use App\Services\PlanService;
use App\Http\Resources\SimulationResource;
use App\Http\Resources\EssayResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }
    /**
     * Retrieve dashboard aggregated data for the authenticated user.
     * Includes active study plans, recent simulations, and overall stats.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $user->load(['plan']);

        // 1. Stats & Totals
        $totalSimulations = Simulation::where('user_id', $user->id)->whereIn('status', ['finished', 'corrected'])->count();
        $totalEssays = \App\Models\Essay::where('user_id', $user->id)->count();

        // 2. Optimized Metrics by Subject - Doing it in SQL instead of PHP loops
        $subjectStats = DB::table('simulations')
            ->join('simulation_answers', 'simulations.id', '=', 'simulation_answers.simulation_id')
            ->join('question_subject', 'simulation_answers.question_id', '=', 'question_subject.question_id')
            ->join('subjects', 'question_subject.subject_id', '=', 'subjects.id')
            ->where('simulations.user_id', $user->id)
            ->whereIn('simulations.status', ['finished', 'corrected'])
            ->select('subjects.name', DB::raw('count(*) as total'), DB::raw('sum(simulation_answers.is_correct) as correct'))
            ->groupBy('subjects.name')
            ->get();

        $calcPct = fn($data) => $data->total > 0 ? ($data->correct / $data->total) * 100 : 0;
        $avgMath = 0.0;
        $avgPortuguese = 0.0;

        foreach ($subjectStats as $data) {
            if (mb_stripos($data->name, 'matemática') !== false)
                $avgMath = $calcPct($data);
            if (mb_stripos($data->name, 'Língua Portuguesa') !== false || mb_stripos($data->name, 'Português') !== false)
                $avgPortuguese = $calcPct($data);
        }

        // 3. Recent Simulations — includes ALL statuses so frontend can show "Pendente" / "Em Andamento"
        $recentSimulationsQuery = Simulation::where('user_id', $user->id)
            ->with(['answers'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentSimulations = SimulationResource::collection($recentSimulationsQuery);

        // 4. Subject Performance Collection
        $subjectPerformance = $subjectStats->map(function ($data) use ($calcPct) {
            return [
                'name' => $data->name,
                'percentage' => round($calcPct($data), 1)
            ];
        })->values()->toArray();

        // 5. Simulation Limits — guaranteed structure
        $simulationLimit = $this->planService->checkSimulationLimit($user);

        // 6. Total questions answered (safe)
        $totalQuestionsAnswered = 0;
        try {
            $totalQuestionsAnswered = $user->questionAnswers()->count();
        } catch (\Throwable $e) {
            // Graceful degradation: if the relationship doesn't exist, just return 0
        }

        // ── RESPONSE: Every field is guaranteed non-null ──
        return response()->json([
            'stats' => [
                'total_simulations' => (int) $totalSimulations,
                'total_essays' => (int) $totalEssays,
                'total_questions_answered' => (int) $totalQuestionsAnswered,
                'average_math_score' => round((float) $avgMath, 1),
                'average_portuguese_score' => round((float) $avgPortuguese, 1),
            ],
            'recent_simulations' => $recentSimulations,
            'subjectPerformance' => $subjectPerformance,
            'simulationLimit' => $simulationLimit ?? [
                'can_create' => true,
                'remaining' => 999,
                'total' => 0,
                'message' => '',
            ],
            'active_study_plan' => $user->studyPlans()->where('status', 'active')->first()
                ? new \App\Http\Resources\StudyPlanResource($user->studyPlans()->where('status', 'active')->first())
                : null,
        ]);
    }
}
