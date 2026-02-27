<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::latest()->paginate(20);
        return response()->json($coupons);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code|uppercase',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $coupon = Coupon::create($validated);

        return response()->json([
            'message' => 'Cupom criado com sucesso!',
            'coupon' => $coupon
        ]);
    }

    public function show(Coupon $coupon)
    {
        return response()->json($coupon);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', Rule::unique('coupons')->ignore($coupon->id), 'uppercase'],
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $coupon->update($validated);

        return response()->json([
            'message' => 'Cupom atualizado com sucesso!',
            'coupon' => $coupon
        ]);
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return response()->json(['message' => 'Cupom excluído com sucesso!']);
    }
}
