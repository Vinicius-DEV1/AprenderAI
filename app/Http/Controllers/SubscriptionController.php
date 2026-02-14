<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function checkout(Plan $plan)
    {
        if ($plan->slug === 'gratuito') {
            return redirect()->route('dashboard')->with('info', 'Este plano é gratuito.');
        }

        $user = Auth::user();

        // Criar preferência no Mercado Pago
        $preference = $this->paymentService->createPreference($plan, $user);

        if (!$preference) {
            return back()->with('error', 'Erro ao iniciar pagamento. Tente novamente.');
        }

        // Criar registro de assinatura pendente (ou atualizar existente)
        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'plan_id' => $plan->id,
                'status' => 'pending',
                'gateway' => 'mercadopago',
                'gateway_id' => $preference->id, // ID da preferência
            ]
        );

        return redirect($preference->init_point); // Redireciona para Mercado Pago
    }

    public function success(Request $request)
    {
        // Aqui idealmente validamos o pagamento via API, mas confiamos no webhook para atualização final
        // Porém, podemos atualizar para 'active' se o status na URL for 'approved' para feedback imediato
        // (Cuidado com segurança, webhook é a fonte da verdade)

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
