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

// STEP 1.5: Update draft content
export const updateEssayDraft = async (id: number | string, content: string) => {
    const response = await api.put(`/api/v1/essays/${id}`, { content });
    return response.data;
};

// STEP 2: Start topic generation (Xavier AI)
export const startTopicGeneration = async (id: number | string) => {
    const response = await api.post(`/api/v1/essays/${id}/start-topic`);
    return response.data;
};

export const getTopicStatus = async (id: number | string) => {
    const response = await api.get(`/api/v1/essays/${id}/topic-status`);
    return response.data;
};

// STEP 2: Search existing essay themes from questions bank
export const getEssayThemes = async (params?: { type?: string; keyword?: string; page?: number }) => {
    const response = await api.get('/api/v1/questions/essay-themes', { params });
    return response.data;
};

// STEP 3: Get WritingRule limits for a given type (enem | concurso)
export const getEssayRule = async (type: string) => {
    const response = await api.get(`/api/v1/essays/rule/${type}`);
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
export const retryEssayEvaluation = async (id: number | string) => {
    const response = await api.post(`/api/v1/essays/${id}/retry`);
    return response.data;
};

/** Fire-and-forget: tells the backend to create a "Redação pendente" notification. */
export const notifyEssayAbandoned = async (id: number | string) => {
    const response = await api.post(`/api/v1/essays/${id}/notify-pending`);
    return response.data;
};

/** Invalidates any pending essay notifications when the essay is submitted. */
export const markEssayNotificationDone = async (id: number | string) => {
    const response = await api.post(`/api/v1/essays/${id}/mark-pending-done`);
    return response.data;
};
