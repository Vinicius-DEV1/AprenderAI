<?php

namespace App\Http\Controllers;

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
     * Valida um cupom de desconto via AJAX.
     */
    public function validateCoupon(Request $request, Plan $plan)
    {
        $request->validate(['code' => 'required|string']);

        $coupon = \App\Models\Coupon::where('code', $request->code)->first();

        if (!$coupon || !$coupon->isValid(Auth::user())) {
            return response()->json([
                'valid'   => false,
                'message' => 'Cupom inválido ou expirado.',
            ]);
        }

        $newPrice = $plan->price;
        if ($coupon->type === 'percent') {
            $newPrice = $plan->price * (1 - ($coupon->value / 100));
        } else {
            $newPrice = max(0, $plan->price - $coupon->value);
        }

        return response()->json([
            'valid'     => true,
            'message'   => 'Cupom aplicado com sucesso!',
            'new_price' => $newPrice,
        ]);
    }

    /**
     * Exibe a página de checkout para um plano.
     */
    public function showCheckout(Plan $plan)
    {
        return view('plans.checkout', compact('plan'));
    }

    /**
     * Processa a assinatura (cria no Asaas e salva localmente).
     *
     * PCI COMPLIANCE:
     * - Os dados do cartão (card_number, card_ccv) são usados apenas neste método,
     *   passados ao AsaasService, e NUNCA logados nem persistidos no nosso banco.
     */
    public function store(Request $request, Plan $plan)
    {
        $request->validate([
            'payment_method'   => 'required|in:credit_card,pix',
            'card_name'        => 'required_if:payment_method,credit_card',
            'card_number'      => 'required_if:payment_method,credit_card',
            'card_expiry_month'=> 'required_if:payment_method,credit_card',
            'card_expiry_year' => 'required_if:payment_method,credit_card',
            'card_ccv'         => 'required_if:payment_method,credit_card',
            'card_cpf'         => 'required_if:payment_method,credit_card',
            'coupon_code'      => 'nullable|string|exists:coupons,code',
        ]);

        $user = Auth::user();

        try {
            // ---- Lógica de Cupom ------------------------------------------------
            $discount = null;
            $coupon   = null;

            if ($request->coupon_code) {
                $coupon = \App\Models\Coupon::where('code', $request->coupon_code)->first();
                if ($coupon && $coupon->isValid($user)) {
                    $discount = [
                        'value' => $coupon->value,
                        'type'  => $coupon->type,
                    ];
                }
            }

            // ---- Dados do Cartão ------------------------------------------------
            // ⚠️ PCI: Estes dados são passados ao gateway mas NUNCA logados/salvos aqui
            $cardData = [];
            if ($request->payment_method === 'credit_card') {
                $cardData = [
                    'holder_name'  => $request->card_name,
                    'number'       => $request->card_number,
                    'expiry_month' => $request->card_expiry_month,
                    'expiry_year'  => $request->card_expiry_year,
                    'ccv'          => $request->card_ccv,
                    'cpf'          => $request->card_cpf,
                ];
            }

            // ---- Criar Assinatura no Asaas --------------------------------------
            $asaasSubscription = $this->asaasService->createSubscription(
                $user, $plan, $request->payment_method, $cardData, $discount
            );

            // ---- Salvar Assinatura Local ----------------------------------------
            $subscription = Subscription::create([
                'user_id'              => $user->id,
                'plan_id'              => $plan->id,
                'status'               => 'pending',
                'gateway'              => 'asaas',
                'gateway_id'           => $asaasSubscription['id'],
                'current_period_start' => now(),
                'current_period_end'   => $plan->interval === 'yearly'
                    ? now()->addYear()
                    : now()->addMonth(),
            ]);

            // ---- Audit Log (PCI-safe) -------------------------------------------
            PaymentLog::create([
                'user_id'                 => $user->id,
                'gateway'                 => 'asaas',
                'gateway_subscription_id' => $asaasSubscription['id'],
                'event'                   => 'CHECKOUT_' . strtoupper($request->payment_method),
                'status'                  => 'success',
                'raw_response'            => PaymentLog::sanitize($asaasSubscription),
            ]);

            // ---- Registrar Uso do Cupom -----------------------------------------
            if ($coupon && $discount) {
                $coupon->increment('used_count');
                $coupon->users()->attach($user->id, ['order_id' => $asaasSubscription['id']]);
            }

            // ---- Fluxo Pix: Gera QR Code ----------------------------------------
            if ($request->payment_method === 'pix') {
                $payment = $this->asaasService->getFirstPendingPayment($asaasSubscription['id']);

                if ($payment) {
                    $pixData = $this->asaasService->getPixQrCode($payment['id']);
                    if ($pixData) {
                        return view('subscriptions.pending', [
                            'subscription' => $subscription,
                            'pix_payload'  => $pixData['payload'],
                            'pix_image'    => $pixData['encodedImage'],
                            'plan'         => $plan,
                        ]);
                    }
                }

                return redirect()->route('dashboard')->with(
                    'warning',
                    'Assinatura criada via Pix, mas houve erro ao gerar QR Code. Verifique seu email.'
                );
            }

            session()->forget('selected_plan');

            return view('subscriptions.success', compact('subscription'));

        } catch (\Exception $e) {
            // ⚠️ PCI: Logar apenas a mensagem de erro, NUNCA $request->all() que contém dados do cartão
            Log::error('[Checkout] Erro ao processar pagamento', [
                'user_id'        => $user->id,
                'plan_id'        => $plan->id,
                'payment_method' => $request->payment_method,
                'error'          => $e->getMessage(),
            ]);

            // Audit log da falha
            PaymentLog::create([
                'user_id'       => $user->id,
                'gateway'       => 'asaas',
                'event'         => 'CHECKOUT_' . strtoupper($request->payment_method ?? 'UNKNOWN'),
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return back()
                ->with('error', 'Erro ao processar pagamento: ' . $e->getMessage())
                ->withInput($request->except(['card_number', 'card_ccv', 'card_expiry_month', 'card_expiry_year']));
        }
    }

    public function success(Request $request)
    {
        return view('subscriptions.success');
    }

    public function failure()
    {
        return view('subscriptions.failure');
    }

    public function pending()
    {
        return view('subscriptions.pending');
    }
}
