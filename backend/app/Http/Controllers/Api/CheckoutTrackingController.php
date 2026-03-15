<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\CheckoutTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Receives tracking events from the frontend.
 * All endpoints are authenticated (sanctum) but fail gracefully.
 */
class CheckoutTrackingController extends Controller
{
    public function __construct(protected CheckoutTrackingService $tracking) {}

    /**
     * Record a purchase intention (user clicked a plan button).
     * POST /api/v1/tracking/intention
     */
    public function recordIntention(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'plan_id'     => 'required|integer|exists:plans,id',
            'source_page' => 'nullable|string|max:200',
        ]);

        $plan = Plan::find($data['plan_id']);
        $userId = Auth::id();

        $this->tracking->recordIntention(
            $userId,
            $data['plan_id'],
            (float) ($plan?->price ?? 0),
            $data['source_page'] ?? '/',
            $request
        );

        // Also track as plan_clicked event
        $this->tracking->trackEvent(
            $userId,
            'plan_clicked',
            $data['plan_id'],
            'prices',
            null,
            ['source_page' => $data['source_page'] ?? '/'],
            $request
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Track a generic checkout event.
     * POST /api/v1/tracking/event
     */
    public function trackEvent(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'event_type'     => 'required|string|max:50',
            'plan_id'        => 'nullable|integer',
            'checkout_step'  => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|max:30',
            'metadata'       => 'nullable|array',
        ]);

        $this->tracking->trackEvent(
            Auth::id(),
            $data['event_type'],
            $data['plan_id'] ?? null,
            $data['checkout_step'] ?? null,
            $data['payment_method'] ?? null,
            $data['metadata'] ?? [],
            $request
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Track a frontend error.
     * POST /api/v1/tracking/error
     */
    public function trackFrontendError(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'error_message' => 'required|string|max:2000',
            'stack_trace'   => 'nullable|string|max:5000',
            'plan_id'       => 'nullable|integer',
            'checkout_step' => 'nullable|string|max:50',
            'page'          => 'nullable|string|max:200',
        ]);

        $this->tracking->trackError(
            Auth::id(),
            'frontend_error',
            $data['plan_id'] ?? null,
            $data['error_message'],
            $data['checkout_step'] ?? $data['page'] ?? null,
            null,
            null,
            $data['stack_trace'] ?? null,
            $request
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Record a checkout abandonment.
     * POST /api/v1/tracking/abandonment
     */
    public function recordAbandonment(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'plan_id'                  => 'nullable|integer',
            'last_step_reached'        => 'nullable|string|max:50',
            'time_spent_seconds'       => 'nullable|integer|min:0',
            'payment_method_selected'  => 'nullable|string|max:30',
            'had_coupon'               => 'nullable|boolean',
        ]);

        $userId = Auth::id();

        $this->tracking->recordAbandonment(
            $userId,
            $data['plan_id'] ?? null,
            $data['last_step_reached'] ?? null,
            $data['time_spent_seconds'] ?? null,
            $data['payment_method_selected'] ?? null,
            (bool) ($data['had_coupon'] ?? false)
        );

        // Mark corresponding intention as abandoned
        if ($userId && isset($data['plan_id'])) {
            $this->tracking->abandonIntention($userId, $data['plan_id']);
        }

        return response()->json(['ok' => true]);
    }
}
