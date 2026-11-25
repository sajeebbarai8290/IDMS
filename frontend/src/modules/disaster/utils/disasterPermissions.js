export const DISASTER_CREATE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
  'ROLE_UNO',
  'ROLE_PIO',
];

export const DISASTER_UPDATE_ROLES = [
  ...DISASTER_CREATE_ROLES,
  'ROLE_DDM_RELIEF_DIRECTOR',
];

export const DISASTER_VERIFY_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
  'ROLE_DIVISIONAL_COMMISSIONER',
  'ROLE_DEPUTY_COMMISSIONER',
  'ROLE_DRRO',
];

export const DISASTER_WARNING_ROLES = DISASTER_VERIFY_ROLES;

export const DISASTER_CATEGORY_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
];

export const LIFECYCLE_PHASES = [
  { value: 'REPORTED', bn: 'প্রতিবেদিত', en: 'Reported' },
  { value: 'UNDER_ASSESSMENT', bn: 'মূল্যায়নাধীন', en: 'Under Assessment' },
  { value: 'VERIFIED', bn: 'যাচাইকৃত', en: 'Verified' },
  { value: 'ACTIVE', bn: 'সক্রিয়', en: 'Active' },
  { value: 'RESPONSE_ONGOING', bn: 'সাড়াদান চলমান', en: 'Response Ongoing' },
  { value: 'RECOVERY', bn: 'পুনরুদ্ধার', en: 'Recovery' },
  { value: 'CLOSED', bn: 'সমাপ্ত', en: 'Closed' },
];

export const SEVERITY_LEVELS = [
  { value: 'ADVISORY', bn: 'পরামর্শমূলক', en: 'Advisory', color: '#0dcaf0' },
  { value: 'WARNING', bn: 'সতর্কতা', en: 'Warning', color: '#ffc107' },
  { value: 'DANGER', bn: 'বিপদ', en: 'Danger', color: '#fd7e14' },
  { value: 'GREAT_DANGER', bn: 'মহাবিপদ', en: 'Great Danger', color: '#dc3545' },
  { value: 'CATASTROPHIC', bn: 'ভয়াবহ বিপর্যয়', en: 'Catastrophic', color: '#6f1d1b' },
];

const hasAny = (user, list) => (user?.roles || []).some((r) => list.includes(r));

export const canCreateDisaster = (user) => hasAny(user, DISASTER_CREATE_ROLES);
export const canUpdateDisaster = (user) => hasAny(user, DISASTER_UPDATE_ROLES);
export const canVerifyDisaster = (user) => hasAny(user, DISASTER_VERIFY_ROLES);
export const canBroadcastWarning = (user) => hasAny(user, DISASTER_WARNING_ROLES);
export const canManageCategories = (user) => hasAny(user, DISASTER_CATEGORY_ROLES);
export const isNationalUser = (user) =>
  hasAny(user, [
    'ROLE_SUPER_ADMIN',
    'ROLE_MINISTRY_EXECUTIVE',
    'ROLE_NEOC_DIRECTOR',
    'ROLE_NATIONAL_AUDITOR',
    'ROLE_DDM_DIRECTOR_GENERAL',
  ]);
