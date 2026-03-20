import axios from './axios';

export const validateCoupon = (planId: number | string, code: string) => {
    return axios.post(`/api/v1/subscriptions/${planId}/validate-coupon`, { code });
};

export const processCheckout = (planId: number | string, data: any) => {
    return axios.post(`/api/v1/subscriptions/${planId}/checkout`, data);
};

export const checkSubscriptionStatus = () => {
    return axios.get('/api/v1/subscriptions/check-status');
};

export const getSubscriptions = () => {
    return axios.get('/api/v1/subscriptions');
};

export const getPaymentReceipt = (subscriptionId: number | string) => {
    return axios.get(`/api/v1/subscriptions/${subscriptionId}/receipt`);
};

export const getUpgradePreview = (planId: number | string) => {
    return axios.get(`/api/v1/subscriptions/${planId}/upgrade-preview`);
};

/** Returns the latest pending Pix subscription (QR Code + expiry) for the current user. */
export const getPendingPixSubscription = () => {
    return axios.get('/api/v1/subscriptions/pending-pix');
};

/** Creates a fresh Pix QR Code for an expired subscription. */
export const regeneratePixPayment = (subscriptionId: number | string) => {
    return axios.post(`/api/v1/subscriptions/${subscriptionId}/regenerate-pix`);
};

