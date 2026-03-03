import api from './axios';

// Required for SPA authentication via Sanctum
export const getCsrfCookie = async () => {
    await api.get('/sanctum/csrf-cookie');
};

export const login = async (data: any) => {
    await getCsrfCookie();
    return api.post('/api/v1/login', data);
};

export const register = async (data: any) => {
    await getCsrfCookie();
    return api.post('/api/v1/register', data);
};

export const logout = async () => {
    return api.post('/api/v1/logout');
};

export const getUser = async () => {
    return api.get('/api/v1/user', { _quiet: true } as any);
};

export const forgotPassword = async (data: any) => {
    await getCsrfCookie();
    return api.post('/api/v1/forgot-password', data);
};

export const resetPassword = async (data: any) => {
    await getCsrfCookie();
    return api.post('/api/v1/reset-password', data);
};

export const sendVerificationEmail = async () => {
    await getCsrfCookie();
    return api.post('/api/v1/email/verification-notification');
};
