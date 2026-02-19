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

        $plan = Plan::where('slug', $planSlug)->first();

        if (!$plan || $plan->slug === 'free') {
            session()->forget('selected_plan');
            return redirect()->route('dashboard');
        }

        return view('checkout.welcome', compact('plan'));
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
