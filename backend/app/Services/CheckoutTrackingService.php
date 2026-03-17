<?php

namespace App\Services;

use App\Models\CheckoutAbandonment;
use App\Models\CheckoutError;
use App\Models\CheckoutEvent;
use App\Models\PurchaseIntention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Central service for tracking all checkout-related events, errors,
 * purchase intentions, and abandonments.
 *
 * All methods are designed to fail silently — tracking must never
 * interrupt or break the actual checkout flow.
 */
class CheckoutTrackingService
{
    /**
     * Track a checkout funnel event.
     */
    public function trackEvent(
        ?int $userId,
        string $eventType,
        ?int $planId = null,
        ?string $checkoutStep = null,
        ?string $paymentMethod = null,
        array $metadata = [],
        ?Request $request = null
    ): void {
        try {
            CheckoutEvent::create([
                'user_id'        => $userId,
                'session_id'     => $this->getSessionId($request),
                'event_type'     => $eventType,
                'plan_id'        => $planId,
                'checkout_step'  => $checkoutStep,
                'payment_method' => $paymentMethod,
                'device'         => $this->detectDevice($request),
                'browser'        => $request?->userAgent() ? substr($request->userAgent(), 0, 100) : null,
                'ip'             => $request?->ip(),
                'metadata'       => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CheckoutTracking] Failed to track event', [
                'event_type' => $eventType,
                'user_id'    => $userId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a purchase intention when a user clicks a plan subscribe button.
     */
    public function recordIntention(
        ?int $userId,
        int $planId,
        float $planAmount,
        string $sourcePage,
        ?Request $request = null
    ): ?PurchaseIntention {
        try {
            return PurchaseIntention::create([
                'user_id'     => $userId,
                'plan_id'     => $planId,
                'plan_amount'  => $planAmount,
                'had_coupon'   => $request?->has('coupon_code') || ($request?->input('metadata.had_coupon') === true),
                'source_page'  => $sourcePage,
                'device'      => $this->detectDevice($request),
                'browser'     => $request?->userAgent() ? substr($request->userAgent(), 0, 100) : null,
                'ip'          => $request?->ip(),
                'status'      => PurchaseIntention::STATUS_PENDING,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CheckoutTracking] Failed to record intention', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Convert the most recent pending or abandoned purchase intention for a user+plan combo.
     * Returns true if it successfully converted an intention, false otherwise.
     */
    public function convertIntention(int $userId, int $planId, int $subscriptionId): bool
    {
        try {
            $intention = PurchaseIntention::where('user_id', $userId)
                ->where('plan_id', $planId)
                ->whereIn('status', [PurchaseIntention::STATUS_PENDING, PurchaseIntention::STATUS_ABANDONED])
                ->latest()
                ->first();

            if ($intention) {
                // Return false if it was already converted
                if ($intention->status === PurchaseIntention::STATUS_CONVERTED) {
                    return false;
                }

                $subscription = \App\Models\Subscription::find($subscriptionId);
                $finalAmount = $subscription ? (float) $subscription->amount : $intention->plan_amount;

                $timeToConvert = (int) $intention->created_at->diffInSeconds(now());
                $intention->update([
                    'status'                   => PurchaseIntention::STATUS_CONVERTED,
                    'converted_at'             => now(),
                    'plan_amount'              => $finalAmount,
                    'time_to_convert_seconds'  => $timeToConvert,
                    'subscription_id'          => $subscriptionId,
                ]);
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('[CheckoutTracking] Failed to convert intention', [
                'user_id' => $userId,
                'plan_id' => $planId,
                'error'   => $e->getMessage(),
            ]);
        }
        return false;
    }

    /**
     * Mark the most recent pending intention as abandoned.
     */
    public function abandonIntention(int $userId, int $planId): void
    {
        try {
            PurchaseIntention::where('user_id', $userId)
                ->where('plan_id', $planId)
                ->where('status', PurchaseIntention::STATUS_PENDING)
                ->latest()
                ->first()
                ?->update(['status' => PurchaseIntention::STATUS_ABANDONED]);
        } catch (\Throwable $e) {
            Log::warning('[CheckoutTracking] Failed to abandon intention', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track a checkout error (backend or frontend).
     */
    public function trackError(
        ?int $userId,
        string $errorType,
        ?int $planId,
        string $errorMessage,
        ?string $checkoutStep = null,
        ?string $paymentMethod = null,
        ?array $gatewayResponse = null,
        ?string $stackTrace = null,
        ?Request $request = null
    ): void {
        try {
            CheckoutError::create([
                'user_id'          => $userId,
                'plan_id'          => $planId,
                'error_type'       => $errorType,
                'error_message'    => substr($errorMessage, 0, 1000),
                'stack_trace'      => $stackTrace ? substr($stackTrace, 0, 5000) : null,
                'gateway_response' => $gatewayResponse,
                'checkout_step'    => $checkoutStep,
                'payment_method'   => $paymentMethod,
                'device'           => $this->detectDevice($request),
                'browser'          => $request?->userAgent() ? substr($request->userAgent(), 0, 100) : null,
                'ip'               => $request?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CheckoutTracking] Failed to track error', [
                'error_type' => $errorType,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a checkout abandonment event.
     */
    public function recordAbandonment(
        ?int $userId,
        ?int $planId,
        ?string $lastStep,
        ?int $timeSpentSeconds,
        ?string $paymentMethodSelected,
        bool $hadCoupon = false,
        ?Request $request = null
    ): void {
        try {
            CheckoutAbandonment::create([
                'user_id'                  => $userId,
                'plan_id'                  => $planId,
                'last_step_reached'        => $lastStep,
                'time_spent_seconds'       => $timeSpentSeconds,
                'payment_method_selected'  => $paymentMethodSelected,
                'had_coupon'               => $hadCoupon,
                'ip'                       => $request?->ip(),
                'device'                   => $this->detectDevice($request),
                'browser'                  => $request?->userAgent() ? substr($request->userAgent(), 0, 100) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[CheckoutTracking] Failed to record abandonment', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract or generate a session ID from the request.
     * Checks X-Session-Id header (sent by frontend), then falls back to a UUID.
     */
    public function getSessionId(?Request $request): ?string
    {
        if (!$request) return null;
        return $request->header('X-Session-Id')
            ?? $request->cookie('session_tracking_id')
            ?? null;
    }

    /**
     * Detect device category from User-Agent.
     */
    protected function detectDevice(?Request $request): ?string
    {
        if (!$request || !$request->userAgent()) return null;

        $ua = strtolower($request->userAgent());
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'mobile';
        }
        if (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            return 'tablet';
        }
        return 'desktop';
    }
}
