import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const nidApi = {
  getDashboard: () => axiosClient.get(ENDPOINTS.NID.DASHBOARD),
  getProviderHealth: () => axiosClient.get(ENDPOINTS.NID.PROVIDER_HEALTH),
  getVerifications: (params = {}) => axiosClient.get(ENDPOINTS.NID.VERIFICATIONS, { params }),
  getVerification: (id) => axiosClient.get(ENDPOINTS.NID.VERIFICATION_DETAIL(id)),
  verifyNid: (data) => axiosClient.post(ENDPOINTS.NID.VERIFICATIONS, data),
  retryVerification: (id, data) => axiosClient.post(ENDPOINTS.NID.RETRY(id), data),
  manualReview: (id, data) => axiosClient.post(ENDPOINTS.NID.MANUAL_REVIEW(id), data),
};

export default nidApi;
