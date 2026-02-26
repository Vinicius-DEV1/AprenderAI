import api from './axios';

export const getDashboardData = async () => {
    const response = await api.get('/api/v1/dashboard');
    return response.data;
};
