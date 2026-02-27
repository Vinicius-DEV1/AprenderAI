<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiPricing;
use App\Models\ApiPricingLog;
use App\Services\AI\PriceCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiPricingController extends Controller
{
    /**
     * GET /api/v1/admin/api-pricing
     * Returns all pricing entries with their last updater.
     */
    public function index()
    {
        $pricing = ApiPricing::with('updatedBy:id,name')
            ->orderBy('api_name')
            ->orderBy('model_key')
            ->get();

        return response()->json(['data' => $pricing]);
    }

    /**
     * PUT /api/v1/admin/api-pricing/{apiPricing}
     * Updates input/output price for a model. Logs the change.
     */
    public function update(Request $request, ApiPricing $apiPricing)
    {
        $validated = $request->validate([
            'input_price_per_1m' => 'required|numeric|min:0',
            'output_price_per_1m' => 'required|numeric|min:0',
        ]);

        // Persist audit log BEFORE updating
        ApiPricingLog::create([
            'api_pricing_id' => $apiPricing->id,
            'updated_by' => Auth::id(),
            'old_input_price_per_1m' => $apiPricing->input_price_per_1m,
            'old_output_price_per_1m' => $apiPricing->output_price_per_1m,
            'new_input_price_per_1m' => $validated['input_price_per_1m'],
            'new_output_price_per_1m' => $validated['output_price_per_1m'],
        ]);

        // Update pricing record
        $apiPricing->update([
            'input_price_per_1m' => $validated['input_price_per_1m'],
            'output_price_per_1m' => $validated['output_price_per_1m'],
            'updated_by' => Auth::id(),
        ]);

        // Invalidate the pricing cache so future calculations use the new values
        app(PriceCalculatorService::class)->invalidateCache();

        // Reload with relations
        $apiPricing->load('updatedBy:id,name');

        return response()->json([
            'message' => 'Preço atualizado com sucesso. O cache foi invalidado.',
            'data' => $apiPricing,
        ]);
    }

    /**
     * GET /api/v1/admin/api-pricing/{apiPricing}/logs
     * Returns the audit history for a specific pricing entry.
     */
    public function logs(ApiPricing $apiPricing)
    {
        $logs = $apiPricing->logs()
            ->with('updatedBy:id,name')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['data' => $logs]);
    }
}
