const getUserRoles = (user) => {
  const roles = [...(user?.roles || [])];
  if (user?.role_code && !roles.includes(user.role_code)) {
    roles.push(user.role_code);
  }
  return roles;
};

const hasAny = (user, list) => getUserRoles(user).some((r) => list.includes(r));

export const isVolunteerBlackout = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');

export const isVolunteerExternalPartner = (user) =>
  getUserRoles(user).includes('ROLE_EXTERNAL_PARTNER') &&
  !hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_NATIONAL_AUDITOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]);

export const canManageVolunteerCategories = (user) =>
  hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DDM_RELIEF_DIRECTOR',
  ]);

export const canManageVolunteerSkills = (user) =>
  canManageVolunteerCategories(user) ||
  hasAny(user, ['ROLE_DRRO', 'ROLE_PIO', 'ROLE_CPP_LEADER', 'ROLE_CPP_TEAM_LEADER']);

export const canManageVolunteerOrganizations = (user) =>
  hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]);

export const canVerifyVolunteerOrganization = (user) =>
  !isVolunteerBlackout(user) &&
  hasAny(user, [
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]);

export const canWriteVolunteers = (user) =>
  !isVolunteerBlackout(user) &&
  hasAny(user, [
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_UP_CHAIRMAN',
    'ROLE_UP_SECRETARY',
    'ROLE_CPP_LEADER',
    'ROLE_CPP_TEAM_LEADER',
  ]);

export const canDeleteVolunteers = (user) =>
  !isVolunteerBlackout(user) && hasAny(user, ['ROLE_DRRO', 'ROLE_UNO', 'ROLE_PIO']);

export const canVerifyVolunteers = (user) =>
  !isVolunteerBlackout(user) &&
  hasAny(user, [
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DIVISIONAL_COMMISSIONER',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_TAG_OFFICER',
  ]);

export const canDeployVolunteers = (user) =>
  !isVolunteerBlackout(user) &&
  hasAny(user, [
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DIVISIONAL_COMMISSIONER',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_UP_CHAIRMAN',
    'ROLE_SHELTER_MANAGER',
    'ROLE_CPP_LEADER',
    'ROLE_CPP_TEAM_LEADER',
  ]);

export const canRecordVolunteerAttendance = (user) =>
  !isVolunteerBlackout(user) &&
  hasAny(user, [
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_UP_CHAIRMAN',
    'ROLE_UP_SECRETARY',
    'ROLE_SHELTER_MANAGER',
    'ROLE_CPP_LEADER',
    'ROLE_CPP_TEAM_LEADER',
    'ROLE_VOLUNTEER_RESPONDER',
    'ROLE_VOLUNTEER',
  ]);

export const canConfirmVolunteerAttendance = (user) =>
  !isVolunteerBlackout(user) &&
  hasAny(user, [
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DIVISIONAL_COMMISSIONER',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_UP_CHAIRMAN',
    'ROLE_SHELTER_MANAGER',
    'ROLE_CPP_LEADER',
    'ROLE_CPP_TEAM_LEADER',
  ]);

export const canManageTrainingPrograms = (user) =>
  hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]);

export const canManageTrainingSessions = (user) =>
  !isVolunteerBlackout(user) &&
  hasAny(user, [
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
    'ROLE_CPP_LEADER',
    'ROLE_CPP_TEAM_LEADER',
  ]);

export const canDecryptVolunteerPii = (user) =>
  !isVolunteerExternalPartner(user) &&
  hasAny(user, [
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_NATIONAL_AUDITOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
    'ROLE_DEPUTY_COMMISSIONER',
    'ROLE_DRRO',
    'ROLE_UNO',
    'ROLE_PIO',
  ]);

export const canExportVolunteerReports = (user) =>
  hasAny(user, [
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
    'ROLE_CPP_LEADER',
    'ROLE_CPP_TEAM_LEADER',
    'ROLE_EXTERNAL_PARTNER',
  ]);

export const formatVolunteerApiError = (err, fallback) => {
  const payload = err?.response?.data;
  if (payload?.errors) {
    return Object.values(payload.errors).flat().join(' ');
  }
  return payload?.message || payload?.error?.message || fallback;
};

export const triggerVolunteerCsvDownload = (blobData, filename) => {
  const url = window.URL.createObjectURL(new Blob([blobData], { type: 'text/csv;charset=utf-8;' }));
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.URL.revokeObjectURL(url);
};

export const VOLUNTEER_VERIFICATION_META = {
  DRAFT: { bn: 'খসড়া', en: 'Draft', variant: 'secondary' },
  PENDING_VERIFICATION: { bn: 'যাচাই অপেক্ষমাণ', en: 'Pending Verification', variant: 'warning' },
  VERIFIED: { bn: 'যাচাইকৃত', en: 'Verified', variant: 'success' },
  REJECTED: { bn: 'প্রত্যাখ্যাত', en: 'Rejected', variant: 'danger' },
  UNVERIFIED: { bn: 'অযাচাইকৃত', en: 'Unverified', variant: 'secondary' },
  PENDING: { bn: 'অপেক্ষমাণ', en: 'Pending', variant: 'warning' },
};

export const VOLUNTEER_AVAILABILITY_META = {
  AVAILABLE: { bn: 'উপলব্ধ', en: 'Available', variant: 'success' },
  STANDBY: { bn: 'স্ট্যান্ডবাই', en: 'Standby', variant: 'info' },
  DEPLOYED: { bn: 'মোতায়েনকৃত', en: 'Deployed', variant: 'primary' },
  ON_LEAVE: { bn: 'ছুটিতে', en: 'On Leave', variant: 'warning' },
  UNAVAILABLE: { bn: 'অনুপলব্ধ', en: 'Unavailable', variant: 'secondary' },
  INACTIVE: { bn: 'নিষ্ক্রিয়', en: 'Inactive', variant: 'dark' },
};

export const DEPLOYMENT_STATUS_META = {
  ASSIGNED: { bn: 'নির্ধারিত', en: 'Assigned', variant: 'info' },
  ACTIVE: { bn: 'সক্রিয়', en: 'Active', variant: 'primary' },
  COMPLETED: { bn: 'সম্পন্ন', en: 'Completed', variant: 'success' },
  CANCELLED: { bn: 'বাতিল', en: 'Cancelled', variant: 'secondary' },
};
