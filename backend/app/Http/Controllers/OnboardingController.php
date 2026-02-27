<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    /**
     * Exibe a tela de boas-vindas e upgrade após o login.
     */
    public function welcome()
    {
        $user = Auth::user();

        // Se o usuário já for premium, não faz sentido ele ficar na tela de onboarding
        if ($user->plan && $user->plan->slug !== 'free') {
            return redirect()->route('dashboard');
        }

        // Busca os planos premium para exibir no grid
        $plans = Plan::where('is_active', true)
            ->where('slug', '!=', 'free')
            ->orderBy('price', 'asc')
            ->get();

        return view('onboarding.welcome', compact('user', 'plans'));
    }
}
