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

        // 1. Finished Simulations & Stats
        $finishedSimulations = Simulation::where('user_id', $user->id)
            ->whereIn('status', ['finished', 'corrected'])
            ->with(['answers.question.subjects'])
            ->get();

        $totalSimulations = $finishedSimulations->count();
        $totalEssays = \App\Models\Essay::where('user_id', $user->id)->count();

        // 2. Metrics by Subject
        $subjectStats = [];
        foreach ($finishedSimulations as $sim) {
            foreach ($sim->answers as $answer) {
                if (!$answer->question)
                    continue;
                foreach ($answer->question->subjects as $subject) {
                    $name = $subject->name;
                    if (!isset($subjectStats[$name])) {
                        $subjectStats[$name] = ['correct' => 0, 'total' => 0];
                    }
                    $subjectStats[$name]['total']++;
                    if ($answer->is_correct) {
                        $subjectStats[$name]['correct']++;
                    }
                }
            }
        }

        $calcPct = fn($data) => $data['total'] > 0 ? ($data['correct'] / $data['total']) * 100 : 0;
        $avgMath = 0.0;
        $avgPortuguese = 0.0;

        foreach ($subjectStats as $name => $data) {
            if (mb_stripos($name, 'matemática') !== false)
                $avgMath = $calcPct($data);
            if (mb_stripos($name, 'português') !== false)
                $avgPortuguese = $calcPct($data);
        }

        // 3. Recent Simulations (Enhanced for Dashboard)
        $recentSimulationsQuery = Simulation::where('user_id', $user->id)
            ->whereIn('status', ['finished', 'corrected'])
            ->with(['answers'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentSimulations = SimulationResource::collection($recentSimulationsQuery);

        // 4. Subject Performance Collection
        $subjectPerformance = collect($subjectStats)->map(function ($data, $name) use ($calcPct) {
            return [
                'name' => $name,
                'percentage' => round($calcPct($data), 1)
            ];
        })->values();

        // 5. Simulation Limits
        $simulationLimit = $this->planService->checkSimulationLimit($user);

        return response()->json([
            'stats' => [
                'total_simulations' => $totalSimulations,
                'total_essays' => $totalEssays,
                'total_questions_answered' => $user->questionAnswers()->count(),
                'average_math_score' => round($avgMath, 1),
                'average_portuguese_score' => round($avgPortuguese, 1),
            ],
            'recent_simulations' => $recentSimulations,
            'subjectPerformance' => $subjectPerformance,
            'simulationLimit' => $simulationLimit,
            'active_study_plan' => $user->studyPlans()->where('status', 'active')->first()
                ? new \App\Http\Resources\StudyPlanResource($user->studyPlans()->where('status', 'active')->first())
                : null,
        ]);
    }
}
