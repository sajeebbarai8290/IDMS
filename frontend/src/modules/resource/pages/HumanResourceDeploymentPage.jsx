import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { resourceApi } from '../../../api/resourceApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { AllocationStatusBadge } from '../components/ResourceStatusBadges';
import { canDeployResources, formatResourceApiError, getTodayIsoDate } from '../utils/resourcePermissions';

export const HumanResourceDeploymentPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [statusFilter, setStatusFilter] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [form, setForm] = useState(() => ({
    resource_asset_id: '',
    user_id: '',
    response_team_id: '',
    response_operation_id: '',
    disaster_event_id: '',
    administrative_unit_id: '',
    personnel_name: '',
    personnel_designation: '',
    personnel_contact: '',
    assigned_duty: '',
    deployment_location: '',
    deployment_start_date: getTodayIsoDate(),
    deployment_end_date: '',
  }));

  const { data: lookupsRes } = useQuery({
    queryKey: ['resource', 'lookups'],
    queryFn: () => resourceApi.getLookups(),
  });

  const { data: assetsRes } = useQuery({
    queryKey: ['resource', 'assets', 'HR_POOL'],
    queryFn: () => resourceApi.getAssets({ per_page: 100 }),
  });

  const { data: listRes, isLoading, error } = useQuery({
    queryKey: ['resource', 'human-deployments', statusFilter],
    queryFn: () =>
      resourceApi.getHumanDeployments({
        per_page: 25,
        ...(statusFilter ? { availability_status: statusFilter } : {}),
      }),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['resource'] });

  const createMutation = useMutation({
    mutationFn: (payload) => resourceApi.createHumanDeployment(payload),
    onSuccess: () => {
      invalidate();
      setForm({
        resource_asset_id: '',
        user_id: '',
        response_team_id: '',
        response_operation_id: '',
        disaster_event_id: '',
        administrative_unit_id: '',
        personnel_name: '',
        personnel_designation: '',
        personnel_contact: '',
        assigned_duty: '',
        deployment_location: '',
        deployment_start_date: new Date().toISOString().slice(0, 10),
        deployment_end_date: '',
      });
      setFeedback({ variant: 'success', message: t('মানবসম্পদ মোতায়েন সম্পন্ন হয়েছে।', 'Human resource deployed successfully.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('মোতায়েন ব্যর্থ', 'Deployment failed')) }),
  });

  const releaseMutation = useMutation({
    mutationFn: (id) => resourceApi.releaseHumanDeployment(id, {}),
    onSuccess: () => {
      invalidate();
      setFeedback({ variant: 'success', message: t('মোতায়েন সমাপ্ত হয়েছে এবং কর্মী পুনরায় উপলব্ধ।', 'Deployment completed and personnel released.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('মুক্তি ব্যর্থ', 'Release failed')) }),
  });

  const hrAssets = (assetsRes?.data || []).filter((a) => a.category?.is_human_resource);
  const disasters = lookupsRes?.data?.disasters || [];
  const operations = lookupsRes?.data?.response_operations || [];
  const teams = lookupsRes?.data?.response_teams || [];
  const adminUnits = lookupsRes?.data?.administrative_units || [];
  const deployments = listRes?.data || [];

  const handleSelectHrAsset = (assetId) => {
    const selected = hrAssets.find((a) => a.id === assetId);
    setForm((p) => ({
      ...p,
      resource_asset_id: assetId,
      personnel_name: selected ? selected.responsible_officer_name || selected.name_en : p.personnel_name,
      personnel_designation: selected ? selected.resource_type : p.personnel_designation,
      personnel_contact: selected ? selected.responsible_officer_contact || '' : p.personnel_contact,
      administrative_unit_id: selected ? selected.administrative_unit_id : p.administrative_unit_id,
    }));
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-people-fill text-primary me-2"></i>
            {t('মানবসম্পদ বরাদ্দ ও মোতায়েন', 'Human Resource Allocation & Deployment')}
          </h4>
          <p className="text-muted small mb-0">
            {t('সাড়াদান অভিযানে বিশেষজ্ঞ কর্মী ও দল সংযুক্তি, দায়িত্ব ও সময়কাল ট্র্যাকিং', 'Assign specialist personnel or response teams to operations with duty & period history')}
          </p>
        </div>
        <Link to="/resource/dashboard" className="btn btn-sm btn-outline-secondary">{t('ড্যাশবোর্ড', 'Dashboard')}</Link>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatResourceApiError(error, t('তালিকা লোড ব্যর্থ', 'Failed to load deployments'))} />}

      <div className="row g-3">
        {canDeployResources(user) && (
          <div className="col-lg-4">
            <form
              className="card border-0 shadow-sm"
              onSubmit={(e) => {
                e.preventDefault();
                createMutation.mutate({
                  ...form,
                  resource_asset_id: form.resource_asset_id || null,
                  user_id: form.user_id || null,
                  response_team_id: form.response_team_id || null,
                  response_operation_id: form.response_operation_id || null,
                  disaster_event_id: form.disaster_event_id || null,
                  administrative_unit_id: form.administrative_unit_id || null,
                  deployment_end_date: form.deployment_end_date || null,
                });
              }}
            >
              <div className="card-header bg-white fw-bold">{t('কর্মী / দল মোতায়েন করুন', 'Deploy Personnel / Team')}</div>
              <div className="card-body d-grid gap-2">
                <div>
                  <label className="form-label small fw-semibold">{t('নিবন্ধিত মানবসম্পদ (ঐচ্ছিক)', 'Registered HR Specialist (Optional)')}</label>
                  <select className="form-select form-select-sm" value={form.resource_asset_id} onChange={(e) => handleSelectHrAsset(e.target.value)}>
                    <option value="">{t('নির্বাচন করুন...', 'Select registered HR asset...')}</option>
                    {hrAssets.map((a) => (
                      <option key={a.id} value={a.id} disabled={a.status !== 'AVAILABLE'}>
                        {a.asset_code} — {t(a.name_bn, a.name_en)} [{a.status}]
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="form-label small fw-semibold">{t('সাড়াদান দল (ঐচ্ছিক)', 'Response Team (Optional)')}</label>
                  <select className="form-select form-select-sm" value={form.response_team_id} onChange={(e) => setForm((p) => ({ ...p, response_team_id: e.target.value }))}>
                    <option value="">{t('নির্বাচন করুন...', 'Select team...')}</option>
                    {teams.map((tm) => (
                      <option key={tm.id} value={tm.id}>{tm.team_code} — {tm.team_name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="form-label small fw-semibold">{t('সাড়াদান অভিযান', 'Response Operation')}</label>
                  <select className="form-select form-select-sm" required={!form.disaster_event_id} value={form.response_operation_id} onChange={(e) => setForm((p) => ({ ...p, response_operation_id: e.target.value }))}>
                    <option value="">{t('অভিযান নির্বাচন করুন...', 'Select operation...')}</option>
                    {operations.map((op) => (
                      <option key={op.id} value={op.id}>{op.operation_no} — {t(op.title_bn || op.title_en, op.title_en)}</option>
                    ))}
                  </select>
                  {operations.length === 0 && (
                    <small className="text-muted d-block mt-1">
                      {t('কোন সক্রিয় অভিযান নেই। ', 'No active operations found. ')}
                      <Link to="/response/operations/new">{t('নতুন অভিযান তৈরি করুন', 'Create one')}</Link>
                      {t(' অথবা নিচে দুর্যোগ নির্বাচন করুন।', ' or select a disaster below.')}
                    </small>
                  )}
                </div>
                <div>
                  <label className="form-label small fw-semibold">{t('দুর্যোগ ইভেন্ট (অভিযান না থাকলে আবশ্যিক)', 'Disaster Event (required if no operation)')}</label>
                  <select className="form-select form-select-sm" required={!form.response_operation_id} value={form.disaster_event_id} onChange={(e) => setForm((p) => ({ ...p, disaster_event_id: e.target.value }))}>
                    <option value="">{t('নির্বাচন করুন...', 'Select disaster...')}</option>
                    {disasters.map((d) => (
                      <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)} ({d.disaster_code})</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="form-label small fw-semibold">{t('অধিক্ষেত্র', 'Administrative Unit')}</label>
                  <select className="form-select form-select-sm" value={form.administrative_unit_id} onChange={(e) => setForm((p) => ({ ...p, administrative_unit_id: e.target.value }))}>
                    <option value="">{t('স্বয়ংক্রিয় / নির্বাচন করুন...', 'Auto from operation or select...')}</option>
                    {adminUnits.map((u) => (
                      <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)}</option>
                    ))}
                  </select>
                </div>
                <input className="form-control form-control-sm" required placeholder={t('কর্মী / দলনেতার নাম *', 'Personnel / Team Lead Name *')} value={form.personnel_name} onChange={(e) => setForm((p) => ({ ...p, personnel_name: e.target.value }))} />
                <div className="row g-2">
                  <div className="col-6">
                    <input className="form-control form-control-sm" placeholder={t('পদবি', 'Designation')} value={form.personnel_designation} onChange={(e) => setForm((p) => ({ ...p, personnel_designation: e.target.value }))} />
                  </div>
                  <div className="col-6">
                    <input className="form-control form-control-sm" placeholder={t('যোগাযোগ', 'Contact')} value={form.personnel_contact} onChange={(e) => setForm((p) => ({ ...p, personnel_contact: e.target.value }))} />
                  </div>
                </div>
                <textarea className="form-control form-control-sm" required rows={2} placeholder={t('অর্পিত দায়িত্ব *', 'Assigned Duties *')} value={form.assigned_duty} onChange={(e) => setForm((p) => ({ ...p, assigned_duty: e.target.value }))} />
                <input className="form-control form-control-sm" placeholder={t('মোতায়েন স্থান', 'Deployment Location')} value={form.deployment_location} onChange={(e) => setForm((p) => ({ ...p, deployment_location: e.target.value }))} />
                <div className="row g-2">
                  <div className="col-6">
                    <label className="form-label small">{t('শুরুর তারিখ *', 'Start Date *')}</label>
                    <input type="date" className="form-control form-control-sm" required value={form.deployment_start_date} onChange={(e) => setForm((p) => ({ ...p, deployment_start_date: e.target.value }))} />
                  </div>
                  <div className="col-6">
                    <label className="form-label small">{t('সমাপ্তি তারিখ', 'End Date')}</label>
                    <input type="date" className="form-control form-control-sm" value={form.deployment_end_date} onChange={(e) => setForm((p) => ({ ...p, deployment_end_date: e.target.value }))} />
                  </div>
                </div>
                <button type="submit" className="btn btn-sm btn-primary" disabled={createMutation.isPending}>
                  {t('মোতায়েন নিশ্চিত করুন', 'Deploy Personnel')}
                </button>
              </div>
            </form>
          </div>
        )}

        <div className={canDeployResources(user) ? 'col-lg-8' : 'col-12'}>
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white d-flex justify-content-between align-items-center">
              <span className="fw-bold">{t('মানবসম্পদ মোতায়েন ইতিহাস', 'Deployment & Availability History')}</span>
              <select className="form-select form-select-sm" style={{ width: '150px' }} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                <option value="">{t('সকল অবস্থা', 'All')}</option>
                <option value="ASSIGNED">ASSIGNED</option>
                <option value="IN_USE">IN_USE</option>
                <option value="COMPLETED">COMPLETED</option>
              </select>
            </div>
            {isLoading ? (
              <div className="p-3"><Loader /></div>
            ) : deployments.length === 0 ? (
              <div className="p-3"><EmptyState title={t('কোন মোতায়েন নেই', 'No human resource deployments found')} /></div>
            ) : (
              <div className="table-responsive">
                <table className="table table-sm align-middle mb-0 small">
                  <thead className="table-light">
                    <tr>
                      <th>{t('নম্বর ও কর্মী', 'Ref & Personnel')}</th>
                      <th>{t('অভিযান ও দল', 'Operation & Team')}</th>
                      <th>{t('দায়িত্ব', 'Assigned Duty')}</th>
                      <th>{t('সময়কাল', 'Period')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {deployments.map((d) => (
                      <tr key={d.id}>
                        <td>
                          <div className="fw-bold font-monospace">{d.deployment_no}</div>
                          <div>{d.personnel_name}</div>
                          {d.personnel_designation && <small className="text-muted">{d.personnel_designation}</small>}
                        </td>
                        <td>
                          <div>{d.response_operation ? d.response_operation.operation_no : '—'}</div>
                          {d.team && <small className="text-muted">{d.team.team_name}</small>}
                        </td>
                        <td>{d.assigned_duty}</td>
                        <td>{d.deployment_start_date} &rarr; {d.actual_return_date || d.deployment_end_date || '—'}</td>
                        <td><AllocationStatusBadge status={d.availability_status} /></td>
                        <td className="text-end">
                          {canDeployResources(user) && (d.availability_status === 'ASSIGNED' || d.availability_status === 'IN_USE') && (
                            <button type="button" className="btn btn-sm btn-outline-success" onClick={() => releaseMutation.mutate(d.id)}>
                              {t('মুক্তি দিন', 'Release')}
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default HumanResourceDeploymentPage;
