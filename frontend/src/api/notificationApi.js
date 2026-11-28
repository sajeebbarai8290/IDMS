import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const notificationApi = {
  getDashboard: () => axiosClient.get(ENDPOINTS.NOTIFICATIONS.DASHBOARD),
  getLookups: () => axiosClient.get(ENDPOINTS.NOTIFICATIONS.LOOKUPS),

  getInbox: (params = {}) => axiosClient.get(ENDPOINTS.NOTIFICATIONS.INBOX, { params }),
  markInboxRead: (id) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.INBOX_READ(id)),
  markAllInboxRead: () => axiosClient.post(ENDPOINTS.NOTIFICATIONS.INBOX_READ_ALL),

  getPreferences: (params = {}) => axiosClient.get(ENDPOINTS.NOTIFICATIONS.PREFERENCES, { params }),
  updatePreferences: (payload) => axiosClient.put(ENDPOINTS.NOTIFICATIONS.PREFERENCES, payload),

  getDevices: () => axiosClient.get(ENDPOINTS.NOTIFICATIONS.DEVICES),
  registerDevice: (payload) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.DEVICES, payload),
  deactivateDevice: (id) => axiosClient.delete(ENDPOINTS.NOTIFICATIONS.DEVICE_DETAIL(id)),

  getTemplates: (params = {}) => axiosClient.get(ENDPOINTS.NOTIFICATIONS.TEMPLATES, { params }),
  createTemplate: (payload) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.TEMPLATES, payload),
  updateTemplate: (id, payload) => axiosClient.put(ENDPOINTS.NOTIFICATIONS.TEMPLATE_DETAIL(id), payload),
  deleteTemplate: (id) => axiosClient.delete(ENDPOINTS.NOTIFICATIONS.TEMPLATE_DETAIL(id)),

  getAlertRules: (params = {}) => axiosClient.get(ENDPOINTS.NOTIFICATIONS.ALERT_RULES, { params }),
  createAlertRule: (payload) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.ALERT_RULES, payload),
  updateAlertRule: (id, payload) => axiosClient.put(ENDPOINTS.NOTIFICATIONS.ALERT_RULE_DETAIL(id), payload),
  deleteAlertRule: (id) => axiosClient.delete(ENDPOINTS.NOTIFICATIONS.ALERT_RULE_DETAIL(id)),

  getAlerts: (params = {}) => axiosClient.get(ENDPOINTS.NOTIFICATIONS.ALERTS, { params }),
  triggerAlert: (payload) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.ALERT_TRIGGER, payload),
  runEscalations: () => axiosClient.post(ENDPOINTS.NOTIFICATIONS.ALERT_ESCALATE),
  acknowledgeAlert: (id, payload = {}) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.ALERT_ACKNOWLEDGE(id), payload),
  resolveAlert: (id, payload = {}) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.ALERT_RESOLVE(id), payload),

  getCampaigns: (params = {}) => axiosClient.get(ENDPOINTS.NOTIFICATIONS.CAMPAIGNS, { params }),
  getCampaign: (id) => axiosClient.get(ENDPOINTS.NOTIFICATIONS.CAMPAIGN_DETAIL(id)),
  sendNotification: (payload) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.CAMPAIGNS, payload),
  getDispatches: (params = {}) => axiosClient.get(ENDPOINTS.NOTIFICATIONS.DISPATCHES, { params }),
  retryDispatch: (id) => axiosClient.post(ENDPOINTS.NOTIFICATIONS.DISPATCH_RETRY(id)),
  processScheduled: () => axiosClient.post(ENDPOINTS.NOTIFICATIONS.PROCESS_SCHEDULED),
};
