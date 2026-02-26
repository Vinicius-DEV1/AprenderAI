import axios from './axios';

export const validateCoupon = (planId: number | string, code: string) => {
    return axios.post(`/plans/${planId}/validate-coupon`, { code });
};

export const processCheckout = (planId: number | string, data: any) => {
    return axios.post(`/plans/${planId}/checkout`, data);
};

export const checkSubscriptionStatus = () => {
    return axios.get('/plans/check-status');
};
