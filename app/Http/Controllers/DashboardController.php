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
        $user->load(['plan']);

        // 1. Total de Simulados (Realizados/Finalizados)
        $finishedSimulations = Simulation::where('user_id', $user->id)
            ->whereIn('status', ['finished', 'corrected'])
            ->with(['answers.question.subjects']) // Eager load for calculation
            ->get();

        $totalSimulations = $finishedSimulations->count();

        // 2. Redações Enviadas
        $totalEssays = \App\Models\Essay::where('user_id', $user->id)
            ->count();

        // 3. Cálculo de Médias por Matéria (Matemática e Português + Geral por Matéria)
        $subjectStats = []; // [ 'Matemática' => ['correct' => 10, 'total' => 20], ... ]

        foreach ($finishedSimulations as $sim) {
            foreach ($sim->answers as $answer) {
                if (!$answer->question)
                    continue;

                // Assuming a question belongs to subjects. Taking the first one for simplicity or iterating all.
                // If subjects is a collection:
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

        // Helper function to calc %
        $calcPct = fn($data) => $data['total'] > 0 ? ($data['correct'] / $data['total']) * 100 : 0;

        $avgMath = 0.0;
        $avgPortuguese = 0.0;

        // Normalize subject names (case insensitive search usually better, but simplified here)
        foreach ($subjectStats as $name => $data) {
            if (mb_stripos($name, 'matemática') !== false) {
                $avgMath = $calcPct($data);
            }
            if (mb_stripos($name, 'português') !== false) {
                $avgPortuguese = $calcPct($data);
            }
        }

        // 4. Progresso nas Provas (Últimas 5-10)
        $recentSimulationsQuery = Simulation::where('user_id', $user->id)
            ->whereIn('status', ['finished', 'corrected'])
            ->with(['answers'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentSimulations = $recentSimulationsQuery->map(function ($sim) {
            $total = $sim->answers->count();
            $correct = $sim->answers->where('is_correct', true)->count();
            $pct = $total > 0 ? ($correct / $total) * 100 : 0;

            // Inject readable properties for the view
            $sim->calculated_score = $pct;
            $sim->formatted_date = $sim->created_at->format('d/m/Y');
            return $sim;
        });

        // 5. Desempenho por Matéria (Collection para a view)
        $subjectPerformance = collect($subjectStats)->map(function ($data, $name) use ($calcPct) {
            return [
                'name' => $name,
                'percentage' => $calcPct($data)
            ];
        })->values();

        // Legacy compatibility (if view uses stats object)
        $stats = (object) [
            'total_simulations' => $totalSimulations,
            'total_essays' => $totalEssays,
            'average_math_score' => $avgMath,
            'average_portuguese_score' => $avgPortuguese,
        ];

        // Check plan limits (keep existing logic)
        $simulationLimit = $this->planService->checkSimulationLimit($user);

        // Return view
        return view('dashboard.index', compact(
            'user',
            'stats',
            'totalSimulations',
            'totalEssays',
            'avgMath',
            'avgPortuguese',
            'recentSimulations',
            'subjectPerformance',
            'simulationLimit'
        ));
    }
}
