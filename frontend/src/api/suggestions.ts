import api from './axios';

export const getSuggestions = () =>
  api.get('/api/v1/suggestions');

export const submitSuggestion = (title: string, body?: string) =>
  api.post('/api/v1/suggestions', { title, body });

export const voteSuggestion = (id: number) =>
  api.post(`/api/v1/suggestions/${id}/vote`);

// Admin
export const adminGetSuggestions = (status?: string) =>
  api.get('/api/v1/admin/suggestions', { params: { status } });

export const adminUpdateSuggestionStatus = (id: number, status: string) =>
  api.patch(`/api/v1/admin/suggestions/${id}/status`, { status });
