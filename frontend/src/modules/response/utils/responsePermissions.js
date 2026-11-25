export const RESPONSE_ACTIVITY_MANAGE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
];

export const RESPONSE_OPERATION_CREATE_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
];

export const RESPONSE_LIFECYCLE_AUTHORITY_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
];

export const RESPONSE_TASK_MANAGE_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_FIELD_ENUMERATOR',
  'ROLE_SHELTER_MANAGER',
  'ROLE_CPP_TEAM_LEADER',
  'ROLE_VOLUNTEER',
];

export const RESPONSE_COORDINATION_MANAGE_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_WAREHOUSE_MANAGER',
  'ROLE_CPP_TEAM_LEADER',
];

export const RESPONSE_REPORT_CREATE_ROLES = [
  ...RESPONSE_TASK_MANAGE_ROLES,
  'ROLE_TAG_OFFICER',
];

const getUserRoles = (user) => {
  const roles = [...(user?.roles || [])];
  if (user?.role_code && !roles.includes(user.role_code)) {
    roles.push(user.role_code);
  }
  return roles;
};

const hasAny = (user, list) => getUserRoles(user).some((r) => list.includes(r));

export const isResponseBlackout = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');
export const canManageActivities = (user) => hasAny(user, RESPONSE_ACTIVITY_MANAGE_ROLES);
export const canCreateOperation = (user) => !isResponseBlackout(user) && hasAny(user, RESPONSE_OPERATION_CREATE_ROLES);
export const canManageLifecycle = (user) => !isResponseBlackout(user) && hasAny(user, RESPONSE_LIFECYCLE_AUTHORITY_ROLES);
export const canManageTasks = (user) => !isResponseBlackout(user) && hasAny(user, RESPONSE_TASK_MANAGE_ROLES);
export const canManageCoordination = (user) => !isResponseBlackout(user) && hasAny(user, RESPONSE_COORDINATION_MANAGE_ROLES);
export const canCreateReport = (user) => !isResponseBlackout(user) && hasAny(user, RESPONSE_REPORT_CREATE_ROLES);

export const OPERATION_STATUS_META = {
  PLANNED: { bn: 'পরিকল্পিত', en: 'Planned', variant: 'secondary' },
  ACTIVATED: { bn: 'সক্রিয়কৃত', en: 'Activated', variant: 'primary' },
  ONGOING: { bn: 'চলমান', en: 'Ongoing', variant: 'success' },
  SUSPENDED: { bn: 'স্থগিত', en: 'Suspended', variant: 'warning' },
  COMPLETED: { bn: 'সম্পন্ন', en: 'Completed', variant: 'info' },
  CLOSED: { bn: 'সমাপ্ত', en: 'Closed', variant: 'dark' },
};

export const TASK_STATUS_META = {
  PENDING: { bn: 'অপেক্ষমাণ', en: 'Pending', variant: 'secondary' },
  IN_PROGRESS: { bn: 'চলমান', en: 'In Progress', variant: 'primary' },
  BLOCKED: { bn: 'বাধাগ্রস্ত', en: 'Blocked', variant: 'danger' },
  COMPLETED: { bn: 'সম্পন্ন', en: 'Completed', variant: 'success' },
  CANCELLED: { bn: 'বাতিল', en: 'Cancelled', variant: 'dark' },
};

export const formatResponseApiError = (err, fallback) => {
  if (err?.details && typeof err.details === 'object') {
    const msgs = Object.values(err.details).flat().filter(Boolean).join(' ');
    if (msgs) return msgs;
  }
  return err?.message || fallback;
};

export const downloadResponseBlob = (blob, filename) => {
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  window.URL.revokeObjectURL(url);
};
