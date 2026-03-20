<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\UserNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * NotifyPendingPixPayments — Pix payment recovery command.
 *
 * Runs every 5 minutes (see console.php schedule).
 *
 * Logic:
 *  1. Find all subscriptions with billing_type=pix, status=pending.
 *  2. If the subscription was created > 15 minutes ago AND the Pix QR Code
 *     has NOT yet expired → fire a "payment_pending" nudge notification.
 *  3. If the Pix QR Code HAS expired AND payment is still pending → fire a
 *     "payment_expired" notification prompting the user to generate a new QR.
 *
 * Both flows use `notifyUserOnce()` to avoid duplicate notifications.
 */
class NotifyPendingPixPayments extends Command
{
    protected $signature = 'pix:notify-pending';

    protected $description = 'Send in-app notifications to users with pending Pix payments';

    public function handle(): int
    {
        $pendingSubs = Subscription::with(['user', 'plan'])
            ->where('status', 'pending')
            ->where('billing_type', 'pix')
            ->whereNotNull('pix_expires_at')
            ->get();

        $this->info("Found {$pendingSubs->count()} pending Pix subscription(s).");

        foreach ($pendingSubs as $subscription) {
            $user = $subscription->user;
            $plan = $subscription->plan;

            if (!$user || !$plan) {
                continue;
            }

            $planName  = $plan->name ?? 'Assinatura';
            $pixExpiry = $subscription->pix_expires_at;
            $createdAt = $subscription->created_at;

            // Fifteen minutes past since QR Code was generated
            $past15min = $createdAt && $createdAt->lt(now()->subMinutes(15));

            // QR Code already expired
            $expired = $pixExpiry && $pixExpiry->isPast();

            if ($expired) {
                // ── SCENARIO 2: QR Code expired, payment still pending ──────────
                $this->line("  → Sub #{$subscription->id} expired. Sending payment_expired notification to user #{$user->id}");

                UserNotification::notifyUserOnce(
                    $user->id,
                    'payment_expired',
                    $subscription->id,
                    [
                        'title'      => 'QR Code expirado',
                        'body'       => 'Seu código Pix expirou antes da confirmação. Gere um novo para concluir sua assinatura.',
                        'action_url' => null, // handled in frontend via related_payment_id
                        'meta'       => [
                            'plan_name'      => $planName,
                            'amount'         => (float) $subscription->amount,
                            'subscription_id'=> $subscription->id,
                            'action'         => 'regenerate_pix',
                        ],
                    ]
                );

                Log::info('[pix:notify-pending] payment_expired notification dispatched', [
                    'user_id'         => $user->id,
                    'subscription_id' => $subscription->id,
                ]);

            } elseif ($past15min) {
                // ── SCENARIO 1: 15 minutes passed, QR Code still valid ──────────
                $this->line("  → Sub #{$subscription->id} pending 15+ min. Sending payment_pending notification to user #{$user->id}");

                UserNotification::notifyUserOnce(
                    $user->id,
                    'payment_pending',
                    $subscription->id,
                    [
                        'title'      => 'Pagamento pendente',
                        'body'       => 'Seu Pix foi gerado, mas o pagamento ainda não foi concluído. Finalize para ativar seu acesso.',
                        'action_url' => null,
                        'meta'       => [
                            'plan_name'      => $planName,
                            'amount'         => (float) $subscription->amount,
                            'subscription_id'=> $subscription->id,
                            'pix_expires_at' => $pixExpiry?->toISOString(),
                            'action'         => 'resume_pix',
                        ],
                    ]
                );

                Log::info('[pix:notify-pending] payment_pending notification dispatched', [
                    'user_id'         => $user->id,
                    'subscription_id' => $subscription->id,
                ]);
            } else {
                $this->line("  → Sub #{$subscription->id} pending but < 15 min old. Skipping.");
            }
        }

        return Command::SUCCESS;
    }
}
