<?php

namespace App\Http\Controllers;

use App\Models\PaymentLog;
use App\Models\Subscription;
use App\Models\Configuration;
use App\Services\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Recebe e processa eventos de pagamento do Asaas.
     *
     * SEGURANÇA:
     *  1. CSRF: Esta rota está excluída do VerifyCsrfToken (configurado no bootstrap/app.php)
     *  2. Token: Verificamos o header 'asaas-access-token' contra o valor salvo nas configurações.
     *     Se o token não batir, retornamos 403 imediatamente.
     */
    public function handleAsaas(Request $request)
    {
        // ---- 1. Verificação de Autenticidade do Webhook ----------------------
        $productionToken = Configuration::get('asaas_production_webhook_token', '');
        $sandboxToken = Configuration::get('asaas_sandbox_webhook_token', '');

        $receivedToken = $request->header('asaas-access-token', '');

        $isValid = false;
        $isSandbox = false;

        if (!empty($productionToken) && hash_equals($productionToken, $receivedToken)) {
            $isValid = true;
            $isSandbox = false;
        } elseif (!empty($sandboxToken) && hash_equals($sandboxToken, $receivedToken)) {
            $isValid = true;
            $isSandbox = true;
        }

        // SECURITY: Always reject if token is invalid — no exceptions.
        // The previous code had a bypass: if BOTH tokens were empty, the webhook
        // would accept ANY payload, allowing fake payment fraud.
        if (!$isValid) {
            Log::warning('[Webhook] Token inválido ou ausente', [
                'ip' => $request->ip(),
                'received_token' => $receivedToken ? substr($receivedToken, 0, 8) . '...' : null,
                'production_token_configured' => !empty($productionToken),
                'sandbox_token_configured' => !empty($sandboxToken),
            ]);
            return response()->json(['status' => 'unauthorized'], 403);
        }

        // ---- 2. Extrair dados e despachar para fila (Async) -------------
        $data = $request->all();
        $data['is_sandbox_webhook'] = $isSandbox; // Injected for the Job to know
        $event = $data['event'] ?? null;
        $paymentId = $data['payment']['id'] ?? null;

        Log::info('[Webhook] Recebido e enfileirado', ['event' => $event, 'payment_id' => $paymentId, 'is_sandbox' => $isSandbox]);

        dispatch(new \App\Jobs\ProcessAsaasWebhookJob($data));

        return response()->json(['status' => 'ok', 'message' => 'Enfileirado com sucesso']);
    }
}
