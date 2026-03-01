<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentSettingsController extends Controller
{
    /**
     * Get payment settings.
     */
    public function index()
    {
        return response()->json([
            'asaas_api_key' => Configuration::get('asaas_api_key', ''),
            'asaas_webhook_token' => Configuration::get('asaas_webhook_token', ''),
            'asaas_sandbox' => filter_var(Configuration::get('asaas_sandbox', false), FILTER_VALIDATE_BOOLEAN),
            'payment_active' => filter_var(Configuration::get('payment_active', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * Update payment settings.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asaas_api_key' => 'nullable|string',
            'asaas_webhook_token' => 'nullable|string|max:255',
            'asaas_sandbox' => 'boolean',
            'payment_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        foreach ($data as $key => $value) {
            Configuration::set($key, $value);
        }

        return response()->json([
            'message' => 'Configurações de pagamento atualizadas com sucesso!',
            'settings' => $this->index()->getData()
        ]);
    }
}
