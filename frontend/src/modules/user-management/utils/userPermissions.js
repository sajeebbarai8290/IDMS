export const USER_VIEW_ROLES = [
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
];

export const USER_ADMIN_ROLES = ['ROLE_SUPER_ADMIN'];

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

export const canViewUsers = (user) => hasAnyRole(user, USER_VIEW_ROLES) || hasPerm(user, 'user.account.view');
export const canCreateUsers = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'user.account.create');
export const canUpdateUsers = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'user.account.update');
export const canDeleteUsers = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'user.account.delete');
export const canChangeUserStatus = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'user.account.deactivate');
export const canAssignUserRoles = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'role.assignment.manage');
export const canAssignUserScopes = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'user.scope.assign');
export const canSyncKeycloak = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'user.keycloak.sync');
export const canViewRoles = (user) => Boolean(user);
export const canManageRoleConfig = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'role.configuration.manage');
export const canManageOrganizations = (user) => hasAnyRole(user, USER_ADMIN_ROLES) || hasPerm(user, 'organization.manage');
export const canViewUserActivity = (user) =>
  hasAnyRole(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NATIONAL_AUDITOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]) || hasPerm(user, 'user.activity.view');

export const ACCOUNT_STATUS_META = {
  PENDING: { bn: 'অপেক্ষমাণ', en: 'Pending', variant: 'warning' },
  ACTIVE: { bn: 'সক্রিয়', en: 'Active', variant: 'success' },
  SUSPENDED: { bn: 'সাময়িক স্থগিত', en: 'Suspended', variant: 'secondary' },
  DEACTIVATED: { bn: 'নিষ্ক্রিয়', en: 'Deactivated', variant: 'dark' },
  LOCKED: { bn: 'লককৃত', en: 'Locked', variant: 'danger' },
};

export const IDP_SYNC_META = {
  LINKED: { bn: 'কীক্লক সংযুক্ত', en: 'Linked', variant: 'success' },
  PENDING_SYNC: { bn: 'সিঙ্ক অপেক্ষমাণ', en: 'Pending Sync', variant: 'warning' },
  SYNC_FAILED: { bn: 'সিঙ্ক ব্যর্থ', en: 'Sync Failed', variant: 'danger' },
  LOCKED_BY_IDP: { bn: 'আইডিপি লককৃত', en: 'Locked in IdP', variant: 'dark' },
  NOT_LINKED: { bn: 'সংযুক্ত নয়', en: 'Not Linked', variant: 'secondary' },
};

export const SCOPE_ASSIGNMENT_META = {
  PRIMARY_POSTING: { bn: 'মূল পদায়ন', en: 'Primary Posting', variant: 'primary' },
  ADDITIONAL_CHARGE: { bn: 'অতিরিক্ত দায়িত্ব', en: 'Additional Charge', variant: 'info' },
  EMERGENCY_DEPUTATION: { bn: 'জরুরি প্রেষণ', en: 'Emergency Deputation', variant: 'danger' },
  INSPECTION_TAG: { bn: 'পরিদর্শন ট্যাগ দায়িত্ব', en: 'Inspection Tag', variant: 'warning' },
};

export const TIER_LEVEL_META = {
  NATIONAL: { bn: 'জাতীয় / মন্ত্রণালয়', en: 'National / Ministry' },
  DIRECTORATE: { bn: 'অধিদপ্তর (ডিডিএম)', en: 'Directorate (DDM)' },
  DIVISION: { bn: 'বিভাগ', en: 'Division' },
  DISTRICT: { bn: 'জেলা', en: 'District' },
  UPAZILA: { bn: 'উপজেলা', en: 'Upazila' },
  UNION: { bn: 'ইউনিয়ন / পৌরসভা', en: 'Union / Pourashava' },
  FIELD: { bn: 'মাঠ পর্যায় / অংশীদার', en: 'Field / Partner' },
};

export const ORG_TYPE_META = {
  MINISTRY: { bn: 'মন্ত্রণালয়', en: 'Ministry' },
  DIRECTORATE: { bn: 'অধিদপ্তর', en: 'Directorate' },
  DIVISION_ADMIN: { bn: 'বিভাগীয় প্রশাসন', en: 'Division Admin' },
  DISTRICT_ADMIN: { bn: 'জেলা প্রশাসন', en: 'District Admin' },
  UPAZILA_ADMIN: { bn: 'উপজেলা প্রশাসন', en: 'Upazila Admin' },
  UNION_PARISHAD: { bn: 'ইউনিয়ন পরিষদ', en: 'Union Parishad' },
  DEFENSE_RESCUE: { bn: 'সশস্ত্র ও উদ্ধার বাহিনী', en: 'Defense & Rescue' },
  HUMANITARIAN_PARTNER: { bn: 'মানবিক অংশীদার', en: 'Humanitarian Partner' },
  VOLUNTEER_AGENCY: { bn: 'স্বেচ্ছাসেবী সংস্থা', en: 'Volunteer Agency' },
  OTHER: { bn: 'অন্যান্য', en: 'Other' },
};

export const formatUserApiError = (err, fallback) => {
  if (!err) return fallback;
  if (err.details && typeof err.details === 'object') {
    const firstKey = Object.keys(err.details)[0];
    if (firstKey && Array.isArray(err.details[firstKey]) && err.details[firstKey][0]) {
      return err.details[firstKey][0];
    }
  }
  return err.message || fallback;
};
