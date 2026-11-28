import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const workflowApi = {
  getDashboard: (params = {}) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.DASHBOARD, { params }),

  getLookups: () =>
    axiosClient.get(ENDPOINTS.WORKFLOW.LOOKUPS),

  getTargetEntities: (params = {}) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.TARGET_ENTITIES, { params }),

  getNotifications: (params = {}) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.NOTIFICATIONS, { params }),

  markNotificationRead: (id) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.NOTIFICATION_READ(id)),

  markAllNotificationsRead: () =>
    axiosClient.post(ENDPOINTS.WORKFLOW.NOTIFICATIONS_READ_ALL),

  runEscalations: () =>
    axiosClient.post(ENDPOINTS.WORKFLOW.ESCALATIONS_RUN),

  getDefinitions: (params = {}) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.DEFINITIONS, { params }),

  getDefinition: (id) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.DEFINITION_DETAIL(id)),

  createDefinition: (payload) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.DEFINITIONS, payload),

  updateDefinition: (id, payload) =>
    axiosClient.put(ENDPOINTS.WORKFLOW.DEFINITION_DETAIL(id), payload),

  toggleDefinitionStatus: (id, isActive) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.DEFINITION_TOGGLE(id), { is_active: isActive }),

  deleteDefinition: (id) =>
    axiosClient.delete(ENDPOINTS.WORKFLOW.DEFINITION_DETAIL(id)),

  getDelegations: (params = {}) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.DELEGATIONS, { params }),

  createDelegation: (payload) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.DELEGATIONS, payload),

  revokeDelegation: (id) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.DELEGATION_REVOKE(id)),

  getEntityHistory: (entityType, entityId) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.ENTITY_HISTORY(entityType, entityId)),

  getInstances: (params = {}) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.INSTANCES, { params }),

  getInstance: (id) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.INSTANCE_DETAIL(id)),

  createInstance: (payload) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCES, payload),

  approveInstance: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_APPROVE(id), payload),

  rejectInstance: (id, payload) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_REJECT(id), payload),

  returnForCorrection: (id, payload) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_RETURN_CORRECTION(id), payload),

  requestInfo: (id, payload) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_REQUEST_INFO(id), payload),

  provideInfo: (id, payload) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_PROVIDE_INFO(id), payload),

  resubmitInstance: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_RESUBMIT(id), payload),

  forwardInstance: (id, payload) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_FORWARD(id), payload),

  cancelInstance: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_CANCEL(id), payload),

  sendReminder: (id) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_REMIND(id)),

  uploadAttachment: (id, formData) =>
    axiosClient.post(ENDPOINTS.WORKFLOW.INSTANCE_ATTACHMENTS(id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  downloadAttachment: (instanceId, attachmentId) =>
    axiosClient.get(ENDPOINTS.WORKFLOW.INSTANCE_ATTACHMENT_DOWNLOAD(instanceId, attachmentId), {
      responseType: 'blob',
    }),
};

export default workflowApi;
