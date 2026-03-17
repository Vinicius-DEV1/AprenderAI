import api from './axios';

export const adminGetNotifications = () =>
    api.get('/api/v1/admin/notifications');

export const adminSendNotification = (data: {
    user_id?: number | null;
    broadcast?: boolean;
    target_group?: 'all' | 'basic' | 'plus' | 'admin';
    title: string;
    body?: string;
    type?: 'info' | 'success' | 'warning' | 'tip';
    action_url?: string;
}) => api.post('/api/v1/admin/notifications/send', data);
