<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PlanService;

class CheckPlanLimits
{
    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    public function handle(Request $request, Closure $next, string $type = 'simulation')
    {
        $user = $request->user();

        $check = match ($type) {
            'simulation' => $this->planService->checkSimulationLimit($user),
            'essay' => $this->planService->checkEssayLimit($user),
            default => ['can_create' => false, 'message' => 'Tipo inválido']
        };

        if (!$check['can_create']) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $check['message']
                ], 403);
            }

            return redirect()->route('dashboard')->with('error', $check['message']);
        }

        return $next($request);
    }
}
