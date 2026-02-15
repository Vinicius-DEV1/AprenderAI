<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handleAsaas(Request $request)
    {
        $data = $request->all();
        $event = $data['event'] ?? null;
        
        Log::info('Asaas Webhook:', $data);

        if (!$event) {
            return response()->json(['status' => 'ignored']);
        }

        $payment = $data['payment'] ?? [];
        $subscriptionId = $payment['subscription'] ?? null;

        if (!$subscriptionId) {
            return response()->json(['status' => 'ignored_no_subscription']);
        }

        $subscription = Subscription::where('gateway_id', $subscriptionId)->first();

        if (!$subscription) {
            Log::warning('Asaas Webhook: Subscription not found', ['id' => $subscriptionId]);
            return response()->json(['status' => 'not_found']);
        }

        $user = $subscription->user;
        $plan = $subscription->plan; // Assuming relation exists

        switch ($event) {
            case 'PAYMENT_CONFIRMED':
            case 'PAYMENT_RECEIVED':
                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => now(), // Start of this period
                    // Calculate end based on plan interval
                    'current_period_end' => $plan->interval === 'yearly' ? now()->addYear() : now()->addMonth(),
                ]);

                // Update User Plan
                $user->update([
                    'plan_id' => $plan->id,
                    'plan_started_at' => now(),
                    'plan_expires_at' => $subscription->current_period_end,
                ]);

                // Optional: Reset usage stats if needed
                // $user->update(['simulations_used_this_month' => 0, ...]);
                break;

            case 'PAYMENT_OVERDUE':
            case 'PAYMENT_REFUNDED':
                $subscription->update(['status' => 'past_due']); // or canceled
                // We might want to keep user access until grace period ends, or cut immediately.
                // For now, let's not remove plan_id immediately but plan_expires_at handles access.
                break;
                
            case 'PAYMENT_DELETED':
                $subscription->update(['status' => 'canceled']);
                break;
        }

        return response()->json(['status' => 'ok']);
    }

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
                // ... (existing logic commented out)
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
