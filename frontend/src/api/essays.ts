import api from './axios';

export const getEssays = async (page = 1) => {
    const response = await api.get(`/api/v1/essays?page=${page}`);
    return response.data;
};

export const getEssay = async (id: number | string) => {
    const response = await api.get(`/api/v1/essays/${id}`);
    return response.data;
};

export const createEssay = async (data: { theme: string; content: string }) => {
    const response = await api.post('/api/v1/essays', data);
    return response.data;
};
