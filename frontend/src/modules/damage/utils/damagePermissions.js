export const DAMAGE_WRITE_ROLES = [
  'ROLE_DRRO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_FIELD_ENUMERATOR',
  'ROLE_SHELTER_MANAGER',
  'ROLE_CPP_LEADER',
];

export const DAMAGE_DELETE_ROLES = [
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_FIELD_ENUMERATOR',
];

export const DAMAGE_SUBMIT_ROLES = [
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_FIELD_ENUMERATOR',
  'ROLE_SHELTER_MANAGER',
  'ROLE_CPP_LEADER',
];

export const DAMAGE_VERIFY_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_TAG_OFFICER',
];

export const DAMAGE_APPROVE_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
];

export const DAMAGE_CATEGORY_MANAGE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DDM_PLANNING_OFFICER',
];

export const DAMAGE_EXPORT_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_NATIONAL_AUDITOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DDM_RELIEF_DIRECTOR',
  'ROLE_DDM_PLANNING_DIRECTOR',
  'ROLE_DDM_PLANNING_OFFICER',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
  'ROLE_UP_CHAIRMAN',
  'ROLE_UP_SECRETARY',
  'ROLE_FIELD_ENUMERATOR',
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

export const isDamageBlackout = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');

export const isExternalPartner = (user) =>
  getUserRoles(user).includes('ROLE_EXTERNAL_PARTNER') &&
  !hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_NATIONAL_AUDITOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DDM_PLANNING_DIRECTOR',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]);

export const canCreateDamage = (user) => !isDamageBlackout(user) && hasAny(user, DAMAGE_WRITE_ROLES);
export const canUpdateDamage = (user) => !isDamageBlackout(user) && hasAny(user, DAMAGE_WRITE_ROLES);
export const canDeleteDamage = (user) => !isDamageBlackout(user) && hasAny(user, DAMAGE_DELETE_ROLES);
export const canSubmitDamage = (user) => !isDamageBlackout(user) && hasAny(user, DAMAGE_SUBMIT_ROLES);
export const canVerifyDamage = (user) => !isDamageBlackout(user) && hasAny(user, DAMAGE_VERIFY_ROLES);
export const canApproveDamage = (user) => !isDamageBlackout(user) && hasAny(user, DAMAGE_APPROVE_ROLES);
export const canManageDamageCategories = (user) => hasAny(user, DAMAGE_CATEGORY_MANAGE_ROLES);
export const canExportDamage = (user) => hasAny(user, DAMAGE_EXPORT_ROLES);

export const DAMAGE_STATUS_META = {
  DRAFT: { bn: 'খসড়া', en: 'Draft', variant: 'secondary' },
  SUBMITTED: { bn: 'দাখিলকৃত', en: 'Submitted', variant: 'info' },
  UNDER_VERIFICATION: { bn: 'যাচাইাধীন', en: 'Under Verification', variant: 'primary' },
  CORRECTION_REQUESTED: { bn: 'সংশোধন প্রয়োজন', en: 'Correction Requested', variant: 'warning' },
  VERIFIED: { bn: 'যাচাইকৃত', en: 'Verified', variant: 'success' },
  REJECTED: { bn: 'প্রত্যাখ্যাত', en: 'Rejected', variant: 'danger' },
  APPROVED: { bn: 'অনুমোদিত', en: 'Approved', variant: 'dark' },
};

export const DAMAGE_TYPE_META = {
  INITIAL_RAPID: { bn: 'প্রাথমিক দ্রুত নিরূপণ', en: 'Initial Rapid' },
  PRELIMINARY_D_FORM: { bn: 'প্রাথমিক ডি-ফর্ম', en: 'Preliminary D-Form' },
  DETAILED_SECTORAL: { bn: 'বিস্তারিত খাতভিত্তিক', en: 'Detailed Sectoral' },
  FINAL_CONSOLIDATED: { bn: 'চূড়ান্ত সমন্বিত ডি-ফর্ম', en: 'Final Consolidated' },
};

export const DAMAGE_SEVERITY_META = {
  LOW: { bn: 'নিম্ন', en: 'Low', variant: 'success', color: '#198754' },
  MODERATE: { bn: 'মাঝারি', en: 'Moderate', variant: 'warning', color: '#fd7e14' },
  SEVERE: { bn: 'গুরুতর', en: 'Severe', variant: 'danger', color: '#dc3545' },
  CRITICAL: { bn: 'অতি গুরুতর', en: 'Critical', variant: 'dark', color: '#b02a37' },
};

export const formatBdt = (value) => {
  const num = Number(value || 0);
  return `৳ ${num.toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

export const formatDamageApiError = (err, fallbackMessage) => {
  const data = err?.response?.data;
  if (data?.errors && typeof data.errors === 'object') {
    const firstKey = Object.keys(data.errors)[0];
    const firstVal = data.errors[firstKey];
    if (Array.isArray(firstVal) && firstVal[0]) {
      return firstVal[0];
    }
  }
  return data?.message || err?.message || fallbackMessage;
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
