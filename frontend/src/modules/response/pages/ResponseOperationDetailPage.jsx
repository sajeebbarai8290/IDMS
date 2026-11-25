import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { responseApi } from '../../../api/responseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ResponseGisMap from '../components/ResponseGisMap';
import {
  canCreateOperation,
  canCreateReport,
  canManageCoordination,
  canManageLifecycle,
  canManageTasks,
  downloadResponseBlob,
  formatResponseApiError,
} from '../utils/responsePermissions';
import { OperationStatusBadge, TaskStatusBadge } from '../components/ResponseStatusBadges';

export const ResponseOperationDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [tab, setTab] = useState('tasks');
  const [feedback, setFeedback] = useState(null);
  const [lifecycleNote, setLifecycleNote] = useState('');

  // Task form state
  const [taskForm, setTaskForm] = useState({
    activity_type_id: '',
    response_team_id: '',
    title_en: '',
    title_bn: '',
    assigned_officer_name: '',
    priority: 'HIGH',
    deadline_at: '',
  });
  const [attTaskId, setAttTaskId] = useState(null);
  const [attFile, setAttFile] = useState(null);

  // Agency & Team state
  const [agencyForm, setAgencyForm] = useState({
    agency_name: '',
    agency_type: 'GOVERNMENT',
    role_summary: '',
    focal_person_name: '',
    focal_contact: '',
  });
  const [teamForm, setTeamForm] = useState({
    team_name: '',
    agency_name: '',
    team_type: 'FIRE_SERVICE',
    leader_name: '',
    leader_contact: '',
    member_count: 8,
    latitude: '',
    longitude: '',
  });

  // Resource state
  const [resForm, setResForm] = useState({
    resource_asset_id: '',
    requirement_title: '',
    quantity_needed: 1,
    unit: 'UNIT',
    priority: 'HIGH',
  });

  // Situation Report state
  const [repForm, setRepForm] = useState({
    report_type: 'INCIDENT',
    title_en: '',
    title_bn: '',
    summary: '',
    casualties_injured: 0,
    casualties_missing: 0,
    casualties_fatalities: 0,
    rescued_count: 0,
    evacuated_count: 0,
    emergency_requirements: '',
    location_description: '',
    latitude: '',
    longitude: '',
    privacy_level: 'PUBLIC_AREA',
  });

  const { data: opRes, isLoading, error } = useQuery({
    queryKey: ['response', 'operations', id],
    queryFn: () => responseApi.getOperation(id),
  });

  const { data: lookupsRes } = useQuery({
    queryKey: ['response', 'lookups'],
    queryFn: () => responseApi.getLookups(),
  });

  const { data: geoRes } = useQuery({
    queryKey: ['response', 'geojson', id],
    queryFn: () => responseApi.getGeoJson({ response_operation_id: id }),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['response'] });

  const lifecycleMutation = useMutation({
    mutationFn: ({ status }) => responseApi.transitionLifecycle(id, { status, notes: lifecycleNote || null }),
    onSuccess: () => {
      invalidate();
      setLifecycleNote('');
      setFeedback({ variant: 'success', message: t('অভিযানের অবস্থা হালনাগাদ হয়েছে।', 'Operation lifecycle updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('অবস্থা পরিবর্তন ব্যর্থ।', 'Lifecycle transition failed.')) }),
  });

  const taskMutation = useMutation({
    mutationFn: (payload) => responseApi.createTask(id, payload),
    onSuccess: () => {
      invalidate();
      setTaskForm({ activity_type_id: '', response_team_id: '', title_en: '', title_bn: '', assigned_officer_name: '', priority: 'HIGH', deadline_at: '' });
      setFeedback({ variant: 'success', message: t('টাস্ক যোগ করা হয়েছে।', 'Task assigned successfully.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('টাস্ক তৈরি ব্যর্থ।', 'Task creation failed.')) }),
  });

  const taskProgressMutation = useMutation({
    mutationFn: ({ taskId, payload }) => responseApi.updateTaskProgress(taskId, payload),
    onSuccess: invalidate,
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('অগ্রগতি আপডেট ব্যর্থ।', 'Task update failed.')) }),
  });

  const uploadAttachmentMutation = useMutation({
    mutationFn: ({ taskId, file }) => {
      const fd = new FormData();
      fd.append('file', file);
      return responseApi.uploadTaskAttachment(taskId, fd);
    },
    onSuccess: () => {
      invalidate();
      setAttTaskId(null);
      setAttFile(null);
      setFeedback({ variant: 'success', message: t('প্রমাণক সংযুক্ত হয়েছে।', 'Attachment uploaded securely.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('আপলোড ব্যর্থ।', 'Upload failed.')) }),
  });

  const agencyMutation = useMutation({
    mutationFn: (payload) => responseApi.addAgency(id, payload),
    onSuccess: () => {
      invalidate();
      setAgencyForm({ agency_name: '', agency_type: 'GOVERNMENT', role_summary: '', focal_person_name: '', focal_contact: '' });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('সংস্থা যোগ ব্যর্থ।', 'Agency coordination failed.')) }),
  });

  const teamMutation = useMutation({
    mutationFn: (payload) => responseApi.addTeam(id, payload),
    onSuccess: () => {
      invalidate();
      setTeamForm({ team_name: '', agency_name: '', team_type: 'FIRE_SERVICE', leader_name: '', leader_contact: '', member_count: 8, latitude: '', longitude: '' });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('দল যোগ ব্যর্থ।', 'Team deployment failed.')) }),
  });

  const resourceMutation = useMutation({
    mutationFn: (payload) => responseApi.addResource(id, payload),
    onSuccess: () => {
      invalidate();
      setResForm({ resource_asset_id: '', requirement_title: '', quantity_needed: 1, unit: 'UNIT', priority: 'HIGH' });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('সম্পদ যুক্ত করতে ব্যর্থ।', 'Resource link failed.')) }),
  });

  const resourceStatusMutation = useMutation({
    mutationFn: ({ allocId, status }) => responseApi.updateResourceStatus(allocId, { status }),
    onSuccess: invalidate,
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('অবস্থা আপডেট ব্যর্থ।', 'Status update failed.')) }),
  });

  const reportMutation = useMutation({
    mutationFn: (payload) => responseApi.createReport(id, payload),
    onSuccess: () => {
      invalidate();
      setRepForm({
        report_type: 'INCIDENT',
        title_en: '',
        title_bn: '',
        summary: '',
        casualties_injured: 0,
        casualties_missing: 0,
        casualties_fatalities: 0,
        rescued_count: 0,
        evacuated_count: 0,
        emergency_requirements: '',
        location_description: '',
        latitude: '',
        longitude: '',
        privacy_level: 'PUBLIC_AREA',
      });
      setFeedback({ variant: 'success', message: t('পরিস্থিতি প্রতিবেদন দাখিল হয়েছে।', 'Situation report submitted.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('প্রতিবেদন দাখিল ব্যর্থ।', 'Report submission failed.')) }),
  });

  if (isLoading) return <Loader />;
  if (error) return <AlertBanner variant="danger" message={formatResponseApiError(error, t('তথ্য লোড ব্যর্থ', 'Failed to load operation'))} />;

  const op = opRes?.data;
  if (!op) return null;

  const activities = lookupsRes?.data?.activities || [];
  const availableAssets = (lookupsRes?.data?.resource_assets || []).filter((a) => a.status === 'AVAILABLE');

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            {op.operation_no} — {t(op.title_bn || op.title_en, op.title_en)} <OperationStatusBadge status={op.status} />
          </h4>
          <div className="small text-muted">
            {t(op.disaster_event?.name_bn || op.disaster_event?.name_en, op.disaster_event?.name_en)} •{' '}
            {t(op.administrative_unit?.name_bn || op.administrative_unit?.name_en, op.administrative_unit?.name_en)} •{' '}
            {op.lead_organization}
          </div>
        </div>
        <div className="d-flex gap-2">
          {canCreateOperation(user) && op.status !== 'CLOSED' && (
            <Link to={`/response/operations/${op.id}/edit`} className="btn btn-sm btn-outline-primary">
              <i className="bi bi-pencil me-1"></i>
              {t('সম্পাদনা', 'Edit')}
            </Link>
          )}
          <Link to="/response/operations" className="btn btn-sm btn-outline-secondary">
            {t('তালিকায় ফিরুন', 'Back to Operations')}
          </Link>
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {/* Lifecycle bar */}
      {canManageLifecycle(user) && op.status !== 'CLOSED' && (
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-body py-2 d-flex flex-wrap align-items-center gap-2">
            <strong className="small me-2">{t('অভিযান পর্যায়:', 'Lifecycle:')}</strong>
            <input
              type="text"
              className="form-control form-control-sm"
              style={{ width: '240px' }}
              placeholder={t('মন্তব্য (ঐচ্ছিক)...', 'Transition notes...')}
              value={lifecycleNote}
              onChange={(e) => setLifecycleNote(e.target.value)}
            />
            {['ACTIVATED', 'ONGOING', 'SUSPENDED', 'COMPLETED', 'CLOSED'].map((st) => (
              <button
                key={st}
                type="button"
                className={`btn btn-sm ${op.status === st ? 'btn-dark disabled' : 'btn-outline-primary'}`}
                disabled={op.status === st || lifecycleMutation.isPending}
                onClick={() => lifecycleMutation.mutate({ status: st })}
              >
                {st}
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Navigation Tabs */}
      <ul className="nav nav-tabs mb-3">
        {[
          { key: 'tasks', bn: 'কার্যক্রম ও টাস্ক', en: `Tasks (${(op.tasks || []).length})` },
          { key: 'coordination', bn: 'সংস্থা ও সাড়াদান দল', en: `Agencies & Teams (${(op.teams || []).length})` },
          { key: 'resources', bn: 'সম্পদ ও যানবাহন', en: `Resources (${(op.resource_allocations || []).length})` },
          { key: 'reports', bn: 'পরিস্থিতি প্রতিবেদন', en: `Situation Reports (${(op.situation_reports || []).length})` },
          { key: 'map', bn: 'জিআইএস মানচিত্র', en: 'GIS Map' },
        ].map((item) => (
          <li className="nav-item" key={item.key}>
            <button
              type="button"
              className={`nav-link ${tab === item.key ? 'active fw-bold' : ''}`}
              onClick={() => setTab(item.key)}
            >
              {t(item.bn, item.en)}
            </button>
          </li>
        ))}
      </ul>

      {/* TAB 1: TASKS */}
      {tab === 'tasks' && (
        <div className="row g-3">
          {canManageTasks(user) && (
            <div className="col-lg-4">
              <form
                className="card border-0 shadow-sm"
                onSubmit={(e) => {
                  e.preventDefault();
                  taskMutation.mutate({
                    ...taskForm,
                    response_team_id: taskForm.response_team_id || null,
                    deadline_at: taskForm.deadline_at || null,
                  });
                }}
              >
                <div className="card-header bg-white fw-bold">{t('নতুন টাস্ক বরাদ্দ করুন', 'Assign Response Task')}</div>
                <div className="card-body">
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('কার্যক্রমের ধরন *', 'Activity Type *')}</label>
                    <select className="form-select form-select-sm" required value={taskForm.activity_type_id} onChange={(e) => setTaskForm((p) => ({ ...p, activity_type_id: e.target.value }))}>
                      <option value="">{t('নির্বাচন করুন...', 'Select activity...')}</option>
                      {activities.map((a) => <option key={a.id} value={a.id}>{t(a.name_bn, a.name_en)}</option>)}
                    </select>
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('শিরোনাম (EN) *', 'Title (EN) *')}</label>
                    <input className="form-control form-control-sm" required value={taskForm.title_en} onChange={(e) => setTaskForm((p) => ({ ...p, title_en: e.target.value }))} />
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('শিরোনাম (BN)', 'Title (BN)')}</label>
                    <input className="form-control form-control-sm" value={taskForm.title_bn} onChange={(e) => setTaskForm((p) => ({ ...p, title_bn: e.target.value }))} />
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('সাড়াদান দল', 'Assigned Team')}</label>
                    <select className="form-select form-select-sm" value={taskForm.response_team_id} onChange={(e) => setTaskForm((p) => ({ ...p, response_team_id: e.target.value }))}>
                      <option value="">{t('ঐচ্ছিক দল...', 'Optional team...')}</option>
                      {(op.teams || []).map((tm) => <option key={tm.id} value={tm.id}>{tm.team_name}</option>)}
                    </select>
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('দায়িত্বপ্রাপ্ত কর্মকর্তা', 'Assigned Officer')}</label>
                    <input className="form-control form-control-sm" value={taskForm.assigned_officer_name} onChange={(e) => setTaskForm((p) => ({ ...p, assigned_officer_name: e.target.value }))} />
                  </div>
                  <div className="row g-2 mb-3">
                    <div className="col-6">
                      <label className="form-label small fw-semibold">{t('অগ্রাধিকার', 'Priority')}</label>
                      <select className="form-select form-select-sm" value={taskForm.priority} onChange={(e) => setTaskForm((p) => ({ ...p, priority: e.target.value }))}>
                        <option value="CRITICAL">CRITICAL</option>
                        <option value="HIGH">HIGH</option>
                        <option value="MEDIUM">MEDIUM</option>
                        <option value="LOW">LOW</option>
                      </select>
                    </div>
                    <div className="col-6">
                      <label className="form-label small fw-semibold">{t('সময়সীমা', 'Deadline')}</label>
                      <input type="datetime-local" className="form-control form-control-sm" value={taskForm.deadline_at} onChange={(e) => setTaskForm((p) => ({ ...p, deadline_at: e.target.value }))} />
                    </div>
                  </div>
                  <button type="submit" className="btn btn-sm btn-primary w-100" disabled={taskMutation.isPending}>
                    {t('টাস্ক তৈরি করুন', 'Assign Task')}
                  </button>
                </div>
              </form>
            </div>
          )}

          <div className={canManageTasks(user) ? 'col-lg-8' : 'col-12'}>
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-bold">{t('টাস্ক তালিকা ও প্রমাণক', 'Tasks & Evidence')}</div>
              <div className="card-body">
                {(op.tasks || []).length === 0 ? (
                  <p className="text-muted small mb-0">{t('এখনও কোন টাস্ক যুক্ত হয়নি।', 'No tasks assigned yet.')}</p>
                ) : (
                  op.tasks.map((task) => (
                    <div key={task.id} className="border rounded p-3 mb-2 bg-light">
                      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                          <strong>{task.task_code}</strong> — {t(task.title_bn || task.title_en, task.title_en)}{' '}
                          <TaskStatusBadge status={task.status} />
                          <div className="small text-muted">
                            {t(task.activity_type?.name_bn, task.activity_type?.name_en)} • {task.team?.team_name || task.assigned_officer_name || 'Unassigned'}
                          </div>
                        </div>
                        <div className="d-flex align-items-center gap-2">
                          <span className="badge bg-white text-dark border">{task.progress_percentage}%</span>
                          {canManageTasks(user) && task.status !== 'COMPLETED' && (
                            <>
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-primary"
                                onClick={() => taskProgressMutation.mutate({ taskId: task.id, payload: { status: 'IN_PROGRESS', progress_percentage: Math.min(90, task.progress_percentage + 25) } })}
                              >
                                +25%
                              </button>
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-success"
                                onClick={() => taskProgressMutation.mutate({ taskId: task.id, payload: { status: 'COMPLETED', progress_percentage: 100 } })}
                              >
                                {t('সম্পন্ন', 'Complete')}
                              </button>
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-secondary"
                                onClick={() => setAttTaskId(attTaskId === task.id ? null : task.id)}
                              >
                                <i className="bi bi-paperclip"></i>
                              </button>
                            </>
                          )}
                        </div>
                      </div>

                      {attTaskId === task.id && (
                        <div className="mt-2 d-flex gap-2 align-items-center">
                          <input type="file" className="form-control form-control-sm" onChange={(e) => setAttFile(e.target.files?.[0] || null)} />
                          <button
                            type="button"
                            className="btn btn-sm btn-dark"
                            disabled={!attFile || uploadAttachmentMutation.isPending}
                            onClick={() => uploadAttachmentMutation.mutate({ taskId: task.id, file: attFile })}
                          >
                            {t('আপলোড', 'Upload')}
                          </button>
                        </div>
                      )}

                      {(task.attachments || []).length > 0 && (
                        <div className="mt-2 d-flex flex-wrap gap-2">
                          {task.attachments.map((att) => (
                            <button
                              key={att.id}
                              type="button"
                              className="btn btn-sm btn-link p-0 text-decoration-none"
                              onClick={async () => {
                                const blob = await responseApi.downloadTaskAttachment(task.id, att.id);
                                downloadResponseBlob(blob, att.original_name);
                              }}
                            >
                              <i className="bi bi-file-earmark-arrow-down me-1"></i>{att.original_name}
                            </button>
                          ))}
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: AGENCIES & TEAMS */}
      {tab === 'coordination' && (
        <div className="row g-3">
          <div className="col-lg-6">
            <div className="card border-0 shadow-sm mb-3">
              <div className="card-header bg-white fw-bold">{t('সমন্বয়কারী সরকারি ও বেসরকারি সংস্থা', 'Coordinating Agencies')}</div>
              <div className="card-body">
                {canManageCoordination(user) && (
                  <form
                    className="row g-2 mb-3 pb-3 border-bottom"
                    onSubmit={(e) => {
                      e.preventDefault();
                      agencyMutation.mutate(agencyForm);
                    }}
                  >
                    <div className="col-md-6">
                      <input className="form-control form-control-sm" required placeholder={t('সংস্থার নাম *', 'Agency Name *')} value={agencyForm.agency_name} onChange={(e) => setAgencyForm((p) => ({ ...p, agency_name: e.target.value }))} />
                    </div>
                    <div className="col-md-6">
                      <select className="form-select form-select-sm" value={agencyForm.agency_type} onChange={(e) => setAgencyForm((p) => ({ ...p, agency_type: e.target.value }))}>
                        <option value="GOVERNMENT">GOVERNMENT</option>
                        <option value="FIRE_SERVICE">FIRE_SERVICE</option>
                        <option value="ARMED_FORCES">ARMED_FORCES</option>
                        <option value="COAST_GUARD">COAST_GUARD</option>
                        <option value="NGO">NGO</option>
                        <option value="VOLUNTEER_ORG">VOLUNTEER_ORG</option>
                      </select>
                    </div>
                    <div className="col-md-8">
                      <input className="form-control form-control-sm" placeholder={t('ভূমিকা / দায়িত্ব', 'Role summary')} value={agencyForm.role_summary} onChange={(e) => setAgencyForm((p) => ({ ...p, role_summary: e.target.value }))} />
                    </div>
                    <div className="col-md-4">
                      <button type="submit" className="btn btn-sm btn-primary w-100">{t('যুক্ত করুন', 'Add Agency')}</button>
                    </div>
                  </form>
                )}
                <ul className="list-group list-group-flush small">
                  {(op.agencies || []).map((ag) => (
                    <li key={ag.id} className="list-group-item px-0">
                      <strong>{ag.agency_name}</strong> ({ag.agency_type})
                      {ag.role_summary && <div className="text-muted">{ag.role_summary}</div>}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>

          <div className="col-lg-6">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-bold">{t('অংশগ্রহণকারী সাড়াদান দল', 'Participating Response Teams')}</div>
              <div className="card-body">
                {canManageCoordination(user) && (
                  <form
                    className="row g-2 mb-3 pb-3 border-bottom"
                    onSubmit={(e) => {
                      e.preventDefault();
                      teamMutation.mutate({
                        ...teamForm,
                        latitude: teamForm.latitude || null,
                        longitude: teamForm.longitude || null,
                      });
                    }}
                  >
                    <div className="col-md-6">
                      <input className="form-control form-control-sm" required placeholder={t('দলের নাম *', 'Team Name *')} value={teamForm.team_name} onChange={(e) => setTeamForm((p) => ({ ...p, team_name: e.target.value }))} />
                    </div>
                    <div className="col-md-6">
                      <input className="form-control form-control-sm" required placeholder={t('সংস্থা *', 'Agency *')} value={teamForm.agency_name} onChange={(e) => setTeamForm((p) => ({ ...p, agency_name: e.target.value }))} />
                    </div>
                    <div className="col-md-4">
                      <select className="form-select form-select-sm" value={teamForm.team_type} onChange={(e) => setTeamForm((p) => ({ ...p, team_type: e.target.value }))}>
                        <option value="FIRE_SERVICE">FIRE_SERVICE</option>
                        <option value="ARMED_FORCES">ARMED_FORCES</option>
                        <option value="COAST_GUARD">COAST_GUARD</option>
                        <option value="CPP_VOLUNTEERS">CPP_VOLUNTEERS</option>
                        <option value="MEDICAL_TEAM">MEDICAL_TEAM</option>
                        <option value="SAR_UNIT">SAR_UNIT</option>
                      </select>
                    </div>
                    <div className="col-md-3">
                      <input type="number" min="1" className="form-control form-control-sm" value={teamForm.member_count} onChange={(e) => setTeamForm((p) => ({ ...p, member_count: Number(e.target.value) }))} />
                    </div>
                    <div className="col-md-5">
                      <button type="submit" className="btn btn-sm btn-success w-100">{t('দল মোতায়েন করুন', 'Deploy Team')}</button>
                    </div>
                  </form>
                )}
                <ul className="list-group list-group-flush small">
                  {(op.teams || []).map((tm) => (
                    <li key={tm.id} className="list-group-item px-0 d-flex justify-content-between">
                      <div>
                        <strong>{tm.team_code}</strong> — {tm.team_name} ({tm.team_type})
                        <div className="text-muted">{tm.agency_name} • {tm.member_count} {t('সদস্য', 'members')}</div>
                      </div>
                      <span className="badge bg-success align-self-center">{tm.status}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: RESOURCES & VEHICLES */}
      {tab === 'resources' && (
        <div className="card border-0 shadow-sm">
          <div className="card-header bg-white fw-bold">{t('সম্পদ ও যানবাহন সংযোগ', 'Resource Requirements & Linked Vehicles/Assets')}</div>
          <div className="card-body">
            {canManageCoordination(user) && (
              <form
                className="row g-2 mb-3 pb-3 border-bottom"
                onSubmit={(e) => {
                  e.preventDefault();
                  resourceMutation.mutate({
                    ...resForm,
                    resource_asset_id: resForm.resource_asset_id || null,
                  });
                }}
              >
                <div className="col-md-4">
                  <select className="form-select form-select-sm" value={resForm.resource_asset_id} onChange={(e) => setResForm((p) => ({ ...p, resource_asset_id: e.target.value }))}>
                    <option value="">{t('উপলব্ধ যানবাহন/সরঞ্জাম লিংক (ঐচ্ছিক)...', 'Link available asset/vehicle (optional)...')}</option>
                    {availableAssets.map((a) => (
                      <option key={a.id} value={a.id}>{t(a.name_bn, a.name_en)} ({a.asset_code})</option>
                    ))}
                  </select>
                </div>
                <div className="col-md-4">
                  <input className="form-control form-control-sm" required placeholder={t('চাহিদা বা সম্পদের বিবরণ *', 'Requirement / Resource title *')} value={resForm.requirement_title} onChange={(e) => setResForm((p) => ({ ...p, requirement_title: e.target.value }))} />
                </div>
                <div className="col-md-2">
                  <input type="number" min="1" className="form-control form-control-sm" value={resForm.quantity_needed} onChange={(e) => setResForm((p) => ({ ...p, quantity_needed: Number(e.target.value) }))} />
                </div>
                <div className="col-md-2">
                  <button type="submit" className="btn btn-sm btn-primary w-100">{t('যুক্ত করুন', 'Save')}</button>
                </div>
              </form>
            )}
            <div className="table-responsive">
              <table className="table table-sm align-middle mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('চাহিদা / সম্পদ', 'Requirement')}</th>
                    <th>{t('সংযুক্ত যানবাহন/সরঞ্জাম', 'Linked Asset')}</th>
                    <th>{t('পরিমাণ', 'Quantity')}</th>
                    <th>{t('অবস্থা', 'Status')}</th>
                    <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(op.resource_allocations || []).map((r) => (
                    <tr key={r.id}>
                      <td className="fw-medium">{r.requirement_title}</td>
                      <td>{r.asset ? `${t(r.asset.name_bn, r.asset.name_en)} (${r.asset.asset_code})` : '—'}</td>
                      <td>{r.quantity_needed} {r.unit}</td>
                      <td><span className="badge bg-info text-dark">{r.status}</span></td>
                      <td className="text-end">
                        {canManageCoordination(user) && r.status !== 'RETURNED' && (
                          <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            onClick={() => resourceStatusMutation.mutate({ allocId: r.id, status: 'RETURNED' })}
                          >
                            {t('ফেরত চিহ্নিত করুন', 'Mark Returned')}
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: SITUATION REPORTS */}
      {tab === 'reports' && (
        <div className="row g-3">
          {canCreateReport(user) && (
            <div className="col-lg-5">
              <form
                className="card border-0 shadow-sm"
                onSubmit={(e) => {
                  e.preventDefault();
                  reportMutation.mutate({
                    ...repForm,
                    latitude: repForm.latitude || null,
                    longitude: repForm.longitude || null,
                  });
                }}
              >
                <div className="card-header bg-white fw-bold">{t('জরুরি পরিস্থিতি প্রতিবেদন (SitRep / Incident)', 'Submit Situation / Incident Report')}</div>
                <div className="card-body">
                  <div className="row g-2 mb-2">
                    <div className="col-6">
                      <label className="form-label small fw-semibold">{t('ধরন', 'Type')}</label>
                      <select className="form-select form-select-sm" value={repForm.report_type} onChange={(e) => setRepForm((p) => ({ ...p, report_type: e.target.value }))}>
                        <option value="INCIDENT">INCIDENT</option>
                        <option value="FIELD_UPDATE">FIELD_UPDATE</option>
                        <option value="SITREP">SITREP</option>
                        <option value="SOS">SOS</option>
                      </select>
                    </div>
                    <div className="col-6">
                      <label className="form-label small fw-semibold">{t('অবস্থান গোপনীয়তা', 'Location Privacy')}</label>
                      <select className="form-select form-select-sm" value={repForm.privacy_level} onChange={(e) => setRepForm((p) => ({ ...p, privacy_level: e.target.value }))}>
                        <option value="PUBLIC_AREA">{t('সাধারণ এলাকা', 'Public Area')}</option>
                        <option value="RESTRICTED_EXACT">{t('সংরক্ষিত সঠিক অবস্থান', 'Restricted Exact GPS')}</option>
                      </select>
                    </div>
                  </div>
                  <div className="mb-2">
                    <input className="form-control form-control-sm" required placeholder={t('শিরোনাম (EN) *', 'Report Title (EN) *')} value={repForm.title_en} onChange={(e) => setRepForm((p) => ({ ...p, title_en: e.target.value }))} />
                  </div>
                  <div className="mb-2">
                    <textarea className="form-control form-control-sm" required rows={2} placeholder={t('ঘটনা ও উদ্ধার অগ্রগতি সারসংক্ষেপ *', 'Summary & rescue progress *')} value={repForm.summary} onChange={(e) => setRepForm((p) => ({ ...p, summary: e.target.value }))} />
                  </div>
                  <div className="row g-2 mb-2">
                    <div className="col-4">
                      <label className="form-label small">{t('উদ্ধারকৃত', 'Rescued')}</label>
                      <input type="number" min="0" className="form-control form-control-sm" value={repForm.rescued_count} onChange={(e) => setRepForm((p) => ({ ...p, rescued_count: Number(e.target.value) }))} />
                    </div>
                    <div className="col-4">
                      <label className="form-label small">{t('আহত', 'Injured')}</label>
                      <input type="number" min="0" className="form-control form-control-sm" value={repForm.casualties_injured} onChange={(e) => setRepForm((p) => ({ ...p, casualties_injured: Number(e.target.value) }))} />
                    </div>
                    <div className="col-4">
                      <label className="form-label small">{t('নিখোঁজ', 'Missing')}</label>
                      <input type="number" min="0" className="form-control form-control-sm" value={repForm.casualties_missing} onChange={(e) => setRepForm((p) => ({ ...p, casualties_missing: Number(e.target.value) }))} />
                    </div>
                  </div>
                  <div className="row g-2 mb-2">
                    <div className="col-6">
                      <input className="form-control form-control-sm" placeholder="Lat (e.g. 22.57)" value={repForm.latitude} onChange={(e) => setRepForm((p) => ({ ...p, latitude: e.target.value }))} />
                    </div>
                    <div className="col-6">
                      <input className="form-control form-control-sm" placeholder="Lng (e.g. 89.51)" value={repForm.longitude} onChange={(e) => setRepForm((p) => ({ ...p, longitude: e.target.value }))} />
                    </div>
                  </div>
                  <button type="submit" className="btn btn-sm btn-danger w-100" disabled={reportMutation.isPending}>
                    {t('প্রতিবেদন দাখিল করুন', 'Submit Report')}
                  </button>
                </div>
              </form>
            </div>
          )}

          <div className={canCreateReport(user) ? 'col-lg-7' : 'col-12'}>
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-bold">{t('দাখিলকৃত প্রতিবেদনসমূহ', 'Filed Situation Reports')}</div>
              <div className="card-body">
                {(op.situation_reports || []).length === 0 ? (
                  <p className="text-muted small mb-0">{t('কোন প্রতিবেদন দাখিল হয়নি।', 'No situation reports submitted yet.')}</p>
                ) : (
                  op.situation_reports.map((rep) => (
                    <div key={rep.id} className="border rounded p-3 mb-2 bg-light small">
                      <div className="d-flex justify-content-between">
                        <strong>{rep.report_no} • {rep.report_type}</strong>
                        <span className="text-muted">{rep.reported_at?.slice(0, 16)}</span>
                      </div>
                      <div className="fw-semibold mt-1">{t(rep.title_bn || rep.title_en, rep.title_en)}</div>
                      <p className="mb-1">{rep.summary}</p>
                      <div className="d-flex flex-wrap gap-2">
                        <span className="badge bg-success">{t('উদ্ধার', 'Rescued')}: {rep.rescued_count}</span>
                        <span className="badge bg-warning text-dark">{t('আহত', 'Injured')}: {rep.casualties_injured}</span>
                        {rep.location_masked && <span className="badge bg-secondary">{t('অবস্থান গোপনীয়তা সক্রিয়', 'Location Masked')}</span>}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 5: GIS MAP */}
      {tab === 'map' && (
        <div className="card border-0 shadow-sm">
          <div className="card-body">
            <ResponseGisMap geojson={geoRes?.data} height="480px" />
          </div>
        </div>
      )}
    </div>
  );
};

export default ResponseOperationDetailPage;
