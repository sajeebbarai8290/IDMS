export const RELIEF_REQUISITION_CREATE_ROLES = [
  'ROLE_DRRO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_SHELTER_MANAGER',
];

export const RELIEF_REQUISITION_APPROVE_ROLES = [
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

export const RELIEF_ALLOCATION_CREATE_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
];

export const RELIEF_ALLOCATION_APPROVE_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
];

export const RELIEF_DISTRIBUTION_RECORD_ROLES = [
  'ROLE_DRRO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_FIELD_ENUMERATOR',
  'ROLE_SHELTER_MANAGER',
  'ROLE_VOLUNTEER',
];

export const RELIEF_DISTRIBUTION_VERIFY_ROLES = [
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_TAG_OFFICER',
];

export const RELIEF_DUPLICATE_OVERRIDE_ROLES = [
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
];

export const RELIEF_CATEGORY_MANAGE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
];

const getUserRoles = (user) => {
  const roles = [...(user?.roles || [])];
  if (user?.role_code && !roles.includes(user.role_code)) {
    roles.push(user.role_code);
  }
  return roles;
};

const hasAnyRole = (user, list) => getUserRoles(user).some((r) => list.includes(r));

export const isSuperAdminBlackout = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');

export const canCreateRequisition = (user) =>
  !isSuperAdminBlackout(user) && hasAnyRole(user, RELIEF_REQUISITION_CREATE_ROLES);

export const canApproveRequisition = (user) =>
  !isSuperAdminBlackout(user) && hasAnyRole(user, RELIEF_REQUISITION_APPROVE_ROLES);

export const canCreateAllocation = (user) =>
  !isSuperAdminBlackout(user) && hasAnyRole(user, RELIEF_ALLOCATION_CREATE_ROLES);

export const canApproveAllocation = (user) =>
  !isSuperAdminBlackout(user) && hasAnyRole(user, RELIEF_ALLOCATION_APPROVE_ROLES);

export const canDispatchAllocation = (user) =>
  !isSuperAdminBlackout(user) &&
  (hasAnyRole(user, RELIEF_ALLOCATION_CREATE_ROLES) || hasAnyRole(user, ['ROLE_WAREHOUSE_MANAGER']));

export const canRecordDistribution = (user) =>
  !isSuperAdminBlackout(user) && hasAnyRole(user, RELIEF_DISTRIBUTION_RECORD_ROLES);

export const canVerifyDistribution = (user) =>
  !isSuperAdminBlackout(user) && hasAnyRole(user, RELIEF_DISTRIBUTION_VERIFY_ROLES);

export const canOverrideDuplicate = (user) =>
  !isSuperAdminBlackout(user) && hasAnyRole(user, RELIEF_DUPLICATE_OVERRIDE_ROLES);

export const canManageReliefCategories = (user) =>
  hasAnyRole(user, RELIEF_CATEGORY_MANAGE_ROLES);

export const REQUISITION_STATUS_META = {
  DRAFT: { bn: 'খসড়া', en: 'Draft', variant: 'secondary' },
  SUBMITTED: { bn: 'জমা দেওয়া হয়েছে', en: 'Submitted', variant: 'primary' },
  UNDER_REVIEW: { bn: 'পর্যালোচনাধীন', en: 'Under Review', variant: 'info' },
  APPROVED: { bn: 'অনুমোদিত', en: 'Approved', variant: 'success' },
  PARTIALLY_APPROVED: { bn: 'আংশিক অনুমোদিত', en: 'Partially Approved', variant: 'warning' },
  REJECTED: { bn: 'প্রত্যাখ্যাত', en: 'Rejected', variant: 'danger' },
  COMPLETED: { bn: 'সম্পন্ন', en: 'Completed', variant: 'dark' },
};

export const ALLOCATION_STATUS_META = {
  DRAFT: { bn: 'খসড়া', en: 'Draft', variant: 'secondary' },
  SANCTIONED: { bn: 'মঞ্জুরিকৃত', en: 'Sanctioned', variant: 'info' },
  DISPATCHED: { bn: 'প্রেরিত', en: 'Dispatched', variant: 'primary' },
  PARTIALLY_DISTRIBUTED: { bn: 'আংশিক বিতরণকৃত', en: 'Partially Distributed', variant: 'warning' },
  COMPLETED: { bn: 'সম্পন্ন', en: 'Completed', variant: 'success' },
  CANCELLED: { bn: 'বাতিল', en: 'Cancelled', variant: 'danger' },
};

export const PRIORITY_META = {
  LOW: { bn: 'সাধারণ', en: 'Low', variant: 'secondary' },
  MEDIUM: { bn: 'মাঝারি', en: 'Medium', variant: 'info' },
  HIGH: { bn: 'উচ্চ', en: 'High', variant: 'warning' },
  CRITICAL: { bn: 'অতি জরুরি', en: 'Critical', variant: 'danger' },
};

export const formatReliefApiError = (err, fallbackMessage) => {
  if (err?.details && typeof err.details === 'object') {
    const detailed = Object.values(err.details).flat().filter(Boolean).join(' ');
    if (detailed) return detailed;
  }
  return err?.message || fallbackMessage;
};

export const downloadBlobFile = (blob, filename) => {
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(url);
};
