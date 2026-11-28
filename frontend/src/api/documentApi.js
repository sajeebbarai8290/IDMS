import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const documentApi = {
  getDashboard: () => axiosClient.get(ENDPOINTS.DOCUMENTS.DASHBOARD),
  getLookups: () => axiosClient.get(ENDPOINTS.DOCUMENTS.LOOKUPS),
  getTargetEntities: (params = {}) => axiosClient.get(ENDPOINTS.DOCUMENTS.TARGET_ENTITIES, { params }),

  getCategories: (params = {}) => axiosClient.get(ENDPOINTS.DOCUMENTS.CATEGORIES, { params }),
  createCategory: (payload) => axiosClient.post(ENDPOINTS.DOCUMENTS.CATEGORIES, payload),
  updateCategory: (id, payload) => axiosClient.put(ENDPOINTS.DOCUMENTS.CATEGORY_DETAIL(id), payload),
  deleteCategory: (id) => axiosClient.delete(ENDPOINTS.DOCUMENTS.CATEGORY_DETAIL(id)),

  getEntityDocuments: (entityType, entityId) =>
    axiosClient.get(ENDPOINTS.DOCUMENTS.ENTITY_DOCUMENTS(entityType, entityId)),

  getDocuments: (params = {}) => axiosClient.get(ENDPOINTS.DOCUMENTS.LIST, { params }),
  getDocument: (id) => axiosClient.get(ENDPOINTS.DOCUMENTS.DETAIL(id)),
  uploadDocument: (formData) =>
    axiosClient.post(ENDPOINTS.DOCUMENTS.LIST, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  updateDocument: (id, payload) => axiosClient.put(ENDPOINTS.DOCUMENTS.DETAIL(id), payload),
  deleteDocument: (id) => axiosClient.delete(ENDPOINTS.DOCUMENTS.DETAIL(id)),

  uploadVersion: (id, formData) =>
    axiosClient.post(ENDPOINTS.DOCUMENTS.VERSIONS(id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  restoreVersion: (id, versionId) =>
    axiosClient.post(ENDPOINTS.DOCUMENTS.VERSION_RESTORE(id, versionId)),
  downloadVersion: (id, versionId) =>
    axiosClient.get(ENDPOINTS.DOCUMENTS.VERSION_DOWNLOAD(id, versionId), {
      responseType: 'blob',
    }),

  downloadDocument: (id, params = {}) =>
    axiosClient.get(ENDPOINTS.DOCUMENTS.DOWNLOAD(id), {
      params,
      responseType: 'blob',
    }),
  previewDocument: (id, params = {}) =>
    axiosClient.get(ENDPOINTS.DOCUMENTS.PREVIEW(id), {
      params,
      responseType: 'blob',
    }),
  generateSignedUrl: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.DOCUMENTS.SIGNED_URL(id), payload),

  submitApproval: (id) => axiosClient.post(ENDPOINTS.DOCUMENTS.SUBMIT_APPROVAL(id)),
  approveDocument: (id, payload = {}) => axiosClient.post(ENDPOINTS.DOCUMENTS.APPROVE(id), payload),
  rejectDocument: (id, payload) => axiosClient.post(ENDPOINTS.DOCUMENTS.REJECT(id), payload),
  archiveDocument: (id, payload = {}) => axiosClient.post(ENDPOINTS.DOCUMENTS.ARCHIVE(id), payload),
  verifyIntegrity: (id, payload = {}) => axiosClient.post(ENDPOINTS.DOCUMENTS.VERIFY_INTEGRITY(id), payload),
  getHistory: (id) => axiosClient.get(ENDPOINTS.DOCUMENTS.HISTORY(id)),
};

export default documentApi;
