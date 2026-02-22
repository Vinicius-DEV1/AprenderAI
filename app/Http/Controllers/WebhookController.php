<?php

namespace App\Http\Controllers;

use App\Models\PaymentLog;
use App\Models\Subscription;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Recebe e processa eventos de pagamento do Asaas.
     *
     * SEGURANÇA:
     *  1. CSRF: Esta rota está excluída do VerifyCsrfToken (configurado no bootstrap/app.php)
     *  2. Token: Verificamos o header 'asaas-access-token' contra o valor salvo nas configurações.
     *     Se o token não batir, retornamos 403 imediatamente.
     */
    public function handleAsaas(Request $request)
    {
        // ---- 1. Verificação de Autenticidade do Webhook ----------------------
        $configuredToken = Configuration::get('asaas_webhook_token', '');

        if (!empty($configuredToken)) {
            $receivedToken = $request->header('asaas-access-token', '');
            if (!hash_equals($configuredToken, $receivedToken)) {
                Log::warning('[Webhook] Token inválido recebido', [
                    'ip'             => $request->ip(),
                    'received_token' => substr($receivedToken, 0, 8) . '...', // Não logar o token completo
                ]);
                return response()->json(['status' => 'unauthorized'], 403);
            }
        }

        // ---- 2. Extrair dados do payload ------------------------------------
        $data    = $request->all();
        $event   = $data['event'] ?? null;
        $payment = $data['payment'] ?? [];

        Log::info('[Webhook] Asaas evento recebido', ['event' => $event, 'payment_id' => $payment['id'] ?? null]);

        if (!$event) {
            return response()->json(['status' => 'ignored_no_event']);
        }

        $subscriptionId = $payment['subscription'] ?? null;
        $paymentId      = $payment['id'] ?? null;

        // ---- 3. Localizar a assinatura --------------------------------------
        $subscription = null;
        if ($subscriptionId) {
            $subscription = Subscription::where('gateway_id', $subscriptionId)->first();
        }

        // Tenta localizar via externalReference se falhou via gateway_id
        // Isso resolve o race condition onde SUBSCRIPTION_CREATED chega antes do record local ser criado
        $externalRef = $payment['externalReference'] ?? ($data['subscription']['externalReference'] ?? null);
        if (!$subscription && $externalRef) {
            $subscription = Subscription::where('user_id', $externalRef)
                ->where('plan_id', Configuration::get('last_plan_id_' . $externalRef)) // Opcional: tracking extra
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($subscription && $subscriptionId) {
                $subscription->update(['gateway_id' => $subscriptionId]);
                Log::info('[Webhook] Vinculou gateway_id via externalReference', ['sub_id' => $subscription->id]);
            }
        }

        // ---- 4. Salvar Audit Log (sempre, mesmo para eventos não encontrados) -
        $auditData = [
            'gateway'                 => 'asaas',
            'gateway_payment_id'      => $paymentId,
            'gateway_subscription_id' => $subscriptionId,
            'event'                   => $event,
            'raw_response'            => PaymentLog::sanitize($data),
        ];

        if ($subscription) {
            $auditData['user_id'] = $subscription->user_id;
        }

        // ---- 5. Processar Evento --------------------------------------------
        if (!$subscription) {
            Log::warning('[Webhook] Subscription não encontrada', [
                'subscription_id' => $subscriptionId,
                'external_ref'    => $externalRef,
                'event'           => $event,
            ]);
            PaymentLog::create(array_merge($auditData, ['status' => 'ignored_not_found']));
            return response()->json(['status' => 'not_found']);
        }

        $user = $subscription->user;
        $plan = $subscription->plan;

        switch ($event) {
            case 'PAYMENT_CONFIRMED':
            case 'PAYMENT_RECEIVED':
                // Calcula o fim do período baseado no plano
                $periodEnd = $plan->interval === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth();

                $subscription->update([
                    'status'               => 'active',
                    'current_period_start' => now(),
                    'current_period_end'   => $periodEnd,
                ]);

                // Ativa o plano do usuário
                $user->update([
                    'plan_id'         => $plan->id,
                    'plan_started_at' => now(),
                    'plan_expires_at' => $periodEnd,
                ]);

                Log::info('[Webhook] Plano ativado', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'event'   => $event,
                ]);

                PaymentLog::create(array_merge($auditData, ['status' => 'success']));
                break;

            case 'PAYMENT_OVERDUE':
                $subscription->update(['status' => 'past_due']);
                // Mantém o acesso até plan_expires_at expirar naturalmente
                PaymentLog::create(array_merge($auditData, ['status' => 'overdue']));
                break;

            case 'PAYMENT_REFUNDED':
                $subscription->update(['status' => 'refunded']);
                PaymentLog::create(array_merge($auditData, ['status' => 'refunded']));
                break;

            case 'PAYMENT_DELETED':
                $subscription->update(['status' => 'canceled']);
                PaymentLog::create(array_merge($auditData, ['status' => 'canceled']));
                break;

            default:
                // Evento ignorado mas registrado para auditoria
                PaymentLog::create(array_merge($auditData, ['status' => 'ignored']));
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}
