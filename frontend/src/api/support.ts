import api from './axios';

export const getTickets = () =>
  api.get('/api/v1/support/tickets');

export const openTicket = (data: FormData) =>
  api.post('/api/v1/support/tickets', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

export const getTicket = (id: number) =>
  api.get(`/api/v1/support/tickets/${id}`);

export const sendMessage = (ticketId: number, data: FormData) =>
  api.post(`/api/v1/support/tickets/${ticketId}/messages`, data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

// Admin
export const adminGetTickets = (status?: string) =>
  api.get('/api/v1/admin/support/tickets', { params: { status } });

export const adminGetTicket = (id: number) =>
  api.get(`/api/v1/admin/support/tickets/${id}`);

export const adminReply = (ticketId: number, message: string) =>
  api.post(`/api/v1/admin/support/tickets/${ticketId}/reply`, { message });

export const adminUpdateStatus = (ticketId: number, status: string) =>
  api.patch(`/api/v1/admin/support/tickets/${ticketId}/status`, { status });
