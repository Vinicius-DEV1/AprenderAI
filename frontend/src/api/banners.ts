import api from './axios';

export const getActiveBanners = () =>
  api.get('/api/v1/banners/active');

export const trackBannerInteraction = (bannerId: number, type: 'view' | 'click' | 'close') =>
  api.post(`/api/v1/banners/${bannerId}/interact`, { type });

// Admin
export const adminGetBanners = () =>
  api.get('/api/v1/admin/banners');

export const adminCreateBanner = (data: FormData) =>
  api.post('/api/v1/admin/banners', data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

export const adminUpdateBanner = (id: number, data: FormData) =>
  api.post(`/api/v1/admin/banners/${id}`, data, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

export const adminDeleteBanner = (id: number) =>
  api.delete(`/api/v1/admin/banners/${id}`);

export const adminGetBannerStats = (id: number) =>
  api.get(`/api/v1/admin/banners/${id}/stats`);
