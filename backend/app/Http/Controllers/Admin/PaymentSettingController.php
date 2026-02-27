<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    public function index()
    {
        return view('admin.payment-settings', [
            'asaas_api_key'         => Configuration::get('asaas_api_key'),
            'asaas_sandbox'         => (bool) Configuration::get('asaas_sandbox', false),
            'payment_active'        => (bool) Configuration::get('payment_active', true),
            'asaas_webhook_token'   => Configuration::get('asaas_webhook_token', ''),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'asaas_api_key'       => 'nullable|string',
            'asaas_webhook_token' => 'nullable|string',
            'asaas_sandbox'       => 'nullable|boolean',
            'payment_active'      => 'nullable|boolean',
        ]);

        Configuration::set('asaas_api_key',       $request->asaas_api_key);
        Configuration::set('asaas_webhook_token',  $request->asaas_webhook_token);
        Configuration::set('asaas_sandbox',        $request->has('asaas_sandbox'));
        Configuration::set('payment_active',       $request->has('payment_active'));

        return back()->with('success', 'Configurações de pagamento atualizadas com sucesso!');
    }
}
