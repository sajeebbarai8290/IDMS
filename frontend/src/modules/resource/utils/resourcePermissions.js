export const RESOURCE_CATEGORY_MANAGE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
];

export const RESOURCE_ASSET_WRITE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_WAREHOUSE_MANAGER',
  'ROLE_WAREHOUSE_OFFICER',
];

export const RESOURCE_DEPLOY_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
];

export const RESOURCE_EXPORT_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_NATIONAL_AUDITOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_WAREHOUSE_MANAGER',
  'ROLE_WAREHOUSE_OFFICER',
  'ROLE_EXTERNAL_PARTNER',
];

const getUserRoles = (user) => {
  const roles = [...(user?.roles || [])];
  if (user?.role_code && !roles.includes(user.role_code)) {
    roles.push(user.role_code);
  }
  return roles;
};

const hasAny = (user, list) => getUserRoles(user).some((r) => list.includes(r));

export const isResourceDeployBlackout = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');
export const canManageResourceCategories = (user) => hasAny(user, RESOURCE_CATEGORY_MANAGE_ROLES);
export const canWriteResourceAssets = (user) => hasAny(user, RESOURCE_ASSET_WRITE_ROLES);
export const canDeleteResourceAssets = (user) =>
  !isResourceDeployBlackout(user) &&
  hasAny(user, ['ROLE_MINISTRY_EXECUTIVE', 'ROLE_NEOC_DIRECTOR', 'ROLE_DDM_DIRECTOR_GENERAL', 'ROLE_DDM_RELIEF_DIRECTOR', 'ROLE_DRRO', 'ROLE_PIO']);
export const canDeployResources = (user) => !isResourceDeployBlackout(user) && hasAny(user, RESOURCE_DEPLOY_ROLES);
export const canExportResources = (user) => hasAny(user, RESOURCE_EXPORT_ROLES);

export const getTodayIsoDate = () => new Date().toISOString().slice(0, 10);

export const RESOURCE_STATUS_META = {
  AVAILABLE: { bn: 'উপলব্ধ', en: 'Available', variant: 'success' },
  ASSIGNED: { bn: 'বরাদ্দকৃত', en: 'Assigned', variant: 'primary' },
  IN_USE: { bn: 'ব্যবহারে আছে', en: 'In Use', variant: 'info' },
  DEPLOYED: { bn: 'মোতায়েনকৃত', en: 'Deployed', variant: 'info' },
  UNDER_MAINTENANCE: { bn: 'রক্ষণাবেক্ষণে', en: 'Under Maintenance', variant: 'warning' },
  MAINTENANCE: { bn: 'রক্ষণাবেক্ষণে', en: 'Under Maintenance', variant: 'warning' },
  DAMAGED: { bn: 'ক্ষতিগ্রস্ত', en: 'Damaged', variant: 'danger' },
  RETIRED: { bn: 'অবসরপ্রাপ্ত', en: 'Retired', variant: 'dark' },
};

export const ALLOCATION_STATUS_META = {
  ASSIGNED: { bn: 'বরাদ্দকৃত', en: 'Assigned', variant: 'primary' },
  IN_USE: { bn: 'চলমান ব্যবহার', en: 'In Use', variant: 'info' },
  RETURNED: { bn: 'ফেরত প্রাপ্ত', en: 'Returned', variant: 'success' },
  CANCELLED: { bn: 'বাতিল', en: 'Cancelled', variant: 'secondary' },
  COMPLETED: { bn: 'সম্পন্ন', en: 'Completed', variant: 'success' },
};

export const MAINTENANCE_STATUS_META = {
  SCHEDULED: { bn: 'নির্ধারিত', en: 'Scheduled', variant: 'secondary' },
  IN_PROGRESS: { bn: 'চলমান', en: 'In Progress', variant: 'warning' },
  COMPLETED: { bn: 'সম্পন্ন', en: 'Completed', variant: 'success' },
  CANCELLED: { bn: 'বাতিল', en: 'Cancelled', variant: 'dark' },
};

export const formatResourceApiError = (err, fallback) => {
  if (err?.details && typeof err.details === 'object') {
    const msgs = Object.values(err.details).flat().filter(Boolean).join(' ');
    if (msgs) return msgs;
  }
  return err?.message || fallback;
};

export const formatBdt = (amount) => {
  const num = Number(amount || 0);
  return new Intl.NumberFormat('en-BD', { style: 'currency', currency: 'BDT', maximumFractionDigits: 0 }).format(num);
};

export const triggerResourceBlobDownload = (blob, filename) => {
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(url);
};
