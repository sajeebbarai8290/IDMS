import axiosClient from './axiosClient';
import { ENDPOINTS } from './endpoints';

export const volunteerApi = {
  getDashboard: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.DASHBOARD, { params }),
  getLookups: () => axiosClient.get(ENDPOINTS.VOLUNTEER.LOOKUPS),
  getGeoJson: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.GEOJSON, { params }),
  getReports: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.REPORTS, { params }),
  exportCsv: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.EXPORT, { params, responseType: 'blob' }),

  getCategories: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.CATEGORIES, { params }),
  createCategory: (payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.CATEGORIES, payload),
  updateCategory: (id, payload) => axiosClient.put(ENDPOINTS.VOLUNTEER.CATEGORY_DETAIL(id), payload),

  getSkills: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.SKILLS, { params }),
  createSkill: (payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.SKILLS, payload),
  updateSkill: (id, payload) => axiosClient.put(ENDPOINTS.VOLUNTEER.SKILL_DETAIL(id), payload),

  getOrganizations: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.ORGANIZATIONS, { params }),
  getOrganization: (id) => axiosClient.get(ENDPOINTS.VOLUNTEER.ORGANIZATION_DETAIL(id)),
  createOrganization: (payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.ORGANIZATIONS, payload),
  updateOrganization: (id, payload) => axiosClient.put(ENDPOINTS.VOLUNTEER.ORGANIZATION_DETAIL(id), payload),
  deleteOrganization: (id) => axiosClient.delete(ENDPOINTS.VOLUNTEER.ORGANIZATION_DETAIL(id)),
  verifyOrganization: (id, payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.ORGANIZATION_VERIFY(id), payload),

  getVolunteers: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.VOLUNTEERS, { params }),
  getVolunteer: (id) => axiosClient.get(ENDPOINTS.VOLUNTEER.VOLUNTEER_DETAIL(id)),
  createVolunteer: (payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.VOLUNTEERS, payload),
  updateVolunteer: (id, payload) => axiosClient.put(ENDPOINTS.VOLUNTEER.VOLUNTEER_DETAIL(id), payload),
  deleteVolunteer: (id) => axiosClient.delete(ENDPOINTS.VOLUNTEER.VOLUNTEER_DETAIL(id)),
  uploadPhoto: (id, formData) =>
    axiosClient.post(ENDPOINTS.VOLUNTEER.VOLUNTEER_PHOTO(id), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  downloadPhoto: (id) => axiosClient.get(ENDPOINTS.VOLUNTEER.VOLUNTEER_PHOTO(id), { responseType: 'blob' }),
  revealPii: (id) => axiosClient.post(ENDPOINTS.VOLUNTEER.VOLUNTEER_REVEAL_PII(id)),
  syncSkills: (id, payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.VOLUNTEER_SKILLS(id), payload),
  submitVolunteer: (id, payload = {}) => axiosClient.post(ENDPOINTS.VOLUNTEER.VOLUNTEER_SUBMIT(id), payload),
  verifyPillar: (id, payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.VOLUNTEER_VERIFY_PILLAR(id), payload),
  approveVolunteer: (id, payload = {}) => axiosClient.post(ENDPOINTS.VOLUNTEER.VOLUNTEER_APPROVE(id), payload),
  rejectVolunteer: (id, payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.VOLUNTEER_REJECT(id), payload),

  getDeployments: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.DEPLOYMENTS, { params }),
  getDeployment: (id) => axiosClient.get(ENDPOINTS.VOLUNTEER.DEPLOYMENT_DETAIL(id)),
  createDeployment: (payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.DEPLOYMENTS, payload),
  activateDeployment: (id, payload = {}) => axiosClient.post(ENDPOINTS.VOLUNTEER.DEPLOYMENT_ACTIVATE(id), payload),
  completeDeployment: (id, payload = {}) => axiosClient.post(ENDPOINTS.VOLUNTEER.DEPLOYMENT_COMPLETE(id), payload),
  cancelDeployment: (id, payload = {}) => axiosClient.post(ENDPOINTS.VOLUNTEER.DEPLOYMENT_CANCEL(id), payload),

  getAttendances: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.ATTENDANCES, { params }),
  checkIn: (deploymentId, payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.DEPLOYMENT_CHECK_IN(deploymentId), payload),
  checkOut: (attendanceId, payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.ATTENDANCE_CHECK_OUT(attendanceId), payload),
  confirmAttendance: (attendanceId, payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.ATTENDANCE_CONFIRM(attendanceId), payload),
  uploadAttendanceEvidence: (attendanceId, formData) =>
    axiosClient.post(ENDPOINTS.VOLUNTEER.ATTENDANCE_EVIDENCE(attendanceId), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  downloadAttendanceEvidence: (attendanceId) =>
    axiosClient.get(ENDPOINTS.VOLUNTEER.ATTENDANCE_EVIDENCE(attendanceId), { responseType: 'blob' }),

  getTrainingPrograms: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.TRAINING_PROGRAMS, { params }),
  createTrainingProgram: (payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.TRAINING_PROGRAMS, payload),
  updateTrainingProgram: (id, payload) => axiosClient.put(ENDPOINTS.VOLUNTEER.TRAINING_PROGRAM_DETAIL(id), payload),

  getTrainingSessions: (params = {}) => axiosClient.get(ENDPOINTS.VOLUNTEER.TRAINING_SESSIONS, { params }),
  getTrainingSession: (id) => axiosClient.get(ENDPOINTS.VOLUNTEER.TRAINING_SESSION_DETAIL(id)),
  createTrainingSession: (payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.TRAINING_SESSIONS, payload),
  updateTrainingSession: (id, payload) => axiosClient.put(ENDPOINTS.VOLUNTEER.TRAINING_SESSION_DETAIL(id), payload),
  enrollVolunteer: (sessionId, payload) => axiosClient.post(ENDPOINTS.VOLUNTEER.TRAINING_SESSION_ENROLL(sessionId), payload),
  updateEnrollment: (enrollmentId, payload) => axiosClient.put(ENDPOINTS.VOLUNTEER.TRAINING_ENROLLMENT_DETAIL(enrollmentId), payload),
  uploadCertificate: (enrollmentId, formData) =>
    axiosClient.post(ENDPOINTS.VOLUNTEER.TRAINING_ENROLLMENT_CERTIFICATE(enrollmentId), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
  downloadCertificate: (enrollmentId) =>
    axiosClient.get(ENDPOINTS.VOLUNTEER.TRAINING_ENROLLMENT_CERTIFICATE(enrollmentId), { responseType: 'blob' }),
};

export default volunteerApi;
