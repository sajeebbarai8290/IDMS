import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const disasterApi = {
  getDashboard: () => axiosClient.get(ENDPOINTS.DISASTER.DASHBOARD),
  getGeoJson: (params = {}) => axiosClient.get(ENDPOINTS.DISASTER.GEOJSON, { params }),
  getCategories: () => axiosClient.get(ENDPOINTS.DISASTER.CATEGORIES),
  createCategory: (data) => axiosClient.post(ENDPOINTS.DISASTER.CATEGORIES, data),
  getWarnings: (params = {}) => axiosClient.get(ENDPOINTS.DISASTER.WARNINGS, { params }),

  getEvents: (params = {}) => axiosClient.get(ENDPOINTS.DISASTER.EVENTS, { params }),
  getEvent: (id) => axiosClient.get(ENDPOINTS.DISASTER.EVENT_DETAIL(id)),
  createEvent: (data) => axiosClient.post(ENDPOINTS.DISASTER.EVENTS, data),
  updateEvent: (id, data) => axiosClient.put(ENDPOINTS.DISASTER.EVENT_DETAIL(id), data),
  deleteEvent: (id) => axiosClient.delete(ENDPOINTS.DISASTER.EVENT_DETAIL(id)),

  updateImpact: (id, data) => axiosClient.put(ENDPOINTS.DISASTER.IMPACT(id), data),
  uploadBoundary: (id, formData) =>
    axiosClient.post(ENDPOINTS.DISASTER.BOUNDARY(id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  transitionLifecycle: (id, data) => axiosClient.post(ENDPOINTS.DISASTER.LIFECYCLE(id), data),
  issueWarning: (id, data) => axiosClient.post(ENDPOINTS.DISASTER.EVENT_WARNINGS(id), data),

  addTimeline: (id, formData) =>
    axiosClient.post(ENDPOINTS.DISASTER.TIMELINES(id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  downloadAttachmentBlob: (eventId, timelineId) =>
    axiosClient.get(ENDPOINTS.DISASTER.TIMELINE_ATTACHMENT(eventId, timelineId), {
      responseType: 'blob',
    }),
};

export default disasterApi;
