<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\PaymentLog;
use App\Models\Subscription;
use App\Models\Configuration;
use App\Services\QuotaService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessAsaasWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;

    /**
     * Create a new job instance.
     *
     * @param array $data O payload completo do webhook do Asaas
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @param QuotaService $quotaService
     */
    public function handle(QuotaService $quotaService)
    {
        $data = $this->data;
        $isSandbox = clone $data['is_sandbox_webhook'] ?? false;
        unset($data['is_sandbox_webhook']); // Remove internally before saving to DB
        $event = $data['event'] ?? null;
        $payment = $data['payment'] ?? [];

        Log::info('[Webhook Job] Processando evento Assas', ['event' => $event, 'payment_id' => $payment['id'] ?? null]);

        if (!$event) {
            return;
        }

        $subscriptionId = $payment['subscription'] ?? null;
        $paymentId = $payment['id'] ?? null;

        $subscription = null;
        if ($subscriptionId) {
            $subscription = Subscription::where('gateway_id', $subscriptionId)->first();
        }

        $externalRef = $payment['externalReference'] ?? ($data['subscription']['externalReference'] ?? null);
        if (!$subscription && $externalRef) {
            $subscription = Subscription::where('user_id', $externalRef)
                ->where('plan_id', Configuration::get('last_plan_id_' . $externalRef))
                ->where('status', 'pending')
                ->latest()
                ->first();

            if ($subscription && $subscriptionId) {
                $subscription->update(['gateway_id' => $subscriptionId]);
                Log::info('[Webhook Job] Vinculou gateway_id via externalReference', ['sub_id' => $subscription->id]);
            }
        }

        $auditData = [
            'gateway' => 'asaas',
            'is_sandbox' => $isSandbox,
            'gateway_payment_id' => $paymentId,
            'gateway_subscription_id' => $subscriptionId,
            'event' => $event,
            'raw_response' => PaymentLog::sanitize($data),
        ];

        if ($subscription) {
            $auditData['user_id'] = $subscription->user_id;
        }

        if (!$subscription) {
            Log::warning('[Webhook Job] Subscription não encontrada', [
                'subscription_id' => $subscriptionId,
                'external_ref' => $externalRef,
                'event' => $event,
            ]);
            PaymentLog::create(array_merge($auditData, ['status' => 'ignored_not_found']));
            return;
        }

        // Executa todo o tratamento dentro de uma Transação para evitar Race Conditions
        DB::transaction(function () use ($subscription, $event, $paymentId, $payment, $quotaService, $auditData) {
            $user = $subscription->user;
            $plan = $subscription->plan;

            switch ($event) {
                case 'PAYMENT_CONFIRMED':
                case 'PAYMENT_RECEIVED':
                    // 1. Idempotência
                    $alreadyProcessed = PaymentLog::where('gateway_payment_id', $paymentId)
                        ->whereIn('event', ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'])
                        ->where('status', 'success')
                        ->exists();

                    if ($alreadyProcessed) {
                        Log::info('[Webhook Job] Pagamento já processado (Idempotência). Ignorando duplo trigger.', ['payment_id' => $paymentId]);
                        return; // Sai da transaction
                    }

                    // 2. Cálculo do período
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

                    $oldPlanId = $user->plan_id;
                    $oldPlan = $oldPlanId ? \App\Models\Plan::find($oldPlanId) : null;

                    $user->update([
                        'plan_id' => $plan->id,
                        'plan_started_at' => now(),
                        'plan_expires_at' => $periodEnd,
                    ]);

                    try {
                        if ($oldPlanId && $oldPlanId != $plan->id && $oldPlanId != 1) {
                            $isUpgrade = $oldPlan && ($plan->price > $oldPlan->price);

                            if ($isUpgrade) {
                                Log::info('[Webhook Job] Detectado UPGRADE. Aplicando Soma Acumulativa!', ['user_id' => $user->id]);
                                $quotaService->processUpgradeSoma($subscription, $plan->default_limits ?? []);
                            } else {
                                Log::info('[Webhook Job] Detectado DOWNGRADE ou mudança de mesmo valor.', ['user_id' => $user->id]);

                                $currentExpiry = $user->plan_expires_at && $user->plan_expires_at->isFuture()
                                    ? $user->plan_expires_at
                                    : now();

                                $quotaService->createPostponedCycle($subscription, $currentExpiry);

                                $newGlobalExpiry = $plan->interval === 'yearly'
                                    ? $currentExpiry->copy()->addYear()
                                    : $currentExpiry->copy()->addMonth();

                                $user->update(['plan_expires_at' => $newGlobalExpiry]);
                            }

                            $previousActiveSubscriptions = Subscription::where('user_id', $user->id)
                                ->where('id', '!=', $subscription->id)
                                ->where('status', 'active')
                                ->where('is_manual_grant', false) // NEVER cancel a manual grant via Webhook
                                ->whereNotNull('gateway_id')
                                ->get();

                            foreach ($previousActiveSubscriptions as $oldSub) {
                                Log::info('[Webhook Job] Cancelando assinatura antiga no Asaas devido a upgrade', [
                                    'user_id' => $user->id,
                                    'old_gateway_id' => $oldSub->gateway_id
                                ]);
                                $asaasService = app(\App\Services\AsaasService::class);
                                $asaasService->cancelSubscription($oldSub->gateway_id);
                                $oldSub->update(['status' => 'canceled']);
                            }
                        } else {
                            Log::info('[Webhook Job] Renovacão normal, compra inicial ou upgrade vindo do Grátis.', ['user_id' => $user->id]);
                            $quotaService->createOrRenewCycle($subscription);
                        }
                    } catch (\Exception $e) {
                        Log::error('[Webhook Job] Erro ao instanciar Ciclos de Quota', [
                            'user_id' => $user->id,
                            'message' => $e->getMessage()
                        ]);
                    }

                    Log::info('[Webhook Job] Plano ativado/renovado com SUCESSO.', [
                        'user_id' => $user->id,
                        'plan_id' => $plan->id,
                    ]);

                    PaymentLog::create(array_merge($auditData, ['status' => 'success']));
                    break;

                case 'PAYMENT_OVERDUE':
                    $subscription->update(['status' => 'past_due']);
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
                    PaymentLog::create(array_merge($auditData, ['status' => 'ignored']));
                    break;
            }
        });
    }
}
