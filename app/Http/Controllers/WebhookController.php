<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handleMercadoPago(Request $request)
    {
        // Validar origem se possível (HMAC ou IP)
        // Mercado Pago envia notificações com 'topic' ou 'type'

        $data = $request->all();
        Log::info('Mercado Pago Webhook:', $data);

        // Lógica simplificada para sandbox:
        // Se receber notificação de pagamento aprovado, ativar assinatura

        $type = $data['type'] ?? $data['topic'] ?? null;

        if ($type === 'payment') {
            $paymentId = $data['data']['id'] ?? $data['id'] ?? null;
            if ($paymentId) {
                // Consultar pagamento na API (simulado aqui)
                // $payment = MercadoPago\Payment::find_by_id($paymentId);

                // Se status approved...
                // Localizar usuário pelo external_reference (user_id)
                // Atualizar assinatura

                // Como não tenho API key real configurada e sandbox exige setup,
                // vou deixar logado e retornar 200.
                // A implementação real faria:
                /*
                $payment = ...;
                if ($payment->status === 'approved') {
                    $user = User::find($payment->external_reference);
                    if ($user) {
                         $sub = Subscription::where('user_id', $user->id)->first();
                         $sub->update([
                             'status' => 'active',
                             'current_period_start' => now(),
                             'current_period_end' => now()->addMonth(), // Baseado no plano atual
                         ]);
                         $user->update(['plan_id' => $sub->plan_id]);
                    }
                }
                */
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
