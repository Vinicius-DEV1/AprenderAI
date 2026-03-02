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

    /**
     * Handle an incoming request.
     * API-first: always returns JSON 403 when limits are exceeded.
     * The React frontend is responsible for any UI redirection.
     */
    public function handle(Request $request, Closure $next, string $type = 'simulation')
    {
        $user = $request->user();

        $check = match ($type) {
            'simulation' => $this->planService->checkSimulationLimit($user),
            'essay' => $this->planService->checkEssayLimit($user),
            'daily_question' => $this->planService->checkDailyQuestionLimit($user),
            default => ['can_create' => false, 'message' => 'Tipo inválido']
        };

        if (!$check['can_create']) {
            return response()->json([
                'message' => $check['message'],
                'quota' => [
                    'limit' => $check['limit'] ?? 0,
                    'used' => $check['used'] ?? 0,
                ],
            ], 403);
        }

        return $next($request);
    }
}
