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
            'asaas_api_key' => Configuration::get('asaas_api_key'),
            'asaas_sandbox' => Configuration::get('asaas_sandbox', false),
            'payment_active' => Configuration::get('payment_active', true),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'asaas_api_key' => 'nullable|string',
            'asaas_sandbox' => 'boolean',
            'payment_active' => 'boolean',
        ]);

        Configuration::set('asaas_api_key', $request->asaas_api_key);
        Configuration::set('asaas_sandbox', $request->has('asaas_sandbox'));
        Configuration::set('payment_active', $request->has('payment_active'));

        return back()->with('success', 'Configurações de pagamento atualizadas com sucesso!');
    }
}
