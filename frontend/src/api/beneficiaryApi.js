import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const beneficiaryApi = {
  // Reference data
  getAdministrativeUnits: (params = {}) => axiosClient.get(ENDPOINTS.ADMIN_UNITS, { params }),
  getDisasters: () => axiosClient.get(ENDPOINTS.DISASTERS),

  // Households
  getHouseholds: (params = {}) => axiosClient.get(ENDPOINTS.BENEFICIARY.HOUSEHOLDS, { params }),
  getHousehold: (id, params = {}) => axiosClient.get(`${ENDPOINTS.BENEFICIARY.HOUSEHOLDS}/${id}`, { params }),
  createHousehold: (data) => axiosClient.post(ENDPOINTS.BENEFICIARY.HOUSEHOLDS, data),
  updateHousehold: (id, data) => axiosClient.put(`${ENDPOINTS.BENEFICIARY.HOUSEHOLDS}/${id}`, data),
  deleteHousehold: (id) => axiosClient.delete(`${ENDPOINTS.BENEFICIARY.HOUSEHOLDS}/${id}`),
  revealPii: (householdId) => axiosClient.post(ENDPOINTS.BENEFICIARY.REVEAL_PII(householdId)),

  // Members
  addMember: (householdId, data) => axiosClient.post(ENDPOINTS.BENEFICIARY.MEMBERS(householdId), data),
  removeMember: (householdId, memberId) => axiosClient.delete(ENDPOINTS.BENEFICIARY.MEMBER_DELETE(householdId, memberId)),

  // Verification
  verifyStep: (householdId, data) => axiosClient.post(ENDPOINTS.BENEFICIARY.VERIFY_STEP(householdId), data),
  finalApproval: (householdId, data) => axiosClient.post(ENDPOINTS.BENEFICIARY.APPROVAL(householdId), data),
  verifyNid: (data) => axiosClient.post(ENDPOINTS.BENEFICIARY.VERIFY_NID, data),

  // Duplicate Review
  getDuplicates: (params = {}) => axiosClient.get(ENDPOINTS.BENEFICIARY.DUPLICATES, { params }),
  resolveDuplicate: (id, data) => axiosClient.post(ENDPOINTS.BENEFICIARY.RESOLVE_DUPLICATE(id), data),

  // Disaster Eligibility
  linkDisaster: (householdId, data) => axiosClient.post(ENDPOINTS.BENEFICIARY.DISASTER_LINK(householdId), data),
  disburseAssistance: (householdId, eligibilityId) =>
    axiosClient.post(ENDPOINTS.BENEFICIARY.DISASTER_DISBURSE(householdId, eligibilityId)),

  // Dashboard Metrics
  getDashboardMetrics: () => axiosClient.get(ENDPOINTS.BENEFICIARY.DASHBOARD_METRICS),
  getGeoBreakdown: () => axiosClient.get(ENDPOINTS.BENEFICIARY.DASHBOARD_GEO),
  getDisasterBreakdown: () => axiosClient.get(ENDPOINTS.BENEFICIARY.DASHBOARD_DISASTERS),

  // Import / Export
  importBeneficiaries: (formData) =>
    axiosClient.post(ENDPOINTS.BENEFICIARY.IMPORT, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  importCsvData: (csvData) => axiosClient.post(ENDPOINTS.BENEFICIARY.IMPORT, { csv_data: csvData }),
  exportUrl: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return `${axiosClient.defaults.baseURL}${ENDPOINTS.BENEFICIARY.EXPORT}${query ? `?${query}` : ''}`;
  },
  downloadTemplateUrl: () => `${axiosClient.defaults.baseURL}${ENDPOINTS.BENEFICIARY.TEMPLATE}`,
};

export default beneficiaryApi;
