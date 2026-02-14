<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::where('is_active', true)->orderBy('price')->get();
        $userPlan = auth()->user()->plan;

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
