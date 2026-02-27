<?php

namespace App\Http\Middleware;

use App\Models\Configuration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trava de Segurança para o Checkout.
 *
 * Regras:
 *  - payment_active = false → BLOQUEIA todos (inclusive admin)
 *  - asaas_sandbox = true   → PERMITE o checkout, mas injeta flash 'sandbox_mode'
 *                             para que a view exiba um banner de aviso
 *  - Ambos normais           → Fluxo normal sem banner
 */
class CheckPaymentActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $isActive  = (bool) Configuration::get('payment_active', true);
        $isSandbox = (bool) Configuration::get('asaas_sandbox', false);

        // ---- Pagamentos desativados no admin → bloqueia tudo ----------------
        if (!$isActive) {
            return redirect()->back()->with(
                'error',
                'O sistema de pagamentos está temporariamente suspenso. Tente novamente em breve.'
            );
        }

        // ---- Modo Sandbox → permite mas sinaliza para a view ----------------
        // O checkout funciona normalmente (User Acceptance Testing).
        // A view deve exibir um banner de aviso usando session('sandbox_mode').
        if ($isSandbox) {
            session()->flash('sandbox_mode', true);
        }

        return $next($request);
    }
}
