<?php

namespace App\Http\Controllers;

use App\Models\PaymentLog;
use App\Models\Subscription;
use App\Models\Configuration;
use App\Services\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected QuotaService $quotaService;

    public function __construct(QuotaService $quotaService)
    {
        $this->quotaService = $quotaService;
    }
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
        $productionToken = Configuration::get('asaas_production_webhook_token', '');
        $sandboxToken = Configuration::get('asaas_sandbox_webhook_token', '');

        $receivedToken = $request->header('asaas-access-token', '');

        $isValid = false;
        if (!empty($productionToken) && hash_equals($productionToken, $receivedToken)) {
            $isValid = true;
        } elseif (!empty($sandboxToken) && hash_equals($sandboxToken, $receivedToken)) {
            $isValid = true;
        }

        if (!$isValid && (!empty($productionToken) || !empty($sandboxToken))) {
            Log::warning('[Webhook] Token inválido recebido', [
                'ip' => $request->ip(),
                'received_token' => $receivedToken ? substr($receivedToken, 0, 8) . '...' : null,
            ]);
            return response()->json(['status' => 'unauthorized'], 403);
        }

        // ---- 2. Extrair dados do payload ------------------------------------
        $data = $request->all();
        $event = $data['event'] ?? null;
        $payment = $data['payment'] ?? [];

        Log::info('[Webhook] Asaas evento recebido', ['event' => $event, 'payment_id' => $payment['id'] ?? null]);

        if (!$event) {
            return response()->json(['status' => 'ignored_no_event']);
        }

        $subscriptionId = $payment['subscription'] ?? null;
        $paymentId = $payment['id'] ?? null;

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
            'gateway' => 'asaas',
            'gateway_payment_id' => $paymentId,
            'gateway_subscription_id' => $subscriptionId,
            'event' => $event,
            'raw_response' => PaymentLog::sanitize($data),
        ];

        if ($subscription) {
            $auditData['user_id'] = $subscription->user_id;
        }

        // ---- 5. Processar Evento --------------------------------------------
        if (!$subscription) {
            Log::warning('[Webhook] Subscription não encontrada', [
                'subscription_id' => $subscriptionId,
                'external_ref' => $externalRef,
                'event' => $event,
            ]);
            PaymentLog::create(array_merge($auditData, ['status' => 'ignored_not_found']));
            return response()->json(['status' => 'not_found']);
        }

        $user = $subscription->user;
        $plan = $subscription->plan;

        switch ($event) {
            case 'PAYMENT_CONFIRMED':
            case 'PAYMENT_RECEIVED':
                // 1. Idempotência: Checa se já processamos com sucesso este exato pagamento antes.
                $alreadyProcessed = PaymentLog::where('gateway_payment_id', $paymentId)
                    ->whereIn('event', ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'])
                    ->where('status', 'success')
                    ->exists();

                if ($alreadyProcessed) {
                    Log::info('[Webhook] Pagamento já processado (Idempotência). Ignorando duplo trigger.', ['payment_id' => $paymentId]);
                    return response()->json(['status' => 'ignored_idempotent']);
                }

                // 2. Cálculo exato do período ancorado na data do Asaas (não depende do atraso do webhook)
                $paymentDateStr = $payment['paymentDate'] ?? ($payment['dueDate'] ?? now()->toDateString());
                $baseDateAt = \Carbon\Carbon::parse($paymentDateStr);

                $periodEnd = $plan->interval === 'yearly'
                    ? $baseDateAt->copy()->addYear()
                    : $baseDateAt->copy()->addMonth();

                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => $baseDateAt,
                    'current_period_end' => $periodEnd,
                ]);

                // Guarda o plano antigo para decidir se é Upgrade
                $oldPlanId = $user->plan_id;
                $oldPlan = $oldPlanId ? \App\Models\Plan::find($oldPlanId) : null;

                // Ativa o plano do usuário primeiramente na tabela (compatibilidade retroativa)
                $user->update([
                    'plan_id' => $plan->id,
                    'plan_started_at' => now(), // A data que o acesso real começou
                    'plan_expires_at' => $periodEnd,
                ]);

                // --- NOVO MODELO ACUMULATIVO (Somente Upgrade) ---
                try {
                    // Se o usuário já tinha um plano ativo e não é o grátis
                    if ($oldPlanId && $oldPlanId != $plan->id && $oldPlanId != 1) {

                        $isUpgrade = $oldPlan && ($plan->price > $oldPlan->price);

                        if ($isUpgrade) {
                            Log::info('[Webhook] Detectado UPGRADE de Plano Pago. Aplicando Soma Acumulativa!', ['user_id' => $user->id]);
                            $this->quotaService->processUpgradeSoma($subscription, $plan->default_limits ?? []);
                        } else {
                            Log::info('[Webhook] Detectado DOWNGRADE ou mudança de mesmo valor. Criando ciclo limpo (Sem Reembolso conforme regra).', ['user_id' => $user->id]);
                            $this->quotaService->createOrRenewCycle($subscription);
                        }

                        // 🛑 Cancela assinaturas anteriores ATIVAS no Asaas para este usuário
                        // Isso evita cobranças duplicadas no futuro.
                        $previousActiveSubscriptions = Subscription::where('user_id', $user->id)
                            ->where('id', '!=', $subscription->id)
                            ->where('status', 'active')
                            ->whereNotNull('gateway_id')
                            ->get();

                        foreach ($previousActiveSubscriptions as $oldSub) {
                            Log::info('[Webhook] Cancelando assinatura antiga no Asaas devido a upgrade', [
                                'user_id' => $user->id,
                                'old_gateway_id' => $oldSub->gateway_id
                            ]);
                            $asaasService = app(\App\Services\AsaasService::class);
                            $asaasService->cancelSubscription($oldSub->gateway_id);
                            $oldSub->update(['status' => 'canceled']);
                        }
                    } else {
                        Log::info('[Webhook] Renovacão normal, compra inicial ou upgrade vindo do Grátis. Criando ciclo limpo.', ['user_id' => $user->id]);
                        $this->quotaService->createOrRenewCycle($subscription);
                    }
                } catch (\Exception $e) {
                    Log::error('[Webhook] Erro ao instanciar Ciclos de Quota', [
                        'user_id' => $user->id,
                        'message' => $e->getMessage()
                    ]);
                }

                Log::info('[Webhook] Plano ativado/renovado', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'vencimento_base' => $baseDateAt->toDateString(),
                    'nova_expiracao' => $periodEnd->toDateString(),
                    'event' => $event,
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
