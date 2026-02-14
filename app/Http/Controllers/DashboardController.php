<?php

namespace App\Http\Controllers;

use App\Models\Simulation;
use App\Services\PlanService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $user->load(['plan', 'stats']);

        // Estatísticas do mês
        $simulationsThisMonth = Simulation::where('user_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        $recentSimulations = Simulation::where('user_id', $user->id)
            ->with('answers.question')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $stats = $user->stats ?? (object) [
            'total_simulations' => 0,
            'total_essays' => 0,
            'average_math_score' => 0,
            'average_portuguese_score' => 0,
            'average_overall_score' => 0,
        ];

        $simulationLimit = $this->planService->checkSimulationLimit($user);
        $essayLimit = $this->planService->checkEssayLimit($user);

        return view('dashboard.index', compact(
            'user',
            'stats',
            'simulationsThisMonth',
            'recentSimulations',
            'simulationLimit',
            'essayLimit'
        ));
    }
}
