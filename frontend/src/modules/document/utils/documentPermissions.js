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

export const DOC_VIEW_ROLES = [
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
  'ROLE_DATA_ENTRY_OPERATOR',
  'ROLE_TAG_OFFICER',
  'ROLE_WAREHOUSE_OFFICER',
  'ROLE_SHELTER_MANAGER',
  'ROLE_CPP_LEADER',
  'ROLE_EXTERNAL_PARTNER',
];

export const DOC_UPLOAD_ROLES = [
  'ROLE_SUPER_ADMIN',
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
  'ROLE_FIELD_ENUMERATOR',
  'ROLE_DATA_ENTRY_OPERATOR',
  'ROLE_TAG_OFFICER',
  'ROLE_WAREHOUSE_OFFICER',
  'ROLE_SHELTER_MANAGER',
  'ROLE_CPP_LEADER',
  'ROLE_VOLUNTEER_RESPONDER',
];

export const DOC_APPROVE_ROLES = [
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
];

export const DOC_DELETE_ROLES = ['ROLE_SUPER_ADMIN', 'ROLE_DRRO', 'ROLE_PIO'];

export const DOC_VERIFY_ROLES = [
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
  'ROLE_TAG_OFFICER',
  'ROLE_WAREHOUSE_OFFICER',
];

export const DOC_CATEGORY_MANAGE_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
];

export const isSuperAdmin = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');

export const canViewDocuments = (user) =>
  hasAnyRole(user, DOC_VIEW_ROLES) || hasPerm(user, 'document.file.view');

export const canUploadDocuments = (user) =>
  !hasAnyRole(user, ['ROLE_EXTERNAL_PARTNER', 'ROLE_NATIONAL_AUDITOR']) &&
  (hasAnyRole(user, DOC_UPLOAD_ROLES) || hasPerm(user, 'document.file.upload'));

export const canApproveDocuments = (user) =>
  !isSuperAdmin(user) &&
  (hasAnyRole(user, DOC_APPROVE_ROLES) || getUserPermissions(user).includes('document.file.approve'));

export const canDeleteDocuments = (user) =>
  hasAnyRole(user, DOC_DELETE_ROLES) || hasPerm(user, 'document.file.delete');

export const canVerifyDocumentIntegrity = (user) =>
  hasAnyRole(user, DOC_VERIFY_ROLES) || hasPerm(user, 'document.file.verify_integrity');

export const canManageDocumentCategories = (user) =>
  hasAnyRole(user, DOC_CATEGORY_MANAGE_ROLES) || hasPerm(user, 'document.category.manage');

export const APPROVAL_STATUS_META = {
  DRAFT: { bn: 'খসড়া', en: 'Draft', variant: 'secondary' },
  PENDING_APPROVAL: { bn: 'অনুমোদন অপেক্ষমাণ', en: 'Pending Approval', variant: 'warning' },
  APPROVED: { bn: 'অনুমোদিত', en: 'Approved', variant: 'success' },
  REJECTED: { bn: 'প্রত্যাখ্যাত', en: 'Rejected', variant: 'danger' },
  ARCHIVED: { bn: 'আর্কাইভকৃত', en: 'Archived', variant: 'dark' },
};

export const CLASSIFICATION_META = {
  PUBLIC: { bn: 'উন্মুক্ত (Public)', en: 'Public', variant: 'info' },
  INTERNAL: { bn: 'দাপ্তরিক (Internal)', en: 'Internal', variant: 'primary' },
  CONFIDENTIAL: { bn: 'গোপনীয় (Confidential)', en: 'Confidential', variant: 'warning' },
  RESTRICTED: { bn: 'অতি গোপনীয় (Restricted)', en: 'Restricted', variant: 'danger' },
};

export const SCAN_STATUS_META = {
  PENDING: { bn: 'স্ক্যান অপেক্ষমাণ', en: 'Pending Scan', variant: 'secondary' },
  CLEAN: { bn: 'নিরাপদ (Clean)', en: 'Clean', variant: 'success' },
  INFECTED: { bn: 'ঝুঁকিপূর্ণ (Infected)', en: 'Infected', variant: 'danger' },
  FAILED: { bn: 'স্ক্যান ব্যর্থ', en: 'Scan Failed', variant: 'warning' },
};

export const DOCUMENT_TYPE_LABELS = {
  GOVERNMENT_ORDER: { bn: 'সরকারি আদেশ (GO)', en: 'Government Order' },
  CIRCULAR: { bn: 'পরিপত্র / প্রজ্ঞাপন', en: 'Circular / Gazette' },
  SITREP: { bn: 'পরিস্থিতি প্রতিবেদন', en: 'Situation Report' },
  SURVEY_REPORT: { bn: 'জরিপ ও নিরূপণ প্রতিবেদন', en: 'Survey Report' },
  APPROVAL_LETTER: { bn: 'অনুমোদনপত্র', en: 'Approval Letter' },
  MUSTER_ROLL: { bn: 'মাস্টার রোল', en: 'Muster Roll' },
  VOUCHER: { bn: 'ভাউচার / রশিদ', en: 'Voucher / Receipt' },
  AGREEMENT: { bn: 'চুক্তিপত্র / সমঝোতা', en: 'Agreement / MoU' },
  BLUEPRINT: { bn: 'নকশা / প্রাক্কলন', en: 'Blueprint / Estimate' },
  PHOTOGRAPH: { bn: 'মাঠ পর্যায়ের ছবি', en: 'Photograph' },
  CERTIFICATE: { bn: 'প্রত্যয়নপত্র', en: 'Certificate' },
  OTHER: { bn: 'অন্যান্য', en: 'Other' },
};

export const ENTITY_TYPE_LABELS = {
  GENERAL: { bn: 'স্বতন্ত্র নথি (General)', en: 'Standalone / General' },
  HOUSEHOLD: { bn: 'উপকারভোগী পরিবার (M01)', en: 'Beneficiary Household (M01)' },
  DISASTER_EVENT: { bn: 'দুর্যোগ ঘটনা (M03)', en: 'Disaster Event (M03)' },
  RELIEF_REQUISITION: { bn: 'ত্রাণ চাহিদাপত্র (M04)', en: 'Relief Requisition (M04)' },
  RELIEF_ALLOCATION: { bn: 'ত্রাণ বরাদ্দ আদেশ (M04)', en: 'Relief Allocation (M04)' },
  DISTRIBUTION_VOUCHER: { bn: 'ত্রাণ বিতরণ ভাউচার (M04)', en: 'Distribution Voucher (M04)' },
  PROJECT: { bn: 'প্রকল্প / স্কিম (M06)', en: 'Project / Scheme (M06)' },
  PROJECT_DISBURSEMENT: { bn: 'প্রকল্প অর্থ ছাড় (M06)', en: 'Project Disbursement (M06)' },
  DAMAGE_ASSESSMENT: { bn: 'ক্ষয়ক্ষতি নিরূপণ ডি-ফর্ম (M09)', en: 'Damage Assessment D-Form (M09)' },
  WORKFLOW_INSTANCE: { bn: 'অনুমোদন কর্মপ্রবাহ (M11)', en: 'Workflow Instance (M11)' },
};

export const formatFileSize = (bytes) => {
  const num = Number(bytes || 0);
  if (num < 1024) return `${num} B`;
  if (num < 1024 * 1024) return `${(num / 1024).toFixed(1)} KB`;
  return `${(num / (1024 * 1024)).toFixed(2)} MB`;
};

export const formatDocumentApiError = (err, fallback) => {
  if (!err) return fallback;
  if (err.details && typeof err.details === 'object') {
    const first = Object.values(err.details)[0];
    if (Array.isArray(first) && first.length > 0) {
      return `${err.message || fallback} (${first[0]})`;
    }
  }
  return err.message || fallback;
};

export const triggerBlobDownload = (blobData, filename) => {
  const blob = blobData instanceof Blob ? blobData : new Blob([blobData]);
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.setAttribute('download', filename || 'document');
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
};
