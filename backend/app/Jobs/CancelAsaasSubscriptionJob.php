<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\AsaasService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * CancelAsaasSubscriptionJob
 *
 * Cancels a subscription in the Asaas gateway AFTER the local DB has been
 * committed. This is intentionally a separate job so that the HTTP call to
 * the external Asaas API never runs inside a DB transaction (which would
 * hold a lock for the duration of a network round-trip and risk deadlocks
 * or data inconsistency on partial failure).
 *
 * Dispatch pattern (in ProcessAsaasWebhookJob, AFTER DB::transaction):
 *   dispatch(new CancelAsaasSubscriptionJob($subscription->gateway_id, $subscription->id));
 */
class CancelAsaasSubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Cancel at Asaas is idempotent — safe to retry. */
    public int $tries = 4;

    /** Timeout per attempt (30 seconds is plenty for a single API call). */
    public int $timeout = 30;

    /** Exponential backoff: 30s → 2min → 10min → 30min. */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function __construct(
        public readonly string $gatewayId,
        public readonly int $subscriptionId
    ) {
    }

    public function handle(AsaasService $asaasService): void
    {
        Log::info('[CancelAsaasSubscriptionJob] Iniciando cancelamento no Asaas', [
            'gateway_id' => $this->gatewayId,
            'subscription_id' => $this->subscriptionId,
        ]);

        $asaasService->cancelSubscription($this->gatewayId);

        // Idempotent update: mark as canceled in DB (safe to re-run)
        Subscription::where('id', $this->subscriptionId)
            ->where('gateway_id', $this->gatewayId)
            ->update(['status' => 'canceled']);

        Log::info('[CancelAsaasSubscriptionJob] Cancelamento concluído', [
            'gateway_id' => $this->gatewayId,
            'subscription_id' => $this->subscriptionId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[CancelAsaasSubscriptionJob] FALHOU após todas as tentativas', [
            'gateway_id' => $this->gatewayId,
            'subscription_id' => $this->subscriptionId,
            'error' => $exception->getMessage(),
        ]);

        // NOTE: The subscription is already marked as 'canceled' in DB by ProcessAsaasWebhookJob.
        // If Asaas cancellation fails here, it requires manual intervention via the Asaas dashboard.
        // An alert/notification should be sent here in production (Slack, e-mail, etc.).
    }
}
