import axios from './axios';

// ─── Types ─────────────────────────────────────────────────────────────────

export interface DeviceConversion {
    device: string;
    intentions: number;
    conversions: number;
}

export interface TopOrigin {
    source_page: string;
    total: number;
}

export interface ApprovalRate {
    payment_method: string;
    success: number;
    total: number;
    rate: number;
}

export interface GeoRegion {
    ip: string;
    total: number;
}

export interface TimelineEvent {
    id: number;
    event_type: string;
    user_name: string;
    user_avatar: string | null;
    plan_name: string;
    payment_method: string | null;
    metadata: any;
    created_at: string;
    time_ago: string;
}

export interface CheckoutOverview {
    period_days: number;
    prices_viewed: number;
    purchase_intentions: number;
    unique_intentions: number;
    gained_revenue: number;
    lost_revenue: number;
    checkouts_opened: number;
    payments_initiated: number;
    payments_success: number;
    payments_failed: number;
    abandonments: number;
    abandonments_coupon: number;
    conversions_coupon: number;
    conversion_rate: number;
    avg_time_to_convert_minutes: number | null;
    devices: DeviceConversion[];
    top_origins: TopOrigin[];
    approval_rates: ApprovalRate[];
    top_regions: GeoRegion[];
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

export interface CheckoutAbandonmentItem {
    id: number;
    user_id: number | null;
    user_name: string;
    user_email: string;
    user_avatar: string | null;
    plan_name: string;
    last_step: string;
    time_spent_mins: number | null;
    payment_method: string | null;
    had_coupon: boolean;
    error_history: { error_type: string; count: number }[];
    created_at: string;
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
    axios.get<{ abandonments: CheckoutAbandonmentItem[] }>(`/api/v1/admin/checkout/abandonments?days=${days}`);

export const getCheckoutAlerts = () =>
    axios.get<{ alerts: CheckoutAlert[]; total: number }>('/api/v1/admin/checkout/alerts');

export const getCheckoutTimeline = (limit = 50) =>
    axios.get<{ events: TimelineEvent[] }>(`/api/v1/admin/checkout/timeline?limit=${limit}`);
