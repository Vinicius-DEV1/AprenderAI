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
