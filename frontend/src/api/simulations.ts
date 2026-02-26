import api from './axios';

export const getSimulations = async (page = 1) => {
    const response = await api.get(`/api/v1/simulations?page=${page}`);
    return response.data;
};

export const getSimulation = async (id: number | string, includeAnswers = false) => {
    const response = await api.get(`/api/v1/simulations/${id}`, {
        params: { include_answers: includeAnswers ? 1 : 0 }
    });
    return response.data;
};

export const createSimulation = async (data: any) => {
    const response = await api.post('/api/v1/simulations', data);
    return response.data;
};

export const submitSimulationAnswers = async (id: number | string, data: any) => {
    const response = await api.post(`/api/v1/simulations/${id}/submit`, data);
    return response.data;
};
