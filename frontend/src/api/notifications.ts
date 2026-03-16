import api from './axios';

export const getNotifications = () =>
  api.get('/api/v1/notifications');

export const markNotificationRead = (id: number) =>
  api.post(`/api/v1/notifications/${id}/read`);

export const markAllNotificationsRead = () =>
  api.post('/api/v1/notifications/read-all');

// Admin
export const adminGetNotifications = () =>
  api.get('/api/v1/admin/notifications');

export const adminSendNotification = (payload: {
  user_id?: number;
  broadcast?: boolean;
  title: string;
  body?: string;
  type?: 'info' | 'success' | 'warning' | 'tip';
  action_url?: string;
}) => api.post('/api/v1/admin/notifications/send', payload);
