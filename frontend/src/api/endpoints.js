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
  RELIEF: {
    REQUISITIONS: '/relief/requisitions',
    ALLOCATIONS: '/relief/allocations',
    DISTRIBUTIONS: '/relief/distributions',
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
