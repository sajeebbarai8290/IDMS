import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { volunteerApi } from '../../../api/volunteerApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { ConfirmationStatusBadge, DeploymentStatusBadge } from '../components/VolunteerStatusBadges';
import {
  canConfirmVolunteerAttendance,
  canDeployVolunteers,
  canRecordVolunteerAttendance,
  formatVolunteerApiError,
} from '../utils/volunteerPermissions';

export const VolunteerDeploymentPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [tab, setTab] = useState('deployments');
  const [showDeployModal, setShowDeployModal] = useState(false);
  const [checkInTarget, setCheckInTarget] = useState(null);
  const [checkOutTarget, setCheckOutTarget] = useState(null);
  const [feedback, setFeedback] = useState(null);

  const [deployForm, setDeployForm] = useState({
    volunteer_id: '',
    disaster_event_id: '',
    response_operation_id: '',
    administrative_unit_id: '',
    deployment_location: '',
    assigned_role: 'SEARCH_AND_RESCUE',
    assigned_duties: '',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    supervisor_name: '',
    supervisor_contact: '',
    activate_immediately: true,
  });

  const [checkInForm, setCheckInForm] = useState({
    gps_consent_given: false,
    latitude: '',
    longitude: '',
    activity_summary: '',
  });

  const [checkOutForm, setCheckOutForm] = useState({
    activity_summary: '',
    tasks_completed: '',
    beneficiaries_assisted: 0,
    gps_consent_given: false,
    latitude: '',
    longitude: '',
  });

  const { data: lookupsRes } = useQuery({
    queryKey: ['volunteer', 'lookups'],
    queryFn: () => volunteerApi.getLookups(),
  });

  const { data: volListRes } = useQuery({
    queryKey: ['volunteer', 'volunteers', 'deployable'],
    queryFn: () => volunteerApi.getVolunteers({ overall_verification_status: 'VERIFIED', per_page: 100 }),
  });

  const { data: depRes, isLoading: loadingDeps } = useQuery({
    queryKey: ['volunteer', 'deployments'],
    queryFn: () => volunteerApi.getDeployments({ per_page: 50 }),
  });

  const { data: attRes, isLoading: loadingAtt } = useQuery({
    queryKey: ['volunteer', 'attendances'],
    queryFn: () => volunteerApi.getAttendances({ per_page: 50 }),
  });

  const deployMutation = useMutation({
    mutationFn: (payload) => volunteerApi.createDeployment(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setShowDeployModal(false);
      setFeedback({ variant: 'success', message: t('স্বেচ্ছাসেবক মোতায়েন সম্পন্ন হয়েছে।', 'Volunteer deployed successfully.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('মোতায়েন ব্যর্থ।', 'Deployment failed.')) }),
  });

  const transitionMutation = useMutation({
    mutationFn: ({ id, action }) =>
      action === 'activate'
        ? volunteerApi.activateDeployment(id)
        : action === 'complete'
          ? volunteerApi.completeDeployment(id)
          : volunteerApi.cancelDeployment(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setFeedback({ variant: 'success', message: t('মোতায়েন অবস্থা হালনাগাদ হয়েছে।', 'Deployment status updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('হালনাগাদ ব্যর্থ।', 'Update failed.')) }),
  });

  const checkInMutation = useMutation({
    mutationFn: ({ deploymentId, payload }) => volunteerApi.checkIn(deploymentId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setCheckInTarget(null);
      setFeedback({ variant: 'success', message: t('চেক-ইন সম্পন্ন হয়েছে।', 'Check-in recorded.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('চেক-ইন ব্যর্থ।', 'Check-in failed.')) }),
  });

  const checkOutMutation = useMutation({
    mutationFn: ({ attendanceId, payload }) => volunteerApi.checkOut(attendanceId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setCheckOutTarget(null);
      setFeedback({ variant: 'success', message: t('চেক-আউট ও কার্যবিবরণী সংরক্ষিত হয়েছে।', 'Check-out & activity report saved.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('চেক-আউট ব্যর্থ।', 'Check-out failed.')) }),
  });

  const confirmMutation = useMutation({
    mutationFn: ({ id, status }) => volunteerApi.confirmAttendance(id, { confirmation_status: status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setFeedback({ variant: 'success', message: t('সুপারভাইজার নিশ্চিতকরণ সম্পন্ন হয়েছে।', 'Attendance confirmation updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('নিশ্চিতকরণ ব্যর্থ।', 'Confirmation failed.')) }),
  });

  const lookups = lookupsRes?.data || {};
  const deployableVolunteers = (volListRes?.data || []).filter((v) => ['AVAILABLE', 'STANDBY'].includes(v.availability_status));
  const deployments = depRes?.data || [];
  const attendances = attRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h3 className="mb-0">{t('স্বেচ্ছাসেবক মোতায়েন ও উপস্থিতি (Deployments & Attendance)', 'Volunteer Deployments & Attendance')}</h3>
          <small className="text-muted">
            {t('দুর্যোগ ও সাড়াদান অভিযানে স্বেচ্ছাসেবক নিয়োগ, চেক-ইন/আউট ও সুপারভাইজার অনুমোদন', 'Assign volunteers to disasters/operations, log check-in/out & supervisor confirmations')}
          </small>
        </div>
        {canDeployVolunteers(user) && (
          <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowDeployModal(true)}>
            <i className="bi bi-send-plus-fill me-1"></i>{t('নতুন মোতায়েন', 'Assign Deployment')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <ul className="nav nav-tabs mb-3">
        <li className="nav-item">
          <button type="button" className={`nav-link ${tab === 'deployments' ? 'active' : ''}`} onClick={() => setTab('deployments')}>
            {t('মোতায়েন তালিকা', 'Deployments')} ({deployments.length})
          </button>
        </li>
        <li className="nav-item">
          <button type="button" className={`nav-link ${tab === 'attendance' ? 'active' : ''}`} onClick={() => setTab('attendance')}>
            {t('উপস্থিতি ও কার্যবিবরণী', 'Attendance & Activity Reports')} ({attendances.length})
          </button>
        </li>
      </ul>

      {tab === 'deployments' && (
        loadingDeps ? <Loader /> : deployments.length === 0 ? <EmptyState title={t('কোনো মোতায়েন নেই', 'No deployments yet')} /> : (
          <div className="card shadow-sm">
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('মোতায়েন নং', 'Deployment No')}</th>
                    <th>{t('স্বেচ্ছাসেবক', 'Volunteer')}</th>
                    <th>{t('দুর্যোগ / অভিযান', 'Disaster / Operation')}</th>
                    <th>{t('স্থান ও দায়িত্ব', 'Location & Role')}</th>
                    <th>{t('সময়কাল', 'Dates')}</th>
                    <th>{t('অবস্থা', 'Status')}</th>
                    <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                  </tr>
                </thead>
                <tbody>
                  {deployments.map((d) => (
                    <tr key={d.id}>
                      <td className="fw-semibold">{d.deployment_no}</td>
                      <td>
                        <div>{t(d.volunteer?.full_name_bn || d.volunteer?.full_name_en, d.volunteer?.full_name_en)}</div>
                        <small className="text-muted">{d.volunteer?.volunteer_code}</small>
                      </td>
                      <td>
                        <div>{d.disaster_event?.disaster_code || '-'}</div>
                        <small className="text-muted">{d.response_operation?.operation_no || '-'}</small>
                      </td>
                      <td>
                        <div>{d.deployment_location}</div>
                        <small className="text-muted">{d.assigned_role}</small>
                      </td>
                      <td>{d.start_date} {d.end_date ? `→ ${d.end_date}` : ''}</td>
                      <td><DeploymentStatusBadge status={d.deployment_status} /></td>
                      <td className="text-end">
                        {canRecordVolunteerAttendance(user) && ['ASSIGNED', 'ACTIVE'].includes(d.deployment_status) && (
                          <button type="button" className="btn btn-sm btn-outline-success me-1" onClick={() => setCheckInTarget(d)}>
                            {t('চেক-ইন', 'Check In')}
                          </button>
                        )}
                        {canDeployVolunteers(user) && d.deployment_status === 'ASSIGNED' && (
                          <button type="button" className="btn btn-sm btn-outline-primary me-1" onClick={() => transitionMutation.mutate({ id: d.id, action: 'activate' })}>
                            {t('সক্রিয় করুন', 'Activate')}
                          </button>
                        )}
                        {canDeployVolunteers(user) && ['ASSIGNED', 'ACTIVE'].includes(d.deployment_status) && (
                          <button type="button" className="btn btn-sm btn-outline-secondary me-1" onClick={() => transitionMutation.mutate({ id: d.id, action: 'complete' })}>
                            {t('সম্পন্ন', 'Complete')}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )
      )}

      {tab === 'attendance' && (
        loadingAtt ? <Loader /> : attendances.length === 0 ? <EmptyState title={t('কোনো উপস্থিতি রেকর্ড নেই', 'No attendance records')} /> : (
          <div className="card shadow-sm">
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('তারিখ', 'Date')}</th>
                    <th>{t('স্বেচ্ছাসেবক', 'Volunteer')}</th>
                    <th>{t('মোতায়েন', 'Deployment')}</th>
                    <th>{t('সময় ও সময়কাল', 'Check-in / Duration')}</th>
                    <th>{t('কার্যবিবরণী', 'Activity Summary')}</th>
                    <th>{t('নিশ্চিতকরণ', 'Confirmation')}</th>
                    <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                  </tr>
                </thead>
                <tbody>
                  {attendances.map((a) => (
                    <tr key={a.id}>
                      <td>{a.attendance_date}</td>
                      <td>{t(a.volunteer?.full_name_bn || a.volunteer?.full_name_en, a.volunteer?.full_name_en)}</td>
                      <td>{a.deployment?.deployment_no}</td>
                      <td>
                        <div>{a.check_in_at ? new Date(a.check_in_at).toLocaleTimeString() : '-'} → {a.check_out_at ? new Date(a.check_out_at).toLocaleTimeString() : t('চলমান', 'Open')}</div>
                        <small className="text-muted">{a.duration_minutes != null ? `${a.duration_minutes} mins` : ''}</small>
                      </td>
                      <td>
                        <div>{a.activity_summary || '-'}</div>
                        <small className="text-muted">{t('সহায়তাপ্রাপ্ত:', 'Assisted:')} {a.beneficiaries_assisted}</small>
                      </td>
                      <td><ConfirmationStatusBadge status={a.confirmation_status} /></td>
                      <td className="text-end">
                        {canRecordVolunteerAttendance(user) && !a.check_out_at && (
                          <button type="button" className="btn btn-sm btn-outline-primary me-1" onClick={() => setCheckOutTarget(a)}>
                            {t('চেক-আউট', 'Check Out')}
                          </button>
                        )}
                        {canConfirmVolunteerAttendance(user) && a.confirmation_status === 'PENDING' && (
                          <>
                            <button type="button" className="btn btn-sm btn-outline-success me-1" onClick={() => confirmMutation.mutate({ id: a.id, status: 'CONFIRMED' })}>
                              {t('নিশ্চিত করুন', 'Confirm')}
                            </button>
                            <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => confirmMutation.mutate({ id: a.id, status: 'DISPUTED' })}>
                              {t('আপত্তি', 'Dispute')}
                            </button>
                          </>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )
      )}

      {/* Deploy Modal */}
      {showDeployModal && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog modal-lg">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                deployMutation.mutate({
                  ...deployForm,
                  disaster_event_id: deployForm.disaster_event_id || null,
                  response_operation_id: deployForm.response_operation_id || null,
                  end_date: deployForm.end_date || null,
                });
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title">{t('স্বেচ্ছাসেবক মোতায়েন করুন', 'Deploy Volunteer')}</h5>
                <button type="button" className="btn-close" onClick={() => setShowDeployModal(false)} />
              </div>
              <div className="modal-body">
                <div className="row g-2">
                  <div className="col-md-6">
                    <label className="form-label small">{t('যাচাইকৃত ও উপলব্ধ স্বেচ্ছাসেবক', 'Verified Available Volunteer')} *</label>
                    <select className="form-select form-select-sm" required value={deployForm.volunteer_id} onChange={(e) => setDeployForm({ ...deployForm, volunteer_id: e.target.value })}>
                      <option value="">{t('নির্বাচন করুন', 'Select Volunteer')}</option>
                      {deployableVolunteers.map((v) => (
                        <option key={v.id} value={v.id}>{v.volunteer_code} — {t(v.full_name_bn, v.full_name_en)}</option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('মোতায়েন প্রশাসনিক ইউনিট', 'Deployment Unit')} *</label>
                    <select className="form-select form-select-sm" required value={deployForm.administrative_unit_id} onChange={(e) => setDeployForm({ ...deployForm, administrative_unit_id: e.target.value })}>
                      <option value="">{t('নির্বাচন করুন', 'Select Unit')}</option>
                      {(lookups.administrative_units || []).map((u) => <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)}</option>)}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('দুর্যোগ ইভেন্ট', 'Disaster Event')}</label>
                    <select className="form-select form-select-sm" value={deployForm.disaster_event_id} onChange={(e) => setDeployForm({ ...deployForm, disaster_event_id: e.target.value })}>
                      <option value="">{t('ঐচ্ছিক', 'Optional')}</option>
                      {(lookups.disasters || []).map((d) => <option key={d.id} value={d.id}>{d.disaster_code} — {t(d.name_bn, d.name_en)}</option>)}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('সাড়াদান অভিযান', 'Response Operation')}</label>
                    <select className="form-select form-select-sm" value={deployForm.response_operation_id} onChange={(e) => setDeployForm({ ...deployForm, response_operation_id: e.target.value })}>
                      <option value="">{t('ঐচ্ছিক', 'Optional')}</option>
                      {(lookups.response_operations || []).map((op) => <option key={op.id} value={op.id}>{op.operation_no} — {t(op.title_bn || op.title_en, op.title_en)}</option>)}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('মোতায়েন স্থান', 'Deployment Location')} *</label>
                    <input className="form-control form-control-sm" required value={deployForm.deployment_location} onChange={(e) => setDeployForm({ ...deployForm, deployment_location: e.target.value })} />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('নির্ধারিত ভূমিকা', 'Assigned Role')} *</label>
                    <input className="form-control form-control-sm" required value={deployForm.assigned_role} onChange={(e) => setDeployForm({ ...deployForm, assigned_role: e.target.value })} />
                  </div>
                  <div className="col-12">
                    <label className="form-label small">{t('নির্ধারিত দায়িত্বসমূহ', 'Assigned Duties')} *</label>
                    <textarea className="form-control form-control-sm" rows="2" required value={deployForm.assigned_duties} onChange={(e) => setDeployForm({ ...deployForm, assigned_duties: e.target.value })} />
                  </div>
                  <div className="col-md-3">
                    <label className="form-label small">{t('শুরুর তারিখ', 'Start Date')} *</label>
                    <input type="date" className="form-control form-control-sm" required value={deployForm.start_date} onChange={(e) => setDeployForm({ ...deployForm, start_date: e.target.value })} />
                  </div>
                  <div className="col-md-3">
                    <label className="form-label small">{t('শেষ তারিখ', 'End Date')}</label>
                    <input type="date" className="form-control form-control-sm" value={deployForm.end_date} onChange={(e) => setDeployForm({ ...deployForm, end_date: e.target.value })} />
                  </div>
                  <div className="col-md-3">
                    <label className="form-label small">{t('সুপারভাইজার নাম', 'Supervisor Name')} *</label>
                    <input className="form-control form-control-sm" required value={deployForm.supervisor_name} onChange={(e) => setDeployForm({ ...deployForm, supervisor_name: e.target.value })} />
                  </div>
                  <div className="col-md-3">
                    <label className="form-label small">{t('সুপারভাইজার ফোন', 'Supervisor Contact')}</label>
                    <input className="form-control form-control-sm" value={deployForm.supervisor_contact} onChange={(e) => setDeployForm({ ...deployForm, supervisor_contact: e.target.value })} />
                  </div>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setShowDeployModal(false)}>{t('বাতিল', 'Cancel')}</button>
                <button type="submit" className="btn btn-primary btn-sm" disabled={deployMutation.isPending}>{t('মোতায়েন নিশ্চিত করুন', 'Deploy')}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Check-in Modal */}
      {checkInTarget && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                checkInMutation.mutate({
                  deploymentId: checkInTarget.id,
                  payload: {
                    ...checkInForm,
                    latitude: checkInForm.gps_consent_given && checkInForm.latitude !== '' ? Number(checkInForm.latitude) : null,
                    longitude: checkInForm.gps_consent_given && checkInForm.longitude !== '' ? Number(checkInForm.longitude) : null,
                  },
                });
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title">{t('উপস্থিতি চেক-ইন', 'Deployment Check-In')} ({checkInTarget.deployment_no})</h5>
                <button type="button" className="btn-close" onClick={() => setCheckInTarget(null)} />
              </div>
              <div className="modal-body">
                <div className="form-check mb-2">
                  <input id="gpsCheckInConsent" className="form-check-input" type="checkbox" checked={checkInForm.gps_consent_given} onChange={(e) => setCheckInForm({ ...checkInForm, gps_consent_given: e.target.checked })} />
                  <label className="form-check-label small" htmlFor="gpsCheckInConsent">
                    {t('চেক-ইন GPS অবস্থান সংরক্ষণের সম্মতি প্রদান করা হয়েছে', 'Explicit consent given to record check-in GPS coordinates')}
                  </label>
                </div>
                {checkInForm.gps_consent_given && (
                  <div className="row g-2 mb-2">
                    <div className="col-6"><input type="number" step="0.000001" required className="form-control form-control-sm" placeholder="Latitude" value={checkInForm.latitude} onChange={(e) => setCheckInForm({ ...checkInForm, latitude: e.target.value })} /></div>
                    <div className="col-6"><input type="number" step="0.000001" required className="form-control form-control-sm" placeholder="Longitude" value={checkInForm.longitude} onChange={(e) => setCheckInForm({ ...checkInForm, longitude: e.target.value })} /></div>
                  </div>
                )}
                <textarea className="form-control form-control-sm" rows="2" placeholder={t('প্রাথমিক নোট...', 'Initial duty notes...')} value={checkInForm.activity_summary} onChange={(e) => setCheckInForm({ ...checkInForm, activity_summary: e.target.value })} />
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setCheckInTarget(null)}>{t('বাতিল', 'Cancel')}</button>
                <button type="submit" className="btn btn-success btn-sm">{t('চেক-ইন সংরক্ষণ', 'Confirm Check-In')}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Check-out Modal */}
      {checkOutTarget && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                checkOutMutation.mutate({
                  attendanceId: checkOutTarget.id,
                  payload: {
                    ...checkOutForm,
                    beneficiaries_assisted: Number(checkOutForm.beneficiaries_assisted || 0),
                    latitude: checkOutForm.gps_consent_given && checkOutForm.latitude !== '' ? Number(checkOutForm.latitude) : null,
                    longitude: checkOutForm.gps_consent_given && checkOutForm.longitude !== '' ? Number(checkOutForm.longitude) : null,
                  },
                });
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title">{t('চেক-আউট ও কার্যবিবরণী', 'Check-Out & Activity Report')}</h5>
                <button type="button" className="btn-close" onClick={() => setCheckOutTarget(null)} />
              </div>
              <div className="modal-body">
                <div className="mb-2">
                  <label className="form-label small">{t('কার্যক্রমের সারসংক্ষেপ', 'Activity Summary')} *</label>
                  <textarea className="form-control form-control-sm" rows="2" required value={checkOutForm.activity_summary} onChange={(e) => setCheckOutForm({ ...checkOutForm, activity_summary: e.target.value })} />
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('সম্পন্ন কাজসমূহ', 'Tasks Completed')}</label>
                  <input className="form-control form-control-sm" value={checkOutForm.tasks_completed} onChange={(e) => setCheckOutForm({ ...checkOutForm, tasks_completed: e.target.value })} />
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('সহায়তাপ্রাপ্ত উপকারভোগীর সংখ্যা', 'Beneficiaries Assisted')}</label>
                  <input type="number" min="0" className="form-control form-control-sm" value={checkOutForm.beneficiaries_assisted} onChange={(e) => setCheckOutForm({ ...checkOutForm, beneficiaries_assisted: e.target.value })} />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setCheckOutTarget(null)}>{t('বাতিল', 'Cancel')}</button>
                <button type="submit" className="btn btn-primary btn-sm">{t('চেক-আউট সংরক্ষণ', 'Complete Check-Out')}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default VolunteerDeploymentPage;
