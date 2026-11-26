import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const resourceApi = {
  getDashboard: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESOURCE.DASHBOARD, { params }),

  getLookups: () =>
    axiosClient.get(ENDPOINTS.RESOURCE.LOOKUPS),

  getGeoJson: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESOURCE.GEOJSON, { params }),

  getReminders: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESOURCE.REMINDERS, { params }),

  exportCsv: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESOURCE.EXPORT, { params, responseType: 'blob' }),

  getCategories: () =>
    axiosClient.get(ENDPOINTS.RESOURCE.CATEGORIES),

  createCategory: (payload) =>
    axiosClient.post(ENDPOINTS.RESOURCE.CATEGORIES, payload),

  getAssets: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESOURCE.ASSETS, { params }),

  getAsset: (id) =>
    axiosClient.get(ENDPOINTS.RESOURCE.ASSET_DETAIL(id)),

  createAsset: (payload) =>
    axiosClient.post(ENDPOINTS.RESOURCE.ASSETS, payload),

  updateAsset: (id, payload) =>
    axiosClient.put(ENDPOINTS.RESOURCE.ASSET_DETAIL(id), payload),

  deleteAsset: (id) =>
    axiosClient.delete(ENDPOINTS.RESOURCE.ASSET_DETAIL(id)),

  uploadAttachment: (assetId, formData) =>
    axiosClient.post(ENDPOINTS.RESOURCE.ASSET_ATTACHMENTS(assetId), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  downloadAttachment: (assetId, attId) =>
    axiosClient.get(ENDPOINTS.RESOURCE.ASSET_ATTACHMENT_DOWNLOAD(assetId, attId), {
      responseType: 'blob',
    }),

  getAllocations: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESOURCE.ALLOCATIONS, { params }),

  createAllocation: (payload) =>
    axiosClient.post(ENDPOINTS.RESOURCE.ALLOCATIONS, payload),

  markAllocationInUse: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.RESOURCE.ALLOCATION_IN_USE(id), payload),

  releaseAllocation: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.RESOURCE.ALLOCATION_RELEASE(id), payload),

  getMaintenances: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESOURCE.MAINTENANCES, { params }),

  createMaintenance: (assetId, formData) =>
    axiosClient.post(ENDPOINTS.RESOURCE.ASSET_MAINTENANCES(assetId), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  updateMaintenance: (id, payload) => {
    const isFormData = payload instanceof FormData;
    return axiosClient.put(
      ENDPOINTS.RESOURCE.MAINTENANCE_UPDATE(id),
      payload,
      isFormData ? { headers: { 'Content-Type': 'multipart/form-data' } } : undefined
    );
  },

  downloadMaintenanceAttachment: (id) =>
    axiosClient.get(ENDPOINTS.RESOURCE.MAINTENANCE_ATTACHMENT(id), {
      responseType: 'blob',
    }),

  getHumanDeployments: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESOURCE.HUMAN_DEPLOYMENTS, { params }),

  createHumanDeployment: (payload) =>
    axiosClient.post(ENDPOINTS.RESOURCE.HUMAN_DEPLOYMENTS, payload),

  releaseHumanDeployment: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.RESOURCE.HUMAN_DEPLOYMENT_RELEASE(id), payload),
};

export default resourceApi;
