import apiClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const warehouseApi = {
  getDashboard: async () => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.DASHBOARD);
    return data;
  },

  getLookups: async () => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.LOOKUPS);
    return data;
  },

  getGeoJson: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.GEOJSON, { params });
    return data;
  },

  getAlerts: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.ALERTS, { params });
    return data;
  },

  getReports: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.REPORTS, { params });
    return data;
  },

  exportLedgerCsv: async (params = {}) => {
    return apiClient.get(ENDPOINTS.WAREHOUSE.EXPORT_LEDGER, {
      params,
      responseType: 'blob',
    });
  },

  // Facilities
  getFacilities: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.FACILITIES, { params });
    return data;
  },

  getFacility: async (id) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.FACILITY_DETAIL(id));
    return data;
  },

  createFacility: async (payload) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.FACILITIES, payload);
    return data;
  },

  updateFacility: async (id, payload) => {
    const { data } = await apiClient.put(ENDPOINTS.WAREHOUSE.FACILITY_DETAIL(id), payload);
    return data;
  },

  // Items / Commodities
  getItems: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.ITEMS, { params });
    return data;
  },

  createItem: async (payload) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.ITEMS, payload);
    return data;
  },

  updateItem: async (id, payload) => {
    const { data } = await apiClient.put(ENDPOINTS.WAREHOUSE.ITEM_DETAIL(id), payload);
    return data;
  },

  // Stock Balances & Ledger
  getInventories: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.INVENTORIES, { params });
    return data;
  },

  getTransactions: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.TRANSACTIONS, { params });
    return data;
  },

  recordAdjustment: async (payload) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.TRANSACTION_ADJUSTMENT, payload);
    return data;
  },

  // Procurement & Receiving (GRN)
  getReceipts: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.RECEIPTS, { params });
    return data;
  },

  createReceipt: async (formDataOrPayload) => {
    const config = formDataOrPayload instanceof FormData
      ? { headers: { 'Content-Type': 'multipart/form-data' } }
      : {};
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.RECEIPTS, formDataOrPayload, config);
    return data;
  },

  verifyReceipt: async (id, payload = {}) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.RECEIPT_VERIFY(id), payload);
    return data;
  },

  rejectReceipt: async (id, payload) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.RECEIPT_REJECT(id), payload);
    return data;
  },

  downloadReceiptAttachment: async (id) => {
    return apiClient.get(ENDPOINTS.WAREHOUSE.RECEIPT_ATTACHMENT(id), {
      responseType: 'blob',
    });
  },

  // Inter-Warehouse Transfers
  getTransfers: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.TRANSFERS, { params });
    return data;
  },

  createTransfer: async (payload) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.TRANSFERS, payload);
    return data;
  },

  approveTransfer: async (id) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.TRANSFER_APPROVE(id));
    return data;
  },

  dispatchTransfer: async (id, payload = {}) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.TRANSFER_DISPATCH(id), payload);
    return data;
  },

  receiveTransfer: async (id, payload = {}) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.TRANSFER_RECEIVE(id), payload);
    return data;
  },

  cancelTransfer: async (id, payload) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.TRANSFER_CANCEL(id), payload);
    return data;
  },

  // Relief Allocation Integration
  getReliefAllocations: async (params = {}) => {
    const { data } = await apiClient.get(ENDPOINTS.WAREHOUSE.RELIEF_ALLOCATIONS, { params });
    return data;
  },

  issueAgainstReliefAllocation: async (allocationId, payload) => {
    const { data } = await apiClient.post(ENDPOINTS.WAREHOUSE.RELIEF_ISSUE(allocationId), payload);
    return data;
  },
};
