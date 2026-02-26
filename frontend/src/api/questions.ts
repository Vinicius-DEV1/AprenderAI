import api from './axios';

export const getQuestions = async (params?: Record<string, any>) => {
    const response = await api.get('/api/v1/questions', { params });
    return response.data;
};

export const getQuestion = async (id: number | string) => {
    const response = await api.get(`/api/v1/questions/${id}`);
    return response.data;
};
