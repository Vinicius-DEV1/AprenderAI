import axios from './axios';

/**
 * Checkout tracking API — all calls are fire-and-forget.
 * Errors are swallowed to never interrupt the checkout flow.
 */

const safe = (fn: () => Promise<any>) => {
    fn().catch(() => {/* intentionally silent */});
};

export const trackIntention = (planId: number | string, sourcePage: string) => {
    safe(() => axios.post('/api/v1/tracking/intention', { plan_id: planId, source_page: sourcePage }));
};

export const trackEvent = (
    eventType: string,
    planId?: number | string,
    checkoutStep?: string,
    paymentMethod?: string,
    metadata?: Record<string, any>
) => {
    safe(() => axios.post('/api/v1/tracking/event', {
        event_type: eventType,
        plan_id: planId ?? null,
        checkout_step: checkoutStep ?? null,
        payment_method: paymentMethod ?? null,
        metadata: metadata ?? null,
    }));
};

export const trackFrontendError = (
    errorMessage: string,
    stackTrace?: string,
    planId?: number | string,
    checkoutStep?: string,
) => {
    safe(() => axios.post('/api/v1/tracking/error', {
        error_message: String(errorMessage).slice(0, 2000),
        stack_trace: stackTrace ? String(stackTrace).slice(0, 5000) : null,
        plan_id: planId ?? null,
        checkout_step: checkoutStep ?? null,
    }));
};

export const trackAbandonment = (
    planId?: number | string,
    lastStepReached?: string,
    timeSpentSeconds?: number,
    paymentMethodSelected?: string,
    hadCoupon?: boolean,
) => {
    safe(() => axios.post('/api/v1/tracking/abandonment', {
        plan_id: planId ?? null,
        last_step_reached: lastStepReached ?? null,
        time_spent_seconds: timeSpentSeconds ?? null,
        payment_method_selected: paymentMethodSelected ?? null,
        had_coupon: hadCoupon ?? false,
    }));
};
