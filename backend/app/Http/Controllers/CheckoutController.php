<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller
{
    /**
     * Exibe a tela de boas-vindas pós-registro com a oferta do plano selecionado.
     */
    public function welcome()
    {
        $planSlug = session('selected_plan');

        if (!$planSlug) {
            return redirect()->route('dashboard');
        }

        // Busca o plano selecionado
        $selectedPlan = Plan::where('slug', $planSlug)->first();

        if (!$selectedPlan || $selectedPlan->slug === 'free') {
            session()->forget('selected_plan');
            return redirect()->route('dashboard');
        }

        // Busca todos os planos pagos para o carrossel
        $paidPlans = Plan::where('slug', '!=', 'free')
            ->orderBy('price', 'asc')
            ->get();

        return view('checkout.welcome', [
            'plan' => $selectedPlan,
            'paidPlans' => $paidPlans
        ]);
    }

    /**
     * Limpa o plano da sessão e redireciona para o dashboard.
     */
    public function skip()
    {
        session()->forget('selected_plan');

        return redirect()->route('dashboard')->with('success', 'Você está no plano Gratuito. Explore a plataforma!');
    }
}
