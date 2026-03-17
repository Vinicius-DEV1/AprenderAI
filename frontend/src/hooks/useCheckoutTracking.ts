import { useEffect, useRef, useCallback } from 'react';
import { trackEvent, trackAbandonment, trackFrontendError } from '../api/checkoutTracking';

interface UseCheckoutTrackingOptions {
    planId?: number | string;
    paymentMethod?: string;
    hadCoupon?: boolean;
}

/**
 * Hook to instrument a checkout page with tracking events.
 * - Fires 'checkout_opened' on mount
 * - Detects abandonment via beforeunload + cleanup
 * - Exposes helpers for payment_initiated and payment errors
 */
export function useCheckoutTracking({
    planId,
    paymentMethod,
    hadCoupon = false,
}: UseCheckoutTrackingOptions) {
    const mountTimeRef = useRef<number>(Date.now());
    const lastStepRef = useRef<string>('checkout_opened');
    const didConvertRef = useRef<boolean>(false);

    // Track checkout_opened on mount
    useEffect(() => {
        if (!planId) return;
        trackEvent('checkout_opened', planId, 'checkout', paymentMethod);

        // Detect abandonment via beforeunload (best-effort on desktop)
        const handleBeforeUnload = () => {
            if (didConvertRef.current) return;
            const timeSpent = Math.round((Date.now() - mountTimeRef.current) / 1000);
            trackAbandonment(planId, lastStepRef.current, timeSpent, paymentMethod, hadCoupon);
        };

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            // Also fire on normal React unmount (navigation away) if not converted
            if (!didConvertRef.current) {
                const timeSpent = Math.round((Date.now() - mountTimeRef.current) / 1000);
                // Only register abandonment if user spent more than 3s on checkout
                if (timeSpent >= 3) {
                    trackAbandonment(planId, lastStepRef.current, timeSpent, paymentMethod, hadCoupon);
                }
            }
        };
    }, [planId]); // eslint-disable-line react-hooks/exhaustive-deps

    /** Call this when the user submits the payment form */
    const onPaymentInitiated = (method?: string) => {
        lastStepRef.current = 'payment_initiated';
        trackEvent('payment_initiated', planId, 'payment', method ?? paymentMethod);
    };

    /** Call this when payment succeeds — suppresses abandonment tracking */
    const onPaymentSuccess = useCallback(() => {
        didConvertRef.current = true;
        lastStepRef.current = 'payment_success';
    }, []);

    /** Call this when PIX QR code is shown */
    const onPixGenerated = () => {
        lastStepRef.current = 'pix_generated';
    };

    /** Call this on a caught frontend error during checkout */
    const onError = (error: unknown, step?: string) => {
        const msg = error instanceof Error ? error.message : String(error);
        const stack = error instanceof Error ? error.stack : undefined;
        trackFrontendError(msg, stack, planId, step ?? lastStepRef.current);
    };

    return { onPaymentInitiated, onPaymentSuccess, onPixGenerated, onError };
}
