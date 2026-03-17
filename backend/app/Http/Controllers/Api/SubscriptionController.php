<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\AsaasService;
use App\Services\CheckoutTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class SubscriptionController extends Controller
{
    protected AsaasService $asaasService;
    protected CheckoutTrackingService $tracking;

    public function __construct(AsaasService $asaasService, CheckoutTrackingService $tracking)
    {
        $this->asaasService = $asaasService;
        $this->tracking = $tracking;
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

        // Track checkout opened + payment initiated
        $this->tracking->trackEvent(
            $user->id, 'checkout_opened', $plan->id, 'checkout',
            $request->payment_method ?? null, [], $request
        );
        $this->tracking->trackEvent(
            $user->id, 'payment_initiated', $plan->id, 'payment',
            $request->payment_method, ['installment_count' => $request->installment_count ?? null], $request
        );

        // ── GUARD: Detectar assinatura parcelada ativa para upgrade/downgrade ──
        $activeInstallment = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('installment_count')
            ->where('current_period_end', '>', now())
            ->with('plan')
            ->first();

        if ($activeInstallment) {
            $currentLevel = $activeInstallment->plan->getLevel();
            $newLevel = $plan->getLevel();

            $currentPlanPrice = (float) $activeInstallment->plan->annual_price;
            $newPlanPrice = (float) $plan->annual_price;

            // BLOQUEAR DOWNGRADE OU RECOMPRA: plano novo tem nível <= atual
            if ($newLevel <= $currentLevel) {
                return response()->json([
                    'message' => 'Você possui um plano anual parcelado ativo até ' .
                        $activeInstallment->current_period_end->format('d/m/Y') .
                        '. Não é possível fazer downgrade durante este período.'
                ], 400);
            }

            // UPGRADE PRO-RATA: cobrar diferença parcelada nos meses restantes
            $remainingMonths = (int) ceil(now()->diffInDays($activeInstallment->current_period_end) / 30);
            $remainingMonths = max(1, min(12, $remainingMonths));

            $currentMonthly = $currentPlanPrice / 12;
            $newMonthly = $newPlanPrice / 12;
            $deltaPerMonth = round($newMonthly - $currentMonthly, 2);
            $upgradeTotal = round($deltaPerMonth * $remainingMonths, 2);

            if ($upgradeTotal <= 0) {
                return response()->json(['message' => 'Valor do upgrade calculado como zero ou negativo.'], 400);
            }

            if (!in_array($request->payment_method, ['credit_card', 'pix'])) {
                return response()->json(['message' => 'Método de pagamento inválido para upgrade.'], 400);
            }

            // A flag is_installment no request define se o usuário escolheu parcelar ou pagar à vista
            $isInstallmentUpgrade = $request->payment_method === 'credit_card' && filter_var($request->is_installment, FILTER_VALIDATE_BOOLEAN);

            try {
                $cardData = [
                    'cpf' => $request->cpf,
                    'holder_name' => $request->card_name,
                    'number' => $request->card_number,
                    'expiry_month' => $request->card_expiry_month,
                    'expiry_year' => $request->card_expiry_year,
                    'ccv' => $request->card_ccv,
                    'postal_code' => $request->postal_code,
                    'address_number' => $request->address_number,
                    'phone' => $request->phone,
                ];

                $idempotencyKey = md5($user->id . '_upgrade_' . $plan->id . '_' . now()->format('Y-m-d H:i'));

                if ($isInstallmentUpgrade) {
                    // Se o usuário selecionou parcelas na tela, respeita o limite dos meses restantes
                    $reqUpgradeInstallments = (int) $request->input('installment_count', $remainingMonths);
                    $upgradeInstallmentsCount = max(2, min($remainingMonths, $reqUpgradeInstallments));

                    // Criar um plano "virtual" com o preço delta para reusar createInstallmentPayment
                    $upgradePlan = new \stdClass();
                    $upgradePlan->name = "Upgrade {$activeInstallment->plan->name} → {$plan->name}";
                    $upgradePlan->annual_price = $upgradeTotal;
                    $upgradePlan->id = $plan->id;

                    $asaasPayment = $this->asaasService->createInstallmentPayment(
                        $user,
                        $upgradePlan,
                        $upgradeInstallmentsCount,
                        'credit_card',
                        $cardData,
                        null,
                        null,
                        $idempotencyKey
                    );
                    $upgradeGatewayId = $asaasPayment['installment'] ?? $asaasPayment['id'];
                    $billingType = 'installment';
                } else {
                    $description = "Upgrade Pro-rata: {$activeInstallment->plan->name} → {$plan->name}";
                    $asaasPayment = $this->asaasService->createSinglePayment(
                        $user,
                        $upgradeTotal,
                        $description,
                        $request->payment_method,
                        $cardData,
                        null,
                        $idempotencyKey
                    );
                    $upgradeGatewayId = $asaasPayment['id'];
                    $billingType = $request->payment_method === 'pix' ? 'pix' : 'credit_card';
                }

                // Atualizar a subscription existente para o novo plano
                $activeInstallment->update([
                    'plan_id' => $plan->id,
                    'amount' => (float) $plan->annual_price,
                ]);

                // Atualizar o user para o novo plano
                $user->update([
                    'plan_id' => $plan->id,
                ]);

                // Criar subscription de upgrade para rastrear o pagamento delta
                Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                    'gateway' => 'asaas',
                    'gateway_id' => $upgradeGatewayId,
                    'billing_type' => $billingType,
                    'installment_count' => $isInstallmentUpgrade ? $remainingMonths : null,
                    'amount' => $upgradeTotal,
                    'is_sandbox' => $this->asaasService->isSandbox(),
                    'current_period_start' => now(),
                    'current_period_end' => $activeInstallment->current_period_end,
                ]);

                PaymentLog::create([
                    'user_id' => $user->id,
                    'gateway' => 'asaas',
                    'gateway_payment_id' => $asaasPayment['id'] ?? null,
                    'gateway_subscription_id' => $upgradeGatewayId,
                    'event' => $isInstallmentUpgrade ? 'CHECKOUT_UPGRADE_INSTALLMENT' : 'CHECKOUT_UPGRADE_SINGLE',
                    'status' => 'success',
                    'is_sandbox' => $this->asaasService->isSandbox(),
                    'raw_response' => PaymentLog::sanitize($asaasPayment),
                ]);

                // Track upgrade success and convert intention (only if not PIX)
                if ($request->payment_method !== 'pix') {
                    $this->tracking->trackEvent(
                        $user->id, 'payment_success', $plan->id, 'confirmation',
                        $request->payment_method,
                        ['upgrade' => true, 'upgrade_total' => $upgradeTotal, 'remaining_months' => $remainingMonths],
                        $request
                    );
                    $this->tracking->convertIntention($user->id, $plan->id, $activeInstallment->id);
                }

                Log::info('[API Checkout] Upgrade pro-rata processado com sucesso.', [
                    'user_id' => $user->id,
                    'from_plan' => $activeInstallment->plan->name,
                    'to_plan' => $plan->name,
                    'delta_total' => $upgradeTotal,
                    'remaining_months' => $remainingMonths,
                    'payment_method' => $request->payment_method,
                    'is_installment' => $isInstallmentUpgrade,
                ]);

                $responseData = [
                    'success' => true,
                    'upgrade' => true,
                    'payment_method' => $request->payment_method,
                ];

                if ($request->payment_method === 'pix') {
                    $pixData = $this->asaasService->getPixQrCode($asaasPayment['id']);
                    $responseData['pix'] = [
                        'payload' => $pixData['payload'] ?? '',
                        'image' => $pixData['encodedImage'] ?? '',
                        'expirationDate' => $pixData['expirationDate'] ?? '',
                    ];
                }

                return response()->json($responseData);

            } catch (\Exception $e) {
                Log::error('[API Checkout] Erro no upgrade pro-rata', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'error' => $e->getMessage(),
                ]);
                $this->tracking->trackError(
                    $user->id, 'payment_failed', $plan->id,
                    $e->getMessage(), 'payment', $request->payment_method ?? null,
                    null, null, $request
                );
                $this->tracking->trackEvent(
                    $user->id, 'payment_failed', $plan->id, 'payment',
                    $request->payment_method ?? null,
                    ['upgrade' => true, 'error' => substr($e->getMessage(), 0, 200)],
                    $request
                );
                return response()->json(['message' => 'Erro ao processar upgrade: ' . $e->getMessage()], 500);
            }
        }

        // Evita duplicidade apenas se o usuário já tem EXATAMENTE o mesmo plano ativo
        // Isso permite upgrades, downgrades ou mudanças para planos diferentes.
        if ($user->plan_id === $plan->id && $user->plan_expires_at && $user->plan_expires_at->isFuture()) {
            return response()->json(['message' => 'Você já possui este plano ativo e ele ainda é válido.'], 400);
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

            // A idempotência aqui é baseada no tempo atualizado num espaço de 1 minuto, previnindo cliques seguidos do mesmo usuário para o mesmo pacote.
            $idempotencyKey = md5($user->id . '_' . $plan->id . '_' . $request->payment_method . '_' . now()->format('Y-m-d H:i'));

            // Se o request enviar 'installment_count', limitamos entre 1 e 12. Se for 1 (ou PIX), isInstallment no Asaas será false.
            $reqInstallmentCount = (int) $request->input('installment_count', 12);
            $wantsInstallments = $request->payment_method === 'credit_card' && $reqInstallmentCount > 1;

            $isInstallment = $plan->isInstallmentEligible() && $wantsInstallments;

            if ($isInstallment) {
                // ── FLUXO PARCELADO (Plano Anual + Cartão) ──
                $installmentCount = max(2, min(12, $reqInstallmentCount));

                try {
                    $asaasPayment = $this->asaasService->createInstallmentPayment(
                        $user,
                        $plan,
                        $installmentCount,
                        $request->payment_method,
                        $cardData,
                        $discount,
                        null,
                        $idempotencyKey
                    );
                } catch (\Exception $asaasEx) {
                    $errorMsg = strtolower($asaasEx->getMessage());
                    $isDuplicateError = str_contains($errorMsg, 'assinatura') || str_contains($errorMsg, 'já possui') || str_contains($errorMsg, 'uma transação');

                    if ($isDuplicateError) {
                        Log::warning('[Asaas] Bloqueado por limite. Tentando Ghost Customer (installment).', ['user_id' => $user->id]);
                        $ghostCustomerId = $this->asaasService->createGhostCustomer($user, $cardData['cpf'] ?? null);
                        $asaasPayment = $this->asaasService->createInstallmentPayment(
                            $user,
                            $plan,
                            $installmentCount,
                            $request->payment_method,
                            $cardData,
                            $discount,
                            $ghostCustomerId,
                            $idempotencyKey
                        );
                    } else {
                        throw $asaasEx;
                    }
                }

                // O Asaas retorna 'installment' como ID agrupador das parcelas
                $gatewayId = $asaasPayment['installment'] ?? $asaasPayment['id'];

                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                    'gateway' => 'asaas',
                    'gateway_id' => $gatewayId,
                    'billing_type' => 'installment',
                    'installment_count' => $installmentCount,
                    'amount' => $plan->annual_price,
                    'is_sandbox' => $this->asaasService->isSandbox(),
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYear(),
                ]);

                PaymentLog::create([
                    'user_id' => $user->id,
                    'gateway' => 'asaas',
                    'gateway_payment_id' => $asaasPayment['id'] ?? null,
                    'gateway_subscription_id' => $gatewayId,
                    'event' => 'CHECKOUT_INSTALLMENT',
                    'status' => 'success',
                    'is_sandbox' => $this->asaasService->isSandbox(),
                    'raw_response' => PaymentLog::sanitize($asaasPayment),
                ]);

                // Track installment success and convert intention
                $this->tracking->trackEvent(
                    $user->id, 'payment_success', $plan->id, 'confirmation',
                    'credit_card',
                    ['type' => 'installment', 'installment_count' => $installmentCount, 'amount' => $plan->annual_price],
                    $request
                );
                $this->tracking->convertIntention($user->id, $plan->id, $subscription->id);

            } else {
                // ── FLUXO RECORRÊNCIA (Mensal ou Anual PIX) ──
                try {
                    $asaasSubscription = $this->asaasService->createSubscription(
                        $user,
                        $plan,
                        $request->payment_method,
                        $cardData,
                        $discount,
                        null,
                        $idempotencyKey
                    );
                } catch (\Exception $asaasEx) {
                    $errorMsg = strtolower($asaasEx->getMessage());
                    $isDuplicateError = str_contains($errorMsg, 'assinatura') || str_contains($errorMsg, 'já possui') || str_contains($errorMsg, 'uma transação');

                    if ($isDuplicateError) {
                        Log::warning('[Asaas] Bloqueado por limite de assinaturas. Executando estratégia Ghost Customer.', [
                            'user_id' => $user->id,
                            'error' => $errorMsg
                        ]);
                        $ghostCustomerId = $this->asaasService->createGhostCustomer($user, $cardData['cpf'] ?? null);
                        $asaasSubscription = $this->asaasService->createSubscription(
                            $user,
                            $plan,
                            $request->payment_method,
                            $cardData,
                            $discount,
                            $ghostCustomerId,
                            $idempotencyKey
                        );
                        Log::info('[Asaas] Estratégia Ghost Customer bem sucedida.', ['new_customer_id' => $ghostCustomerId]);
                    } else {
                        throw $asaasEx;
                    }
                }

                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                    'gateway' => 'asaas',
                    'gateway_id' => $asaasSubscription['id'],
                    'amount' => $plan->price,
                    'is_sandbox' => $this->asaasService->isSandbox(),
                    'current_period_start' => now(),
                    'current_period_end' => $plan->interval === 'yearly' ? now()->addYear() : now()->addMonth(),
                ]);

                PaymentLog::create([
                    'user_id' => $user->id,
                    'gateway' => 'asaas',
                    'gateway_subscription_id' => $asaasSubscription['id'],
                    'event' => 'CHECKOUT_' . strtoupper($request->payment_method),
                    'status' => 'success',
                    'is_sandbox' => $this->asaasService->isSandbox(),
                    'raw_response' => PaymentLog::sanitize($asaasSubscription),
                ]);

                // Track recurring success and convert intention (only if not PIX)
                if ($request->payment_method !== 'pix') {
                    $this->tracking->trackEvent(
                        $user->id, 'payment_success', $plan->id, 'confirmation',
                        $request->payment_method,
                        ['type' => 'recurring', 'amount' => $plan->price, 'interval' => $plan->interval],
                        $request
                    );
                    $this->tracking->convertIntention($user->id, $plan->id, $subscription->id);
                }
            }

            if ($coupon && $discount) {
                $coupon->increment('used_count');
                $coupon->users()->attach($user->id, ['order_id' => $subscription->gateway_id]);
            }

            $responseData = [
                'success' => true,
                'subscription_id' => $subscription->id,
                'payment_method' => $request->payment_method,
            ];

            // PIX flow — somente para recorrência (parcelamento não suporta PIX)
            if (!$isInstallment && $request->payment_method === 'pix') {
                $payment = $this->asaasService->getFirstPendingPayment($subscription->gateway_id);
                if ($payment) {
                    $pixData = $this->asaasService->getPixQrCode($payment['id']);
                    if ($pixData) {
                        $pixExpiresAt = now()->addMinutes(30); // QR Code válido por 30 minutos

                        $subscription->update([
                            'billing_type' => 'pix',
                            'pix_payload' => $pixData['payload'],
                            'pix_image' => $pixData['encodedImage'],
                            'pix_expires_at' => $pixExpiresAt,
                        ]);

                        $responseData['pix'] = [
                            'payload' => $pixData['payload'],
                            'image' => $pixData['encodedImage'],
                            'expires_at' => $pixExpiresAt->toISOString(),
                        ];

                        $this->tracking->trackEvent(
                            $user->id, 'pix_generated', $plan->id, 'payment',
                            'pix', ['expires_at' => $pixExpiresAt->toISOString()], $request
                        );
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

            // Track payment failure
            $this->tracking->trackError(
                $user->id, 'payment_failed', $plan->id,
                $e->getMessage(), 'payment', $request->payment_method ?? null,
                null, null, $request
            );
            $this->tracking->trackEvent(
                $user->id, 'payment_failed', $plan->id, 'payment',
                $request->payment_method ?? null,
                ['error' => substr($e->getMessage(), 0, 200)],
                $request
            );

            return response()->json(['message' => 'Erro ao processar pagamento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Retorna preview do custo de upgrade pro-rata para planos parcelados.
     */
    public function upgradePreview(Plan $plan)
    {
        $user = Auth::user();

        $activeInstallment = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('installment_count')
            ->where('current_period_end', '>', now())
            ->with('plan')
            ->first();

        if (!$activeInstallment) {
            return response()->json(['has_active_installment' => false]);
        }

        $currentPlanPrice = (float) $activeInstallment->plan->annual_price;
        $newPlanPrice = (float) $plan->annual_price;

        if ($newPlanPrice <= $currentPlanPrice) {
            return response()->json([
                'has_active_installment' => true,
                'is_downgrade' => true,
                'blocked' => true,
                'message' => 'Não é possível fazer downgrade durante plano anual parcelado.',
                'current_plan_name' => $activeInstallment->plan->name,
                'expires_at' => $activeInstallment->current_period_end->format('d/m/Y'),
            ]);
        }

        $remainingMonths = (int) ceil(now()->diffInDays($activeInstallment->current_period_end) / 30);
        $remainingMonths = max(1, min(12, $remainingMonths));

        $currentMonthly = $currentPlanPrice / 12;
        $newMonthly = $newPlanPrice / 12;
        $deltaPerMonth = round($newMonthly - $currentMonthly, 2);
        $upgradeTotal = round($deltaPerMonth * $remainingMonths, 2);

        return response()->json([
            'has_active_installment' => true,
            'is_upgrade' => true,
            'current_plan_name' => $activeInstallment->plan->name,
            'new_plan_name' => $plan->name,
            'remaining_months' => $remainingMonths,
            'delta_per_month' => $deltaPerMonth,
            'upgrade_total' => $upgradeTotal,
            'expires_at' => $activeInstallment->current_period_end->format('d/m/Y'),
        ]);
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
