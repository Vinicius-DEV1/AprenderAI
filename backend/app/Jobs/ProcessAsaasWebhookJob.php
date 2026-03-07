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
use App\Jobs\CancelAsaasSubscriptionJob; // Added this line

use Illuminate\Support\Facades\Mail;
use App\Mail\SubscriptionConfirmedMail;

class ProcessAsaasWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;

    /**
     * Maximum number of attempts before calling failed().
     * Webhook processing is idempotent — safe to retry.
     */
    public int $tries = 5;

    /** Timeout per attempt (60 seconds). */
    public int $timeout = 60;

    /** Exponential backoff: 30s → 2min → 10min → 30min → 1h. */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600];
    }

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
        // FIX: Remove 'clone' on a boolean — scalar values don't need cloning.
        $isSandbox = (bool) ($data['is_sandbox_webhook'] ?? false);
        unset($data['is_sandbox_webhook']); // Remove internally before saving to DB
        $event = $data['event'] ?? null;
        $payment = $data['payment'] ?? [];

        Log::info('[Webhook Job] Processando evento Assas', ['event' => $event, 'payment_id' => $payment['id'] ?? null]);

        if (!$event) {
            return;
        }

        $subscriptionId = $payment['subscription'] ?? null;
        $installmentId = $payment['installment'] ?? null;
        $paymentId = $payment['id'] ?? null;

        $subscription = null;
        if ($subscriptionId) {
            $subscription = Subscription::where('gateway_id', $subscriptionId)->first();
        }

        // Fallback: busca por installment ID (pagamentos parcelados)
        if (!$subscription && $installmentId) {
            $subscription = Subscription::where('gateway_id', $installmentId)->first();
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
            } elseif ($subscription && $installmentId) {
                $subscription->update(['gateway_id' => $installmentId]);
                Log::info('[Webhook Job] Vinculou gateway_id (installment) via externalReference', ['sub_id' => $subscription->id]);
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
        // The closure returns the list of Asaas subscriptions to cancel AFTER the commit.
        $subscriptionsToCancel = DB::transaction(function () use ($subscription, $event, $paymentId, $payment, $quotaService, $auditData) {
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

                    // Send Subscription Confirmation Email
                    try {
                        Mail::to($user->email)->send(new SubscriptionConfirmedMail(
                            $user,
                            $plan->name,
                            $plan->features ?? []
                        ));
                    } catch (\Exception $e) {
                        Log::error('[Webhook Job] Falha ao enviar e-mail de confirmação', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }

                    try {
                        if ($oldPlanId && $oldPlanId != $plan->id && $oldPlanId != 1) {
                            $isUpgrade = false;
                            if ($oldPlan) {
                                $newLevel = $plan->getLevel();
                                $oldLevel = $oldPlan->getLevel();
                                // Upgrade de Funcionalidade
                                if ($newLevel > $oldLevel) {
                                    $isUpgrade = true;
                                }
                                // Upgrade de Periodicidade (mesmo nível, mensal -> anual)
                                else if ($newLevel === $oldLevel && $oldPlan->interval === 'monthly' && $plan->interval === 'yearly') {
                                    $isUpgrade = true;
                                }
                            }

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

                            // ARCH FIX: Only mark as canceled in DB inside the transaction.
                            // The actual HTTP call to Asaas is dispatched as a separate job
                            // AFTER the transaction commits, preventing lock contention and
                            // data inconsistency from failed HTTP calls holding open DB locks.
                            $subscriptionsToCancel = [];
                            foreach ($previousActiveSubscriptions as $oldSub) {
                                $oldSub->update(['status' => 'canceled']);
                                $subscriptionsToCancel[] = [
                                    'gateway_id' => $oldSub->gateway_id,
                                    'subscription_id' => $oldSub->id,
                                ];
                                Log::info('[Webhook Job] Assinatura antiga marcada como cancelada no DB (cancelamento no Asaas via job)', [
                                    'user_id' => $user->id,
                                    'old_gateway_id' => $oldSub->gateway_id,
                                ]);
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

                    // Return the list of subscriptions to cancel outside the transaction
                    return $subscriptionsToCancel ?? [];

                case 'PAYMENT_OVERDUE':
                    $subscription->update(['status' => 'past_due']);
                    PaymentLog::create(array_merge($auditData, ['status' => 'overdue']));
                    return [];

                case 'PAYMENT_REFUNDED':
                    $subscription->update(['status' => 'refunded']);
                    PaymentLog::create(array_merge($auditData, ['status' => 'refunded']));
                    return [];

                case 'PAYMENT_DELETED':
                    $subscription->update(['status' => 'canceled']);
                    PaymentLog::create(array_merge($auditData, ['status' => 'canceled']));
                    return [];

                default:
                    PaymentLog::create(array_merge($auditData, ['status' => 'ignored']));
                    return [];
            }
        });

        // ARCH FIX: Dispatch Asaas HTTP cancellations OUTSIDE the DB transaction.
        // This prevents network latency from holding DB locks and avoids
        // data inconsistency when the HTTP call fails mid-transaction.
        foreach ($subscriptionsToCancel ?? [] as $toCancel) {
            dispatch(new CancelAsaasSubscriptionJob(
                $toCancel['gateway_id'],
                $toCancel['subscription_id']
            ));
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     * At this point, the payment event was NOT fully processed.
     */
    public function failed(\Throwable $exception): void
    {
        $event = $this->data['event'] ?? 'UNKNOWN';
        $payment = $this->data['payment'] ?? [];
        $paymentId = $payment['id'] ?? null;

        Log::critical('[ProcessAsaasWebhookJob] FALHOU definitivamente após todas as tentativas!', [
            'event' => $event,
            'payment_id' => $paymentId,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Send alert to Slack/e-mail here for manual intervention.
    }
}
