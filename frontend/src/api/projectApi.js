import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const projectApi = {
  getDashboard: (params = {}) =>
    axiosClient.get(ENDPOINTS.PROJECT.DASHBOARD, { params }),

  getLookups: () =>
    axiosClient.get(ENDPOINTS.PROJECT.LOOKUPS),

  getGeoJson: (params = {}) =>
    axiosClient.get(ENDPOINTS.PROJECT.GEOJSON, { params }),

  getReports: (params = {}) =>
    axiosClient.get(ENDPOINTS.PROJECT.REPORTS, { params }),

  exportCsv: (params = {}) =>
    axiosClient.get(ENDPOINTS.PROJECT.EXPORT, { params, responseType: 'blob' }),

  createCategory: (payload) =>
    axiosClient.post(ENDPOINTS.PROJECT.CATEGORIES, payload),

  getProjects: (params = {}) =>
    axiosClient.get(ENDPOINTS.PROJECT.SCHEMES, { params }),

  getProject: (id) =>
    axiosClient.get(ENDPOINTS.PROJECT.SCHEME_DETAIL(id)),

  createProject: (payload) =>
    axiosClient.post(ENDPOINTS.PROJECT.SCHEMES, payload),

  updateProject: (id, payload) =>
    axiosClient.put(ENDPOINTS.PROJECT.SCHEME_DETAIL(id), payload),

  deleteProject: (id) =>
    axiosClient.delete(ENDPOINTS.PROJECT.SCHEME_DETAIL(id)),

  transitionLifecycle: (id, payload) =>
    axiosClient.post(ENDPOINTS.PROJECT.SCHEME_LIFECYCLE(id), payload),

  uploadBoundary: (id, payload) =>
    axiosClient.post(ENDPOINTS.PROJECT.SCHEME_BOUNDARY(id), payload),

  createMilestone: (projectId, payload) =>
    axiosClient.post(ENDPOINTS.PROJECT.SCHEME_MILESTONES(projectId), payload),

  updateMilestoneProgress: (milestoneId, payload) =>
    axiosClient.put(ENDPOINTS.PROJECT.MILESTONE_PROGRESS(milestoneId), payload),

  certifyMilestone: (milestoneId, payload) =>
    axiosClient.post(ENDPOINTS.PROJECT.MILESTONE_CERTIFY(milestoneId), payload),

  recordFinancial: (projectId, formData) =>
    axiosClient.post(ENDPOINTS.PROJECT.SCHEME_FINANCIALS(projectId), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  approveFinancial: (disbursementId, payload) =>
    axiosClient.post(ENDPOINTS.PROJECT.FINANCIAL_APPROVE(disbursementId), payload),

  downloadFinancialDoc: (disbursementId) =>
    axiosClient.get(ENDPOINTS.PROJECT.FINANCIAL_DOCUMENT(disbursementId), {
      responseType: 'blob',
    }),

  createMonitoringReport: (projectId, formData) =>
    axiosClient.post(ENDPOINTS.PROJECT.SCHEME_MONITORING(projectId), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  downloadMonitoringAttachment: (reportId) =>
    axiosClient.get(ENDPOINTS.PROJECT.MONITORING_ATTACHMENT(reportId), {
      responseType: 'blob',
    }),

  createRisk: (projectId, payload) =>
    axiosClient.post(ENDPOINTS.PROJECT.SCHEME_RISKS(projectId), payload),

  updateRisk: (riskId, payload) =>
    axiosClient.put(ENDPOINTS.PROJECT.RISK_UPDATE(riskId), payload),
};

export default projectApi;
