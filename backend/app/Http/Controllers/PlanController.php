<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlanService;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    public function index()
    {
        $user = auth()->user();
        $plans = Plan::where('is_active', true)->orderBy('price')->get();
        
        // Defensive fix: ensure user has a plan if they're on this page
        if (!$user->plan_id) {
            try {
                $freePlan = $this->planService->getFreePlan();
                $this->planService->assignPlanToUser($user, $freePlan);
                $user->refresh();
            } catch (\Exception $e) {
                logger()->error('Failed to assign free plan on plans index: ' . $e->getMessage());
            }
        }

        $userPlan = $user->plan;

        return view('plans.index', compact('plans', 'userPlan'));
    }

    public function show(Plan $plan)
    {
        return view('plans.show', compact('plan'));
    }

    public function subscribe(Request $request, Plan $plan)
    {
        // TODO: Implementar lógica de pagamento com Mercado Pago
        // Por enquanto, apenas redireciona

        return redirect()->route('plans.index')
            ->with('info', 'Integração com Mercado Pago será implementada em breve!');
    }
}
