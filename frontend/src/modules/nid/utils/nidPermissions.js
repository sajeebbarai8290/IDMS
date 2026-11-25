export const NID_VIEW_ROLES = [
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
  'ROLE_FIELD_ENUMERATOR',
  'ROLE_TAG_OFFICER',
];

export const NID_INITIATE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_DRRO',
  'ROLE_PIO',
  'ROLE_UP_SECRETARY',
  'ROLE_FIELD_ENUMERATOR',
];

export const NID_MANUAL_REVIEW_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
];

export const canViewNid = (user) => {
  const roles = user?.roles || [];
  return roles.some((r) => NID_VIEW_ROLES.includes(r));
};

export const canInitiateNid = (user) => {
  const roles = user?.roles || [];
  return roles.some((r) => NID_INITIATE_ROLES.includes(r));
};

export const hasManualReviewRole = (user) => {
  const roles = user?.roles || [];
  return roles.some((r) => NID_MANUAL_REVIEW_ROLES.includes(r));
};

export const isSelfRequestedNid = (user, verification) => {
  if (!verification || !user?.id) return false;
  return (
    verification.requested_by_keycloak_sub === user.id ||
    verification.requested_by_user_id === user.id
  );
};

export const canManualReviewNid = (user, verification = null) => {
  if (!hasManualReviewRole(user)) return false;
  if (isSelfRequestedNid(user, verification)) return false;
  return true;
};

export const isValidBangladeshNid = (value) => {
  const clean = (value || '').trim();
  return /^(?:\d{10}|\d{13}|\d{17})$/.test(clean);
};
