import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const reliefApi = {
  getDashboard: (params = {}) =>
    axiosClient.get(ENDPOINTS.RELIEF.DASHBOARD, { params }),

  getLookups: () =>
    axiosClient.get(ENDPOINTS.RELIEF.LOOKUPS),

  getEligibleHouseholds: (params = {}) =>
    axiosClient.get(ENDPOINTS.RELIEF.ELIGIBLE_HOUSEHOLDS, { params }),

  getCategories: (params = {}) =>
    axiosClient.get(ENDPOINTS.RELIEF.CATEGORIES, { params }),

  createCategory: (payload) =>
    axiosClient.post(ENDPOINTS.RELIEF.CATEGORIES, payload),

  updateCategory: (id, payload) =>
    axiosClient.put(ENDPOINTS.RELIEF.CATEGORY_DETAIL(id), payload),

  // Requisitions
  getRequisitions: (params = {}) =>
    axiosClient.get(ENDPOINTS.RELIEF.REQUISITIONS, { params }),

  getRequisition: (id) =>
    axiosClient.get(ENDPOINTS.RELIEF.REQUISITION_DETAIL(id)),

  createRequisition: (formData) =>
    axiosClient.post(ENDPOINTS.RELIEF.REQUISITIONS, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  submitRequisition: (id) =>
    axiosClient.post(ENDPOINTS.RELIEF.REQUISITION_SUBMIT(id)),

  reviewRequisition: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.RELIEF.REQUISITION_REVIEW(id), payload),

  approveRequisition: (id, payload = {}) =>
    axiosClient.post(ENDPOINTS.RELIEF.REQUISITION_APPROVE(id), payload),

  rejectRequisition: (id, payload) =>
    axiosClient.post(ENDPOINTS.RELIEF.REQUISITION_REJECT(id), payload),

  downloadSupportingDoc: (id) =>
    axiosClient.get(ENDPOINTS.RELIEF.REQUISITION_DOCUMENT(id), { responseType: 'blob' }),

  // Allocations
  getAllocations: (params = {}) =>
    axiosClient.get(ENDPOINTS.RELIEF.ALLOCATIONS, { params }),

  getAllocation: (id) =>
    axiosClient.get(ENDPOINTS.RELIEF.ALLOCATION_DETAIL(id)),

  createAllocation: (payload) =>
    axiosClient.post(ENDPOINTS.RELIEF.ALLOCATIONS, payload),

  assignBeneficiaries: (id, householdIds) =>
    axiosClient.post(ENDPOINTS.RELIEF.ALLOCATION_BENEFICIARIES(id), { household_ids: householdIds }),

  approveAllocation: (id) =>
    axiosClient.post(ENDPOINTS.RELIEF.ALLOCATION_APPROVE(id)),

  dispatchAllocation: (id) =>
    axiosClient.post(ENDPOINTS.RELIEF.ALLOCATION_DISPATCH(id)),

  cancelAllocation: (id, reason) =>
    axiosClient.post(ENDPOINTS.RELIEF.ALLOCATION_CANCEL(id), { reason }),

  // Distributions
  getDistributions: (params = {}) =>
    axiosClient.get(ENDPOINTS.RELIEF.DISTRIBUTIONS, { params }),

  checkDuplicate: (params) =>
    axiosClient.get(ENDPOINTS.RELIEF.CHECK_DUPLICATE, { params }),

  recordDistribution: (formData) =>
    axiosClient.post(ENDPOINTS.RELIEF.DISTRIBUTIONS, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  verifyVoucher: (id) =>
    axiosClient.post(ENDPOINTS.RELIEF.VOUCHER_VERIFY(id)),

  voidVoucher: (id, voidReason) =>
    axiosClient.post(ENDPOINTS.RELIEF.VOUCHER_VOID(id), { void_reason: voidReason }),

  downloadVoucherPhoto: (id) =>
    axiosClient.get(ENDPOINTS.RELIEF.VOUCHER_PHOTO(id), { responseType: 'blob' }),

  exportMusterCsv: (params = {}) =>
    axiosClient.get(ENDPOINTS.RELIEF.EXPORT_MUSTER, { params, responseType: 'blob' }),
};
