import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const userManagementApi = {
  getDashboard: () => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.DASHBOARD),
  getLookups: () => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.LOOKUPS),
  getActivityLogs: (params = {}) => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.ACTIVITY_LOGS, { params }),
  getKeycloakHealth: () => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.KEYCLOAK_HEALTH),

  getUsers: (params = {}) => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.USERS, { params }),
  getUser: (id) => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.USER_DETAIL(id)),
  createUser: (payload) => axiosClient.post(ENDPOINTS.USER_MANAGEMENT.USERS, payload),
  updateUser: (id, payload) => axiosClient.put(ENDPOINTS.USER_MANAGEMENT.USER_DETAIL(id), payload),
  deleteUser: (id, reason) => axiosClient.delete(ENDPOINTS.USER_MANAGEMENT.USER_DETAIL(id), { data: { reason } }),
  changeUserStatus: (id, payload) => axiosClient.post(ENDPOINTS.USER_MANAGEMENT.USER_STATUS(id), payload),
  assignUserRoles: (id, payload) => axiosClient.post(ENDPOINTS.USER_MANAGEMENT.USER_ROLES(id), payload),
  assignUserScope: (id, payload) => axiosClient.post(ENDPOINTS.USER_MANAGEMENT.USER_SCOPES(id), payload),
  revokeUserScope: (userId, scopeId) => axiosClient.post(ENDPOINTS.USER_MANAGEMENT.USER_SCOPE_REVOKE(userId, scopeId)),
  syncKeycloak: (id, payload = {}) => axiosClient.post(ENDPOINTS.USER_MANAGEMENT.USER_SYNC_KEYCLOAK(id), payload),
  uploadPhoto: (id, formData) =>
    axiosClient.post(ENDPOINTS.USER_MANAGEMENT.USER_PHOTO(id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  downloadPhoto: (id) =>
    axiosClient.get(ENDPOINTS.USER_MANAGEMENT.USER_PHOTO(id), { responseType: 'blob' }),
  getUserActivity: (id, params = {}) => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.USER_ACTIVITY(id), { params }),

  getRoles: (params = {}) => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.ROLES, { params }),
  getRole: (id) => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.ROLE_DETAIL(id)),
  createRole: (payload) => axiosClient.post(ENDPOINTS.USER_MANAGEMENT.ROLES, payload),
  updateRole: (id, payload) => axiosClient.put(ENDPOINTS.USER_MANAGEMENT.ROLE_DETAIL(id), payload),
  deleteRole: (id) => axiosClient.delete(ENDPOINTS.USER_MANAGEMENT.ROLE_DETAIL(id)),
  syncRolePermissions: (id, permissions) =>
    axiosClient.post(ENDPOINTS.USER_MANAGEMENT.ROLE_PERMISSIONS(id), { permissions }),
  getPermissions: (params = {}) => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.PERMISSIONS, { params }),

  getOrganizations: (params = {}) => axiosClient.get(ENDPOINTS.USER_MANAGEMENT.ORGANIZATIONS, { params }),
  createOrganization: (payload) => axiosClient.post(ENDPOINTS.USER_MANAGEMENT.ORGANIZATIONS, payload),
  updateOrganization: (id, payload) => axiosClient.put(ENDPOINTS.USER_MANAGEMENT.ORGANIZATION_DETAIL(id), payload),
  deleteOrganization: (id) => axiosClient.delete(ENDPOINTS.USER_MANAGEMENT.ORGANIZATION_DETAIL(id)),
};

export default userManagementApi;
