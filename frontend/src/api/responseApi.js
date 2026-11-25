import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const responseApi = {
  getDashboard: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESPONSE.DASHBOARD, { params }),

  getLookups: () =>
    axiosClient.get(ENDPOINTS.RESPONSE.LOOKUPS),

  getGeoJson: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESPONSE.GEOJSON, { params }),

  createActivity: (payload) =>
    axiosClient.post(ENDPOINTS.RESPONSE.ACTIVITIES, payload),

  getOperations: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESPONSE.OPERATIONS, { params }),

  getOperation: (id) =>
    axiosClient.get(ENDPOINTS.RESPONSE.OPERATION_DETAIL(id)),

  createOperation: (payload) =>
    axiosClient.post(ENDPOINTS.RESPONSE.OPERATIONS, payload),

  updateOperation: (id, payload) =>
    axiosClient.put(ENDPOINTS.RESPONSE.OPERATION_DETAIL(id), payload),

  transitionLifecycle: (id, payload) =>
    axiosClient.post(ENDPOINTS.RESPONSE.OPERATION_LIFECYCLE(id), payload),

  addAgency: (opId, payload) =>
    axiosClient.post(ENDPOINTS.RESPONSE.OPERATION_AGENCIES(opId), payload),

  addTeam: (opId, payload) =>
    axiosClient.post(ENDPOINTS.RESPONSE.OPERATION_TEAMS(opId), payload),

  updateTeamStatus: (teamId, payload) =>
    axiosClient.put(ENDPOINTS.RESPONSE.TEAM_UPDATE(teamId), payload),

  addResource: (opId, payload) =>
    axiosClient.post(ENDPOINTS.RESPONSE.OPERATION_RESOURCES(opId), payload),

  updateResourceStatus: (allocId, payload) =>
    axiosClient.put(ENDPOINTS.RESPONSE.RESOURCE_UPDATE(allocId), payload),

  createTask: (opId, payload) =>
    axiosClient.post(ENDPOINTS.RESPONSE.OPERATION_TASKS(opId), payload),

  updateTaskProgress: (taskId, payload) =>
    axiosClient.put(ENDPOINTS.RESPONSE.TASK_UPDATE(taskId), payload),

  uploadTaskAttachment: (taskId, formData) =>
    axiosClient.post(ENDPOINTS.RESPONSE.TASK_ATTACHMENTS(taskId), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  downloadTaskAttachment: (taskId, attId) =>
    axiosClient.get(ENDPOINTS.RESPONSE.TASK_ATTACHMENT_DOWNLOAD(taskId, attId), {
      responseType: 'blob',
    }),

  getReports: (params = {}) =>
    axiosClient.get(ENDPOINTS.RESPONSE.REPORTS, { params }),

  createReport: (opId, payload) =>
    axiosClient.post(ENDPOINTS.RESPONSE.OPERATION_REPORTS(opId), payload),
};
