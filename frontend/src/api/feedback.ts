import api from './axios';

export const submitFeedback = (featureKey: string, isPositive: boolean, comment?: string) =>
  api.post('/api/v1/feedback', {
    feature_key: featureKey,
    is_positive: isPositive,
    comment,
  });

// Admin
export const adminGetFeedback = () =>
  api.get('/api/v1/admin/feedback');
