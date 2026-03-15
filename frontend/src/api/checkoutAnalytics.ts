import axios from './axios';

// ─── Types ─────────────────────────────────────────────────────────────────

export interface CheckoutOverview {
    period_days: number;
    prices_viewed: number;
    purchase_intentions: number;
    checkouts_opened: number;
    payments_initiated: number;
    payments_success: number;
    payments_failed: number;
    abandonments: number;
    conversion_rate: number;
    avg_time_to_convert_minutes: number | null;
}

export interface FunnelStep {
    step: string;
    label: string;
    count: number;
    dropoff_pct: number | null;
}

export interface PlanRanking {
    plan_id: number;
    plan_name: string;
    plan_interval: string;
    plan_price: number;
    clicks: number;
    checkouts: number;
    conversions: number;
    conversion_rate: number;
}

export interface CheckoutAlert {
    type: string;
    severity: 'warning' | 'critical';
    message: string;
    count: number;
    detail: string;
}

// ─── API Functions ─────────────────────────────────────────────────────────

export const getCheckoutOverview = (days = 30) =>
    axios.get<CheckoutOverview>(`/api/v1/admin/checkout/overview?days=${days}`);

export const getCheckoutFunnel = (days = 30) =>
    axios.get<{ funnel: FunnelStep[] }>(`/api/v1/admin/checkout/funnel?days=${days}`);

export const getCheckoutPlansRanking = (days = 30) =>
    axios.get<{ ranking: PlanRanking[] }>(`/api/v1/admin/checkout/plans-ranking?days=${days}`);

export const getCheckoutErrors = (days = 30) =>
    axios.get(`/api/v1/admin/checkout/errors?days=${days}`);

export const getCheckoutUserTimeline = (userId: number | string) =>
    axios.get(`/api/v1/admin/checkout/user-timeline/${userId}`);

export const getCheckoutAbandonments = (days = 30) =>
    axios.get(`/api/v1/admin/checkout/abandonments?days=${days}`);

export const getCheckoutAlerts = () =>
    axios.get<{ alerts: CheckoutAlert[]; total: number }>('/api/v1/admin/checkout/alerts');
