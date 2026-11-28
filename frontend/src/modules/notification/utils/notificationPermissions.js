const getUserRoles = (user) => {
  const roles = [...(user?.roles || [])];
  if (user?.role_code && !roles.includes(user.role_code)) {
    roles.push(user.role_code);
  }
  return roles;
};

const getUserPermissions = (user) => user?.permissions || [];

const hasAnyRole = (user, list) => getUserRoles(user).some((r) => list.includes(r));

const hasPerm = (user, perm) =>
  getUserRoles(user).includes('ROLE_SUPER_ADMIN') || getUserPermissions(user).includes(perm);

export const LOG_VIEW_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_NATIONAL_AUDITOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
];

export const TEMPLATE_MANAGE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
];

export const BROADCAST_SEND_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_UNO',
];

export const ALERT_RULE_MANAGE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
];

export const ALERT_ACK_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_WAREHOUSE_OFFICER',
  'ROLE_SHELTER_MANAGER',
  'ROLE_CPP_LEADER',
];

export const isSuperAdmin = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');

export const canViewNotificationLogs = (user) =>
  hasAnyRole(user, LOG_VIEW_ROLES) || hasPerm(user, 'notification.log.view');

export const canManageNotificationTemplates = (user) =>
  hasAnyRole(user, TEMPLATE_MANAGE_ROLES) || hasPerm(user, 'notification.template.manage');

export const canSendNotificationBroadcast = (user) =>
  !isSuperAdmin(user) &&
  (hasAnyRole(user, BROADCAST_SEND_ROLES) || getUserPermissions(user).includes('notification.broadcast.send'));

export const canManageAlertRules = (user) =>
  hasAnyRole(user, ALERT_RULE_MANAGE_ROLES) || hasPerm(user, 'notification.alert.manage');

export const canAcknowledgeAlerts = (user) =>
  !isSuperAdmin(user) &&
  (hasAnyRole(user, ALERT_ACK_ROLES) || getUserPermissions(user).includes('notification.alert.acknowledge'));

export const canTriggerAlerts = (user) =>
  !isSuperAdmin(user) && (canSendNotificationBroadcast(user) || canManageAlertRules(user));

export const canManagePolicyPreferences = (user) =>
  hasAnyRole(user, TEMPLATE_MANAGE_ROLES) || hasPerm(user, 'notification.preference.manage');

export const CATEGORY_META = {
  DISASTER_WARNING: { bn: 'দুর্যোগ আগাম সতর্কবার্তা', en: 'Disaster Warning', variant: 'danger' },
  EMERGENCY_ALERT: { bn: 'জরুরি সতর্কতা', en: 'Emergency Alert', variant: 'danger' },
  RELIEF_APPROVAL: { bn: 'ত্রাণ অনুমোদন হালনাগাদ', en: 'Relief Approval Update', variant: 'success' },
  BENEFICIARY_VERIFICATION: { bn: 'উপকারভোগী যাচাই', en: 'Beneficiary Verification', variant: 'info' },
  WORKFLOW_APPROVAL: { bn: 'কর্মপ্রবাহ অনুমোদন অনুরোধ', en: 'Workflow Approval Request', variant: 'warning' },
  INVENTORY_ALERT: { bn: 'গুদাম মজুদ সতর্কতা', en: 'Inventory Alert', variant: 'warning' },
  PROJECT_MONITORING: { bn: 'প্রকল্প পরিবীক্ষণ স্মারক', en: 'Project Monitoring Reminder', variant: 'primary' },
  SYSTEM_ANNOUNCEMENT: { bn: 'সিস্টেম ঘোষণা', en: 'System Announcement', variant: 'secondary' },
};

export const SEVERITY_META = {
  INFO: { bn: 'তথ্য', en: 'Info', variant: 'info' },
  WARNING: { bn: 'সতর্কতা', en: 'Warning', variant: 'warning' },
  HIGH: { bn: 'উচ্চ', en: 'High', variant: 'primary' },
  CRITICAL: { bn: 'অতি জরুরি', en: 'Critical', variant: 'danger' },
};

export const DELIVERY_STATUS_META = {
  QUEUED: { bn: 'সারিবদ্ধ', en: 'Queued', variant: 'secondary' },
  RETRYING: { bn: 'পুনঃচেষ্টা', en: 'Retrying', variant: 'warning' },
  SENT: { bn: 'প্রেরিত', en: 'Sent', variant: 'info' },
  DELIVERED: { bn: 'পৌঁছেছে', en: 'Delivered', variant: 'success' },
  FAILED: { bn: 'ব্যর্থ', en: 'Failed', variant: 'danger' },
  SKIPPED: { bn: 'নীতি/পছন্দে এড়ানো', en: 'Skipped', variant: 'dark' },
};

export const ALERT_STATUS_META = {
  ACTIVE: { bn: 'সক্রিয়', en: 'Active', variant: 'danger' },
  ACKNOWLEDGED: { bn: 'গৃহীত', en: 'Acknowledged', variant: 'info' },
  ESCALATED: { bn: 'এস্কেলেটেড', en: 'Escalated', variant: 'warning' },
  RESOLVED: { bn: 'সমাধানকৃত', en: 'Resolved', variant: 'success' },
};

export const CHANNEL_META = {
  IN_APP: { bn: 'ইন-অ্যাপ', en: 'In-App', icon: 'bi-app-indicator' },
  EMAIL: { bn: 'ইমেইল', en: 'Email', icon: 'bi-envelope-fill' },
  SMS: { bn: 'এসএমএস', en: 'SMS', icon: 'bi-chat-left-text-fill' },
  PUSH: { bn: 'মোবাইল পুশ', en: 'Mobile Push', icon: 'bi-phone-vibrate-fill' },
};

export const formatNotificationApiError = (error, fallback = 'Operation failed.') => {
  const res = error?.response?.data;
  if (res?.error?.details) {
    const firstKey = Object.keys(res.error.details)[0];
    if (firstKey && Array.isArray(res.error.details[firstKey])) {
      return res.error.details[firstKey][0];
    }
  }
  return res?.error?.message || res?.message || error?.message || fallback;
};
