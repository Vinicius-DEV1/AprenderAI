<?php

namespace App\Http\Controllers;

use App\Services\AsaasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EssayRechargeController extends Controller
{
    protected $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // 1. Identify Plan and Rules
        // "Plano Plus" (15 credits, R$ 20)
        // "Plano Básico" (2 credits, R$ 5)

        $planName = $user->plan ? $user->plan->name : '';

        $credits = 0;
        $price = 0.0;
        $description = '';

        if (stripos($planName, 'Plus') !== false) {
            $credits = 15;
            $price = 20.00;
            $description = 'Recarga +15 Redações (Plano Plus)';
        } elseif (stripos($planName, 'Básico') !== false || stripos($planName, 'Basico') !== false) {
            $credits = 2;
            $price = 5.00;
            $description = 'Recarga +2 Redações (Plano Básico)';
        } else {
            return back()->with('error', 'Seu plano atual não suporta recarga de redações.');
        }

        // 2. Process Payment
        try {
            // Using credit card (defaulting to saved card logic or just generic one-time flow? 
            // The prompt says "use o fluxo de pagamento já existente... se já houver lógica de compra".
            // AsaasService::createSubscription uses specific card data. 
            // Since we don't have a specific "Saved Card" model evident yet (except maybe Asaas customer ID), 
            // we might need to ask for card details again OR if the prompt implies we should just redirect to a checkout.
            // PROMPT SAYS: "Ao clicar nesse botão: Cobrar R$ X. Após confirmação... adicionar".
            // Ideally this would be a modal or a separate page with card input.
            // HOWEVER, checking `SubscriptionController`, it asks for card data in the request.
            // To keep it "surgical", maybe we should assume the user has a card on file? 
            // AsaasService checks `getOrCreateCustomer`.
            // But `createOneTimePayment` I added requires `paymentMethod` and `cardData`.

            // Wait, simply clicking a button usually implies "Use my saved payment method" OR "Go to checkout".
            // Since we didn't build a new checkout page, and the user request describes:
            // "Exibir um botão... Ao clicar nesse botão: Cobrar R$ 20,00... Após confirmação... adicionar".

            // NOTE: Implementing a full Checkout UI for this might be out of "surgical" scope.
            // But we can't charge a card without data.
            // Assumption: The prompt might be implying a simplified view where we redirect to a checkout page similar to plan subscription?
            // OR we use the existing `plans.checkout` view but repurposed?

            // ACTION: Let's create a simplified flow.
            // 1. User clicks "Recarregar".
            // 2. Redirects to a confirmation/checkout page (or reuses existing one).
            // 3. User enters card/pix.
            // 4. Submits to THIS controller.

            // To be strictly "surgical" and follow requirements: "Ao clicar nesse botão... Cobrar".
            // If I implement just the button POSTing here, it will fail without card data.
            // So I should probably handle GET to confirm/select method, and POST to charge.

            // Let's implement `show` (GET) to show payment form (reusing existing styles/logic if possible).
            // But requirements said "Botão só quando limite acabar".

            // Pivot: The prompt says "Ao clicar nesse botão: Cobrar". 
            // If we assume PIX (which Asaas supports well via link or QR), implementation is easier.
            // But user mentioned "R$ 20,00 ... Cobrar".

            // Let's implement the POST directly assuming we might receive card data or default to PIX if easier?
            // No, standard flow is Checkout.

            // Let's add a GET `confirm` route to render a simple payment selection modal/page using minimal new UI.

            if ($request->isMethod('get')) {
                return view('essays.recharge_checkout', compact('planName', 'credits', 'price'));
            }

            // POST handling
            $data = $request->validate([
                'payment_method' => 'required|in:credit_card,pix',
                // Card validation if credit_card
                'card_name' => 'required_if:payment_method,credit_card',
                'card_number' => 'required_if:payment_method,credit_card',
                'card_expiry_month' => 'required_if:payment_method,credit_card',
                'card_expiry_year' => 'required_if:payment_method,credit_card',
                'card_ccv' => 'required_if:payment_method,credit_card',
                'cpf' => 'required|string',
            ]);

            $cardData = ['cpf' => $request->cpf];
            if ($data['payment_method'] === 'credit_card') {
                $cardData = array_merge($cardData, [
                    'holder_name' => $request->card_name,
                    'number' => $request->card_number,
                    'expiry_month' => $request->card_expiry_month,
                    'expiry_year' => $request->card_expiry_year,
                    'ccv' => $request->card_ccv,
                ]);
            }

            $payment = $this->asaasService->createOneTimePayment($user, $price, $description, $data['payment_method'], $cardData);

            // PIX Flow
            if ($data['payment_method'] === 'pix') {
                $pixData = $this->asaasService->getPixQrCode($payment['id']);
                // We need to show this to user. Return a view with QR Code.
                // We can't automatically add credits yet until paid, BUT requirement says:
                // "Após confirmação/sucesso do pagamento, adicionar...".
                // For PIX, this means waiting for Webhook.
                // For Card, Asaas usually returns status. If 'CONFIRMED' or 'RECEIVED', we add.
                // If 'PENDING', we wait.

                // However, often "Success" in simple implementations implies "Order Placed" or optimist UI?
                // Requirements: "Após confirmação/sucesso do pagamento". This implies strict check.
                // Asaas Credit Card is often instant.

                // Let's assume Credit Card success = instant add.
                // Pix = show QR and wait (User won't get credits immediately).

                if ($pixData) {
                    return view('essays.recharge_pending', [
                        'pix_payload' => $pixData['payload'],
                        'pix_image' => $pixData['encodedImage'],
                        'price' => $price,
                        'credits' => $credits
                    ]);
                }
            }

            // Credit Card Success Check
            if ($payment['status'] === 'CONFIRMED' || $payment['status'] === 'RECEIVED') {
                $user->increment('essay_credits', $credits);
                return redirect()->route('essays.index')->with('success', "Recarga realizada com sucesso! +{$credits} redações adicionadas.");
            } else {
                // Pending processing (Analysis etc)
                return redirect()->route('essays.index')->with('info', 'Pagamento em processamento. Seus créditos serão liberados em breve.');
            }

        } catch (\Exception $e) {
            Log::error('Erro na Recarga', ['error' => $e->getMessage()]);
            return back()->with('error', 'Erro ao processar recarga: ' . $e->getMessage());
        }
    }

    /**
     * AJAX endpoint to check if the user's credits have been updated.
     */
    public function checkStatus(Request $request)
    {
        $user = $request->user();
        
        // Return current credits to detect the change on frontend
        return response()->json([
            'credits' => $user->essay_credits,
        ]);
    }
}
