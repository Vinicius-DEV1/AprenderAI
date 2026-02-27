import api from './axios';

export const getEssays = async (page = 1) => {
    const response = await api.get(`/api/v1/essays?page=${page}`);
    return response.data;
};

export const getEssay = async (id: number | string) => {
    const response = await api.get(`/api/v1/essays/${id}`);
    return response.data;
};

// STEP 1: Create draft
export const createEssayDraft = async (data: { type: string, time_limit: number }) => {
    const response = await api.post('/api/v1/essays', data);
    return response.data;
};

// STEP 2: Start topic generation
export const startTopicGeneration = async (id: number | string) => {
    const response = await api.post(`/api/v1/essays/${id}/start-topic`);
    return response.data;
};

export const getTopicStatus = async (id: number | string) => {
    const response = await api.get(`/api/v1/essays/${id}/topic-status`);
    return response.data;
};

// Compatibilitiy export
export const createEssay = async (data: any) => {
    return createEssayDraft(data);
};

// STEP 3: Submit essay
export const submitEssay = async (id: number | string, data: any) => {
    const isFormData = data instanceof FormData;
    const response = await api.post(`/api/v1/essays/${id}/submit`, data, {
        headers: {
            'Content-Type': isFormData ? 'multipart/form-data' : 'application/json',
        },
    });
    return response.data;
};
