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

        // Estatísticas do mês (apenas finalizadas/corrigidas)
        $simulationsThisMonth = Simulation::where('user_id', $user->id)
            ->whereIn('status', ['finished', 'corrected'])
            ->whereMonth('created_at', now()->month)
            ->count();

        $recentSimulations = Simulation::where('user_id', $user->id)
            ->with('answers.question')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Calcular estatísticas reais baseadas nas simulações finalizadas/corrigidas
        $finishedSimulations = Simulation::where('user_id', $user->id)
            ->whereIn('status', ['finished', 'corrected'])
            ->get();

        $totalSimulations = $finishedSimulations->count();

        // Média Geral
        $averageOverall = $totalSimulations > 0
            ? $finishedSimulations->avg('score')
            : 0;

        // Média Matemática
        $mathScores = $finishedSimulations->map(function ($sim) {
            $scores = $sim->scores_by_subject ?? [];
            return $scores['matemática'] ?? null;
        })->filter(fn($v) => !is_null($v));

        $averageMath = $mathScores->count() > 0 ? $mathScores->avg() : 0;

        // Média Português
        $portScores = $finishedSimulations->map(function ($sim) {
            $scores = $sim->scores_by_subject ?? [];
            return $scores['português'] ?? null;
        })->filter(fn($v) => !is_null($v));

        $averagePortuguese = $portScores->count() > 0 ? $portScores->avg() : 0;

        $stats = (object) [
            'total_simulations' => $totalSimulations,
            'total_essays' => 0, // Mantendo 0 pois não foi solicitado correção de redação
            'average_math_score' => round($averageMath, 1),
            'average_portuguese_score' => round($averagePortuguese, 1),
            'average_overall_score' => round($averageOverall, 1),
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
