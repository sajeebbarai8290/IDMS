export const WORKFLOW_VIEW_ROLES = [
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
  'ROLE_WAREHOUSE_OFFICER',
  'ROLE_SHELTER_MANAGER',
];

export const WORKFLOW_TRANSITION_ROLES = [
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
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
  'ROLE_WAREHOUSE_OFFICER',
  'ROLE_SHELTER_MANAGER',
];

export const WORKFLOW_CONFIG_ROLES = ['ROLE_SUPER_ADMIN'];

export const WORKFLOW_ESCALATION_ROLES = [
  'ROLE_SUPER_ADMIN',
  'ROLE_MINISTRY_EXECUTIVE',
  'ROLE_NEOC_DIRECTOR',
  'ROLE_DDM_DIRECTOR_GENERAL',
];

const getUserRoles = (user) => {
  const roles = [...(user?.roles || [])];
  if (user?.role_code && !roles.includes(user.role_code)) {
    roles.push(user.role_code);
  }
  return roles;
};

const hasAny = (user, list) => getUserRoles(user).some((r) => list.includes(r));

export const isWorkflowBlackout = (user) => getUserRoles(user).includes('ROLE_SUPER_ADMIN');

export const canViewWorkflow = (user) => hasAny(user, WORKFLOW_VIEW_ROLES);
export const canSubmitWorkflow = (user) => !isWorkflowBlackout(user) && hasAny(user, WORKFLOW_TRANSITION_ROLES);
export const canExecuteWorkflow = (user) => !isWorkflowBlackout(user) && hasAny(user, WORKFLOW_TRANSITION_ROLES);
export const canManageWorkflowConfig = (user) => hasAny(user, WORKFLOW_CONFIG_ROLES);
export const canManageDelegations = (user) =>
  hasAny(user, WORKFLOW_CONFIG_ROLES) || hasAny(user, WORKFLOW_TRANSITION_ROLES);
export const canRunEscalations = (user) => hasAny(user, WORKFLOW_ESCALATION_ROLES);

export const WORKFLOW_STATE_META = {
  SUBMITTED: { bn: 'দাখিলকৃত', en: 'Submitted', variant: 'info' },
  INITIAL_REVIEW: { bn: 'প্রাথমিক পর্যালোচনা', en: 'Initial Review', variant: 'primary' },
  SUPERVISOR_APPROVAL: { bn: 'সুপারভাইজার অনুমোদন', en: 'Supervisor Approval', variant: 'primary' },
  DISTRICT_APPROVAL: { bn: 'জেলা পর্যায়ের অনুমোদন', en: 'District Approval', variant: 'warning' },
  DIRECTORATE_APPROVAL: { bn: 'অধিদপ্তর অনুমোদন', en: 'Directorate Approval', variant: 'warning' },
  MINISTRY_APPROVAL: { bn: 'মন্ত্রণালয় অনুমোদন', en: 'Ministry Approval', variant: 'dark' },
  FINAL_APPROVED: { bn: 'চূড়ান্ত অনুমোদিত', en: 'Final Approved', variant: 'success' },
  REJECTED: { bn: 'প্রত্যাখ্যাত', en: 'Rejected', variant: 'danger' },
  RETURNED_FOR_CORRECTION: { bn: 'সংশোধনের জন্য ফেরত', en: 'Returned for Correction', variant: 'warning' },
  INFO_REQUESTED: { bn: 'অতিরিক্ত তথ্য চাওয়া হয়েছে', en: 'Info Requested', variant: 'secondary' },
  CANCELLED: { bn: 'বাতিলকৃত', en: 'Cancelled', variant: 'secondary' },
};

export const WORKFLOW_LEVEL_META = {
  SUBMITTED: { bn: 'দাখিলকৃত', en: 'Submitted' },
  INITIAL_REVIEW: { bn: 'প্রাথমিক পর্যালোচনা', en: 'Initial Review' },
  SUPERVISOR_APPROVAL: { bn: 'সুপারভাইজার অনুমোদন', en: 'Supervisor Approval' },
  DISTRICT_APPROVAL: { bn: 'জেলা পর্যায়ের অনুমোদন', en: 'District Approval' },
  DIRECTORATE_APPROVAL: { bn: 'অধিদপ্তর পর্যায়ের অনুমোদন', en: 'Directorate Approval' },
  MINISTRY_APPROVAL: { bn: 'মন্ত্রণালয় পর্যায়ের অনুমোদন', en: 'Ministry Approval' },
  FINAL_APPROVAL: { bn: 'চূড়ান্ত অনুমোদন', en: 'Final Approval' },
};

export const WORKFLOW_PRIORITY_META = {
  LOW: { bn: 'নিম্ন', en: 'Low', variant: 'secondary' },
  NORMAL: { bn: 'সাধারণ', en: 'Normal', variant: 'info' },
  HIGH: { bn: 'উচ্চ', en: 'High', variant: 'warning' },
  URGENT: { bn: 'জরুরি', en: 'Urgent', variant: 'danger' },
};

export const WORKFLOW_ACTION_META = {
  SUBMIT: { bn: 'দাখিল', en: 'Submit', variant: 'primary' },
  RESUBMIT: { bn: 'পুনরায় দাখিল', en: 'Resubmit', variant: 'primary' },
  APPROVE: { bn: 'অনুমোদন', en: 'Approve', variant: 'success' },
  REJECT: { bn: 'প্রত্যাখ্যান', en: 'Reject', variant: 'danger' },
  RETURN_FOR_CORRECTION: { bn: 'সংশোধনের জন্য ফেরত', en: 'Return for Correction', variant: 'warning' },
  REQUEST_INFO: { bn: 'তথ্য অনুরোধ', en: 'Request Info', variant: 'info' },
  PROVIDE_INFO: { bn: 'তথ্য প্রদান', en: 'Provide Info', variant: 'primary' },
  FORWARD: { bn: 'ফরোয়ার্ড করুন', en: 'Forward', variant: 'secondary' },
  ESCALATE: { bn: 'এস্কেলেশন', en: 'Escalate', variant: 'danger' },
  CANCEL: { bn: 'বাতিল', en: 'Cancel', variant: 'dark' },
};

export const formatWorkflowApiError = (err, fallback) => {
  if (!err) return fallback;
  if (err.details && typeof err.details === 'object') {
    const firstKey = Object.keys(err.details)[0];
    if (firstKey && Array.isArray(err.details[firstKey]) && err.details[firstKey][0]) {
      return err.details[firstKey][0];
    }
  }
  return err.message || fallback;
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
