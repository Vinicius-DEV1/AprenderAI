<?php

namespace App\Http\Controllers;

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

        // Verifica validade do cupom (data, status, limite de uso)
        if (!$coupon || !$coupon->isValid(Auth::user())) {
            return response()->json([
                'valid' => false,
                'message' => 'Cupom inválido ou expirado.'
            ]);
        }

        // Calcula novo preço
        $newPrice = $plan->price;
        if ($coupon->type === 'percent') {
            $newPrice = $plan->price * (1 - ($coupon->value / 100));
        }
        else {
            $newPrice = max(0, $plan->price - $coupon->value);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Cupom aplicado com sucesso!',
            'new_price' => $newPrice
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
     */
    public function store(Request $request, Plan $plan)
    {
        $request->validate([
            'payment_method' => 'required|in:credit_card,pix',
            'card_name' => 'required_if:payment_method,credit_card',
            'card_number' => 'required_if:payment_method,credit_card',
            'card_expiry_month' => 'required_if:payment_method,credit_card',
            'card_expiry_year' => 'required_if:payment_method,credit_card',
            'card_ccv' => 'required_if:payment_method,credit_card',
            'card_cpf' => 'required_if:payment_method,credit_card',
            'coupon_code' => 'nullable|string|exists:coupons,code',
        ]);

        try {
            $user = Auth::user();

            // Lógica de Cupom
            $discount = null;
            $coupon = null;

            if ($request->coupon_code) {
                $coupon = \App\Models\Coupon::where('code', $request->coupon_code)->first();
                if ($coupon && $coupon->isValid($user)) {
                    $discount = [
                        'value' => $coupon->value,
                        'type' => $coupon->type
                    ];
                }
            }

            // Dados do Cartão (se aplicável)
            $cardData = [];
            if ($request->payment_method === 'credit_card') {
                $cardData = [
                    'holder_name' => $request->card_name,
                    'number' => $request->card_number,
                    'expiry_month' => $request->card_expiry_month,
                    'expiry_year' => $request->card_expiry_year,
                    'ccv' => $request->card_ccv,
                    'cpf' => $request->card_cpf,
                ];
            }

            // Cria Assinatura no Asaas
            $asaasSubscription = $this->asaasService->createSubscription($user, $plan, $request->payment_method, $cardData, $discount);

            // Cria Assinatura Local (Pendente)
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending', // Status inicial do Asaas
                'gateway' => 'asaas',
                'gateway_id' => $asaasSubscription['id'],
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(), // Webhook atualizará datas reais
            ]);

            // Registra Uso do Cupom
            if ($coupon && $discount) {
                $coupon->increment('used_count');
                $coupon->users()->attach($user->id, ['order_id' => $asaasSubscription['id']]);
            }

            // Fluxo Pix: Gera QR Code e Exibe
            if ($request->payment_method === 'pix') {
                $payment = $this->asaasService->getFirstPendingPayment($asaasSubscription['id']);

                if ($payment) {
                    $pixData = $this->asaasService->getPixQrCode($payment['id']);
                    if ($pixData) {
                        return view('subscriptions.pending', [
                            'subscription' => $subscription,
                            'pix_payload' => $pixData['payload'],
                            'pix_image' => $pixData['encodedImage'],
                            'plan' => $plan
                        ]);
                    }
                }

                return redirect()->route('dashboard')->with('warning', 'Assinatura criada via Pix, mas houve erro ao gerar QR Code. Verifique seu email.');
            }

            // Limpa o plano da sessão após iniciar o checkout
            session()->forget('selected_plan');

            // Fluxo Cartão: Sucesso (Processamento em Background/Webhook confirmará)
            return view('subscriptions.success', compact('subscription'));

        }
        catch (\Exception $e) {
            Log::error('Erro no Checkout', ['error' => $e->getMessage()]);
            return back()->with('error', 'Erro ao processar pagamento: ' . $e->getMessage())->withInput();
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
