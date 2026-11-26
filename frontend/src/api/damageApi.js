import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const damageApi = {
  getDashboard: (params = {}) =>
    axiosClient.get(ENDPOINTS.DAMAGE.DASHBOARD, { params }),

  getLookups: () =>
    axiosClient.get(ENDPOINTS.DAMAGE.LOOKUPS),

  getCategories: (params = {}) =>
    axiosClient.get(ENDPOINTS.DAMAGE.CATEGORIES, { params }),

  createCategory: (payload) =>
    axiosClient.post(ENDPOINTS.DAMAGE.CATEGORIES, payload),

  updateCategory: (id, payload) =>
    axiosClient.put(ENDPOINTS.DAMAGE.CATEGORY_DETAIL(id), payload),

  getGeoJson: (params = {}) =>
    axiosClient.get(ENDPOINTS.DAMAGE.GEOJSON, { params }),

  getHeatmap: (params = {}) =>
    axiosClient.get(ENDPOINTS.DAMAGE.HEATMAP, { params }),

  getSpatialAggregation: (params = {}) =>
    axiosClient.get(ENDPOINTS.DAMAGE.SPATIAL_AGGREGATION, { params }),

  getReports: (params = {}) =>
    axiosClient.get(ENDPOINTS.DAMAGE.REPORTS, { params }),

  exportCsv: (params = {}) =>
    axiosClient.get(ENDPOINTS.DAMAGE.EXPORT, { params, responseType: 'blob' }),

  getAssessments: (params = {}) =>
    axiosClient.get(ENDPOINTS.DAMAGE.ASSESSMENTS, { params }),

  getAssessment: (id) =>
    axiosClient.get(ENDPOINTS.DAMAGE.ASSESSMENT_DETAIL(id)),

  createAssessment: (payload) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENTS, payload),

  updateAssessment: (id, payload) =>
    axiosClient.put(ENDPOINTS.DAMAGE.ASSESSMENT_DETAIL(id), payload),

  deleteAssessment: (id) =>
    axiosClient.delete(ENDPOINTS.DAMAGE.ASSESSMENT_DETAIL(id)),

  syncAssessments: (payload) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_SYNC, payload),

  uploadBoundary: (id, payload) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_BOUNDARY(id), payload),

  uploadAttachment: (id, formData) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_ATTACHMENTS(id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  downloadAttachment: (assessmentId, attachmentId) =>
    axiosClient.get(ENDPOINTS.DAMAGE.ASSESSMENT_ATTACHMENT_DOWNLOAD(assessmentId, attachmentId), {
      responseType: 'blob',
    }),

  deleteAttachment: (assessmentId, attachmentId) =>
    axiosClient.delete(ENDPOINTS.DAMAGE.ASSESSMENT_ATTACHMENT_DOWNLOAD(assessmentId, attachmentId)),

  submitAssessment: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_SUBMIT(id), payload),

  startVerification: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_START_VERIFICATION(id), payload),

  requestCorrection: (id, payload) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_REQUEST_CORRECTION(id), payload),

  verifyAssessment: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_VERIFY(id), payload),

  rejectAssessment: (id, payload) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_REJECT(id), payload),

  approveAssessment: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.DAMAGE.ASSESSMENT_APPROVE(id), payload),
};

export default damageApi;
