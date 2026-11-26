export const PROJECT_CATEGORY_MANAGE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
];

export const PROJECT_WRITE_ROLES = [
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DRRO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
];

export const PROJECT_APPROVE_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_UNO',
];

export const PROJECT_EXECUTE_ROLES = [
  ...PROJECT_APPROVE_ROLES,
  'ROLE_DRRO',
  'ROLE_PIO',
];

export const PROJECT_MILESTONE_UPDATE_ROLES = [
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DRRO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_FIELD_ENUMERATOR',
  'ROLE_TAG_OFFICER',
];

export const PROJECT_MILESTONE_CERTIFY_ROLES = [
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_TAG_OFFICER',
];

export const PROJECT_DISBURSEMENT_CREATE_ROLES = [
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DRRO',
  'ROLE_PIO',
];

export const PROJECT_DISBURSEMENT_APPROVE_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
];

export const PROJECT_MONITORING_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_TAG_OFFICER',
  'ROLE_FIELD_ENUMERATOR',
];

export const PROJECT_EXPORT_ROLES = [
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
  'ROLE_TAG_OFFICER',
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

export const isProjectBlackout = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');
export const isExternalPartner = (user) =>
  getUserRoles(user).includes('ROLE_EXTERNAL_PARTNER') &&
  !hasAny(user, ['ROLE_MINISTRY_EXECUTIVE', 'ROLE_NATIONAL_AUDITOR', 'ROLE_DDM_DIRECTOR_GENERAL']);

export const canManageProjectCategories = (user) => hasAny(user, PROJECT_CATEGORY_MANAGE_ROLES);
export const canCreateProject = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_WRITE_ROLES);
export const canUpdateProject = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_WRITE_ROLES);
export const canApproveProject = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_APPROVE_ROLES);
export const canExecuteProjectLifecycle = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_EXECUTE_ROLES);
export const canUpdateMilestone = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_MILESTONE_UPDATE_ROLES);
export const canCertifyMilestone = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_MILESTONE_CERTIFY_ROLES);
export const canCreateDisbursement = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_DISBURSEMENT_CREATE_ROLES);
export const canApproveDisbursement = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_DISBURSEMENT_APPROVE_ROLES);
export const canCreateMonitoring = (user) => !isProjectBlackout(user) && hasAny(user, PROJECT_MONITORING_ROLES);
export const canExportProjects = (user) => hasAny(user, PROJECT_EXPORT_ROLES);

export const PROJECT_STATUS_META = {
  PROPOSED: { bn: 'প্রস্তাবিত', en: 'Proposed', variant: 'secondary' },
  UNDER_REVIEW: { bn: 'পর্যালোচনাধীন', en: 'Under Review', variant: 'info' },
  APPROVED: { bn: 'অনুমোদিত', en: 'Approved', variant: 'primary' },
  ONGOING: { bn: 'চলমান', en: 'Ongoing', variant: 'success' },
  SUSPENDED: { bn: 'স্থগিত', en: 'Suspended', variant: 'warning' },
  COMPLETED: { bn: 'সম্পন্ন', en: 'Completed', variant: 'teal' },
  CLOSED: { bn: 'সমাপ্ত', en: 'Closed', variant: 'dark' },
};

export const MILESTONE_APPROVAL_META = {
  PENDING: { bn: 'অপেক্ষমাণ', en: 'Pending', variant: 'secondary' },
  SUBMITTED: { bn: 'দাখিলকৃত', en: 'Submitted', variant: 'info' },
  VERIFIED_ENGINEER: { bn: 'প্রকৌশলী যাচাইকৃত', en: 'Verified (Engr.)', variant: 'primary' },
  APPROVED_PIO: { bn: 'পিআইও অনুমোদিত', en: 'Approved (PIO)', variant: 'success' },
  REJECTED: { bn: 'প্রত্যাখ্যাত', en: 'Rejected', variant: 'danger' },
};

export const DISBURSEMENT_STATUS_META = {
  PENDING: { bn: 'অপেক্ষমাণ', en: 'Pending', variant: 'warning' },
  APPROVED: { bn: 'অনুমোদিত', en: 'Approved', variant: 'primary' },
  RELEASED: { bn: 'ছাড়কৃত', en: 'Released', variant: 'info' },
  RECONCILED: { bn: 'সমন্বিত', en: 'Reconciled', variant: 'success' },
  REJECTED: { bn: 'প্রত্যাখ্যাত', en: 'Rejected', variant: 'danger' },
};

export const RISK_SEVERITY_META = {
  LOW: { bn: 'নিম্ন', en: 'Low', variant: 'secondary' },
  MEDIUM: { bn: 'মধ্যম', en: 'Medium', variant: 'warning' },
  HIGH: { bn: 'উচ্চ', en: 'High', variant: 'orange' },
  CRITICAL: { bn: 'গুরুতর', en: 'Critical', variant: 'danger' },
};

export const formatBdt = (amount) => {
  const num = Number(amount || 0);
  return `৳ ${num.toLocaleString('en-BD', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
};

export const formatProjectApiError = (err, fallback) => {
  const data = err?.response?.data;
  if (data?.errors) {
    const firstKey = Object.keys(data.errors)[0];
    if (firstKey && Array.isArray(data.errors[firstKey])) {
      return data.errors[firstKey][0];
    }
  }
  return data?.error?.message || data?.message || fallback;
};

export const triggerBlobDownload = (blobData, filename) => {
  const url = window.URL.createObjectURL(new Blob([blobData]));
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', filename);
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
};
