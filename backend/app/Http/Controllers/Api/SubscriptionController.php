<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\AsaasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    protected AsaasService $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    /**
     * Retorna o histórico de assinaturas do usuário.
     */
    public function index()
    {
        $subscriptions = Subscription::with('plan')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json($subscriptions);
    }

    /**
     * Valida um cupom de desconto.
     */
    public function validateCoupon(Request $request, Plan $plan)
    {
        $request->validate(['code' => 'required|string']);

        $coupon = \App\Models\Coupon::where('code', $request->code)->first();

        if (!$coupon || !$coupon->isValid(Auth::user())) {
            return response()->json([
                'valid' => false,
                'message' => 'Cupom inválido ou expirado.',
            ], 422);
        }

        $newPrice = $plan->price;
        if ($coupon->type === 'percent') {
            $newPrice = $plan->price * (1 - ($coupon->value / 100));
        } else {
            $newPrice = max(0, $plan->price - $coupon->value);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Cupom aplicado com sucesso!',
            'new_price' => $newPrice,
        ]);
    }

    /**
     * Processa a assinatura via API.
     */
    public function store(Request $request, Plan $plan)
    {
        $request->validate([
            'payment_method' => 'required|in:credit_card,pix',
            'cpf' => 'required|string',
            'coupon_code' => 'nullable|string|exists:coupons,code',
            // Credit card specific fields (required_if doesn't work well with nested/logic sometimes, but here it's fine)
            'card_name' => 'required_if:payment_method,credit_card',
            'card_number' => 'required_if:payment_method,credit_card',
            'card_expiry_month' => 'required_if:payment_method,credit_card',
            'card_expiry_year' => 'required_if:payment_method,credit_card',
            'card_ccv' => 'required_if:payment_method,credit_card',
            'postal_code' => 'required_if:payment_method,credit_card',
            'address_number' => 'required_if:payment_method,credit_card',
            'phone' => 'required_if:payment_method,credit_card',
        ]);

        $user = Auth::user();

        // Evita duplicidade se o usuário já tem um plano ativo
        if ($user->plan_id && $user->plan_id != 1 && $user->plan_expires_at && $user->plan_expires_at->isFuture()) {
            return response()->json(['message' => 'Você já possui um plano ativo.'], 400);
        }

        try {
            $discount = null;
            $coupon = null;

            if ($request->coupon_code) {
                $coupon = \App\Models\Coupon::where('code', $request->coupon_code)->first();
                if ($coupon && $coupon->isValid($user)) {
                    $discount = [
                        'value' => $coupon->value,
                        'type' => $coupon->type,
                    ];
                }
            }

            $cardData = ['cpf' => $request->cpf];
            if ($request->payment_method === 'credit_card') {
                $cardData = array_merge($cardData, [
                    'holder_name' => $request->card_name,
                    'number' => $request->card_number,
                    'expiry_month' => $request->card_expiry_month,
                    'expiry_year' => $request->card_expiry_year,
                    'ccv' => $request->card_ccv,
                    'postal_code' => $request->postal_code,
                    'address_number' => $request->address_number,
                    'phone' => $request->phone,
                ]);
            }

            $asaasSubscription = $this->asaasService->createSubscription(
                $user,
                $plan,
                $request->payment_method,
                $cardData,
                $discount
            );

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'gateway' => 'asaas',
                'gateway_id' => $asaasSubscription['id'],
                'amount' => $plan->price,
                'current_period_start' => now(),
                'current_period_end' => $plan->interval === 'yearly' ? now()->addYear() : now()->addMonth(),
            ]);

            PaymentLog::create([
                'user_id' => $user->id,
                'gateway' => 'asaas',
                'gateway_subscription_id' => $asaasSubscription['id'],
                'event' => 'CHECKOUT_' . strtoupper($request->payment_method),
                'status' => 'success',
                'raw_response' => PaymentLog::sanitize($asaasSubscription),
            ]);

            if ($coupon && $discount) {
                $coupon->increment('used_count');
                $coupon->users()->attach($user->id, ['order_id' => $asaasSubscription['id']]);
            }

            $responseData = [
                'success' => true,
                'subscription_id' => $subscription->id,
                'payment_method' => $request->payment_method,
            ];

            if ($request->payment_method === 'pix') {
                $payment = $this->asaasService->getFirstPendingPayment($asaasSubscription['id']);
                if ($payment) {
                    $pixData = $this->asaasService->getPixQrCode($payment['id']);
                    if ($pixData) {
                        $subscription->update([
                            'billing_type' => 'pix',
                            'pix_payload' => $pixData['payload'],
                            'pix_image' => $pixData['encodedImage'],
                        ]);

                        $responseData['pix'] = [
                            'payload' => $pixData['payload'],
                            'image' => $pixData['encodedImage'],
                        ];
                    }
                }
            } else {
                $subscription->update(['billing_type' => 'credit_card']);
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::error('[API Checkout] Erro ao processar pagamento', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            PaymentLog::create([
                'user_id' => $user->id,
                'gateway' => 'asaas',
                'event' => 'CHECKOUT_' . strtoupper($request->payment_method ?? 'UNKNOWN'),
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erro ao processar pagamento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Gera a URL de comprovante de um pagamento confirmado.
     */
    public function receiptUrl(Subscription $subscription)
    {
        if ($subscription->user_id !== Auth::id()) {
            return response()->json(['message' => 'Acesso negado.'], 403);
        }

        if (!$subscription->gateway_id) {
            return response()->json(['message' => 'Assinatura sem ID no gateway.'], 422);
        }

        try {
            // Busca o primeiro pagamento CONFIRMADO desta assinatura
            $response = $this->asaasService->getConfirmedPaymentForSubscription($subscription->gateway_id);

            if (!$response) {
                return response()->json(['message' => 'Nenhum pagamento confirmado encontrado para esta assinatura.'], 404);
            }

            return response()->json([
                'receipt_url' => $response['transactionReceiptUrl'] ?? null,
                'invoice_url' => $response['invoiceUrl'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('[API receiptUrl] Erro ao buscar comprovante', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erro ao obter comprovante.'], 500);
        }
    }

    /**
     * Verifica se o plano do usuário foi atualizado.
     */
    public function checkStatus(Request $request)
    {
        $user = $request->user();
        $isActive = $user->plan_id && $user->plan_id != 1 && $user->plan_expires_at && $user->plan_expires_at->isFuture();

        return response()->json([
            'active' => $isActive,
            'plan' => $user->plan ? $user->plan->name : 'Grátis',
            'plan_id' => $user->plan_id,
        ]);
    }
}
