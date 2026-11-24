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

  // Modules (Scaffolding ready for subsequent implementations)
  BENEFICIARY: {
    HOUSEHOLDS: '/beneficiary/households',
    MEMBERS: '/beneficiary/members',
    VERIFY_NID: '/beneficiary/verify-nid',
  },
  DISASTER: {
    EVENTS: '/disaster/events',
    EARLY_WARNINGS: '/disaster/warnings',
    HAZARD_ZONES: '/disaster/hazard-zones',
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
