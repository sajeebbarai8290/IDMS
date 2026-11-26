/**
 * Centralized IDMS API Endpoints Dictionary
 */

export const ENDPOINTS = {
  // Core System Inspection
  HEALTH: '/health',
  SYSTEM_INFO: '/system/info',
  AUTH: {
    USER: '/auth/user',
    SYNC: '/auth/sync',
    SCOPE_CHECK: '/auth/scope-check',
  },

  // Administrative Units & Disasters
  ADMIN_UNITS: '/administrative-units',
  DISASTERS: '/disasters',

  // Module 01: Beneficiary Management
  BENEFICIARY: {
    HOUSEHOLDS: '/beneficiary/households',
    MEMBERS: (householdId) => `/beneficiary/households/${householdId}/members`,
    MEMBER_DELETE: (householdId, memberId) => `/beneficiary/households/${householdId}/members/${memberId}`,
    REVEAL_PII: (householdId) => `/beneficiary/households/${householdId}/reveal-pii`,
    VERIFY_STEP: (householdId) => `/beneficiary/households/${householdId}/verify-step`,
    APPROVAL: (householdId) => `/beneficiary/households/${householdId}/approval`,
    DISASTER_LINK: (householdId) => `/beneficiary/households/${householdId}/disaster-eligibility`,
    DISASTER_DISBURSE: (householdId, eligibilityId) => `/beneficiary/households/${householdId}/disaster-eligibility/${eligibilityId}/disburse`,
    VERIFY_NID: '/beneficiary/verify-nid',
    DUPLICATES: '/beneficiary/duplicates',
    RESOLVE_DUPLICATE: (id) => `/beneficiary/duplicates/${id}/resolve`,
    DASHBOARD_METRICS: '/beneficiary/dashboard/metrics',
    DASHBOARD_GEO: '/beneficiary/dashboard/geo-breakdown',
    DASHBOARD_DISASTERS: '/beneficiary/dashboard/disaster-breakdown',
    IMPORT: '/beneficiary/import',
    EXPORT: '/beneficiary/export',
    TEMPLATE: '/beneficiary/template',
  },

  // Module 02: National ID Verification
  NID: {
    DASHBOARD: '/nid/dashboard',
    PROVIDER_HEALTH: '/nid/provider-health',
    VERIFICATIONS: '/nid/verifications',
    VERIFICATION_DETAIL: (id) => `/nid/verifications/${id}`,
    RETRY: (id) => `/nid/verifications/${id}/retry`,
    MANUAL_REVIEW: (id) => `/nid/verifications/${id}/manual-review`,
  },

  // Module 03: Disaster Information Management
  DISASTER: {
    DASHBOARD: '/disaster/dashboard',
    GEOJSON: '/disaster/gis/geojson',
    CATEGORIES: '/disaster/categories',
    WARNINGS: '/disaster/warnings',
    EVENTS: '/disaster/events',
    EVENT_DETAIL: (id) => `/disaster/events/${id}`,
    IMPACT: (id) => `/disaster/events/${id}/impact`,
    BOUNDARY: (id) => `/disaster/events/${id}/boundary`,
    LIFECYCLE: (id) => `/disaster/events/${id}/lifecycle`,
    EVENT_WARNINGS: (id) => `/disaster/events/${id}/warnings`,
    TIMELINES: (id) => `/disaster/events/${id}/timelines`,
    TIMELINE_ATTACHMENT: (eventId, timelineId) => `/disaster/events/${eventId}/timelines/${timelineId}/attachment`,
  },
  // Module 04: Relief Distribution Management
  RELIEF: {
    DASHBOARD: '/relief/dashboard',
    LOOKUPS: '/relief/lookups',
    ELIGIBLE_HOUSEHOLDS: '/relief/eligible-households',
    CATEGORIES: '/relief/categories',
    CATEGORY_DETAIL: (id) => `/relief/categories/${id}`,
    REQUISITIONS: '/relief/requisitions',
    REQUISITION_DETAIL: (id) => `/relief/requisitions/${id}`,
    REQUISITION_SUBMIT: (id) => `/relief/requisitions/${id}/submit`,
    REQUISITION_REVIEW: (id) => `/relief/requisitions/${id}/review`,
    REQUISITION_APPROVE: (id) => `/relief/requisitions/${id}/approve`,
    REQUISITION_REJECT: (id) => `/relief/requisitions/${id}/reject`,
    REQUISITION_DOCUMENT: (id) => `/relief/requisitions/${id}/document`,
    ALLOCATIONS: '/relief/allocations',
    ALLOCATION_DETAIL: (id) => `/relief/allocations/${id}`,
    ALLOCATION_BENEFICIARIES: (id) => `/relief/allocations/${id}/beneficiaries`,
    ALLOCATION_APPROVE: (id) => `/relief/allocations/${id}/approve`,
    ALLOCATION_DISPATCH: (id) => `/relief/allocations/${id}/dispatch`,
    ALLOCATION_CANCEL: (id) => `/relief/allocations/${id}/cancel`,
    DISTRIBUTIONS: '/relief/distributions',
    CHECK_DUPLICATE: '/relief/distributions/check-duplicate',
    EXPORT_MUSTER: '/relief/distributions/export-muster',
    VOUCHER_VERIFY: (id) => `/relief/distributions/${id}/verify`,
    VOUCHER_VOID: (id) => `/relief/distributions/${id}/void`,
    VOUCHER_PHOTO: (id) => `/relief/distributions/${id}/photo`,
  },

  // Module 05: Disaster Response Management
  RESPONSE: {
    DASHBOARD: '/response/dashboard',
    LOOKUPS: '/response/lookups',
    GEOJSON: '/response/gis/geojson',
    ACTIVITIES: '/response/activities',
    OPERATIONS: '/response/operations',
    OPERATION_DETAIL: (id) => `/response/operations/${id}`,
    OPERATION_LIFECYCLE: (id) => `/response/operations/${id}/lifecycle`,
    OPERATION_AGENCIES: (id) => `/response/operations/${id}/agencies`,
    OPERATION_TEAMS: (id) => `/response/operations/${id}/teams`,
    TEAM_UPDATE: (id) => `/response/teams/${id}`,
    OPERATION_RESOURCES: (id) => `/response/operations/${id}/resources`,
    RESOURCE_UPDATE: (id) => `/response/resources/${id}`,
    OPERATION_TASKS: (id) => `/response/operations/${id}/tasks`,
    TASK_UPDATE: (id) => `/response/tasks/${id}`,
    TASK_ATTACHMENTS: (id) => `/response/tasks/${id}/attachments`,
    TASK_ATTACHMENT_DOWNLOAD: (taskId, attId) => `/response/tasks/${taskId}/attachments/${attId}`,
    REPORTS: '/response/reports',
    OPERATION_REPORTS: (id) => `/response/operations/${id}/reports`,
  },

  // Module 06: Project Management
  PROJECT: {
    DASHBOARD: '/project/dashboard',
    LOOKUPS: '/project/lookups',
    GEOJSON: '/project/gis/geojson',
    REPORTS: '/project/reports',
    EXPORT: '/project/export',
    CATEGORIES: '/project/categories',
    SCHEMES: '/project/schemes',
    SCHEME_DETAIL: (id) => `/project/schemes/${id}`,
    SCHEME_LIFECYCLE: (id) => `/project/schemes/${id}/lifecycle`,
    SCHEME_BOUNDARY: (id) => `/project/schemes/${id}/boundary`,
    SCHEME_MILESTONES: (id) => `/project/schemes/${id}/milestones`,
    MILESTONE_PROGRESS: (id) => `/project/milestones/${id}/progress`,
    MILESTONE_CERTIFY: (id) => `/project/milestones/${id}/certify`,
    SCHEME_FINANCIALS: (id) => `/project/schemes/${id}/financials`,
    FINANCIAL_APPROVE: (id) => `/project/financials/${id}/approve`,
    FINANCIAL_DOCUMENT: (id) => `/project/financials/${id}/document`,
    SCHEME_MONITORING: (id) => `/project/schemes/${id}/monitoring-reports`,
    MONITORING_ATTACHMENT: (id) => `/project/monitoring-reports/${id}/attachment`,
    SCHEME_RISKS: (id) => `/project/schemes/${id}/risks`,
    RISK_UPDATE: (id) => `/project/risks/${id}`,
  },
  SHELTER: {
    LIST: '/shelter/shelters',
    OCCUPANCY: '/shelter/occupancy',
  },
  DAMAGE: {
    D_FORMS: '/damage/d-forms',
  },
  WAREHOUSE: {
    FACILITIES: '/warehouse/facilities',
    INVENTORIES: '/warehouse/inventories',
  },
};
