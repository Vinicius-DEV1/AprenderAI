<?php

namespace App\Http\Middleware;

use App\Models\Configuration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPaymentActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isActive = Configuration::get('payment_active', true);
        $isSandbox = Configuration::get('asaas_sandbox', false);

        // Se estiver ativo e não for sandbox (ou sandbox não bloquear, dependendo da regra), segue.
        // A regra diz: Se "Modo Sandbox" ou "Desativado" -> Alerta "Compras suspensas".
        // Vamos assumir que "Sandbox" = Pagamentos de teste permitidos, mas COM AVISO.
        // "Desativado" = Bloqueado.
        
        // Re-lendo requisito: "Se o sistema estiver em 'Modo Sandbox' ou 'Desativado', o usuário que tentar assinar deve receber um alerta: 'O sistema está em manutenção ou em modo de testes. Compras estão temporariamente suspensas.'"
        // Isso sugere que o usuário COMUM não deve conseguir assinar em Sandbox?
        // Vou implementar bloqueio para ambos se o usuário não for admin.
        
        if (!$isActive || $isSandbox) {
            // Se for admin, permite com aviso (aviso será exibido na view se implementado, ou via flash session)
            if ($request->user() && $request->user()->role === 'admin') {
                // Apenas um flash message para o admin saber que está em ambiente "controlado"
                session()->flash('warning', 'Sistema em modo Sandbox/Manutenção. Acesso liberado para Administrador.');
                return $next($request);
            }

            // Bloqueia usuários comuns
            return redirect()->back()->with('error', 'O sistema está em manutenção ou em modo de testes. Compras estão temporariamente suspensas.');
        }

        return $next($request);
    }
}
