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
    return api.get('/api/v1/user');
};
