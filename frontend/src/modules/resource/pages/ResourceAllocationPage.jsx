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

export const ResourceAllocationPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [statusFilter, setStatusFilter] = useState('');
  const [search, setSearch] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [form, setForm] = useState(() => ({
    resource_asset_id: '',
    disaster_event_id: '',
    response_operation_id: '',
    allocation_location: '',
    assignment_date: getTodayIsoDate(),
    expected_return_date: '',
    responsible_officer_name: '',
    responsible_officer_contact: '',
    purpose: '',
  }));

  const { data: lookupsRes } = useQuery({
    queryKey: ['resource', 'lookups'],
    queryFn: () => resourceApi.getLookups(),
  });

  const { data: availableRes } = useQuery({
    queryKey: ['resource', 'assets', 'AVAILABLE_FOR_ALLOC'],
    queryFn: () => resourceApi.getAssets({ per_page: 100 }),
  });

  const { data: allocRes, isLoading, error } = useQuery({
    queryKey: ['resource', 'allocations', statusFilter, search],
    queryFn: () =>
      resourceApi.getAllocations({
        per_page: 25,
        ...(statusFilter ? { status: statusFilter } : {}),
        ...(search ? { search } : {}),
      }),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['resource'] });

  const createMutation = useMutation({
    mutationFn: (payload) => resourceApi.createAllocation(payload),
    onSuccess: () => {
      invalidate();
      setForm({
        resource_asset_id: '',
        disaster_event_id: '',
        response_operation_id: '',
        allocation_location: '',
        assignment_date: new Date().toISOString().slice(0, 10),
        expected_return_date: '',
        responsible_officer_name: '',
        responsible_officer_contact: '',
        purpose: '',
      });
      setFeedback({ variant: 'success', message: t('সম্পদ বরাদ্দ সম্পন্ন হয়েছে।', 'Resource allocated successfully.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('বরাদ্দ ব্যর্থ', 'Allocation failed')) }),
  });

  const inUseMutation = useMutation({
    mutationFn: (id) => resourceApi.markAllocationInUse(id, {}),
    onSuccess: invalidate,
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('হালনাগাদ ব্যর্থ', 'Update failed')) }),
  });

  const releaseMutation = useMutation({
    mutationFn: ({ id, condition }) => resourceApi.releaseAllocation(id, { return_condition: condition }),
    onSuccess: () => {
      invalidate();
      setFeedback({ variant: 'success', message: t('সম্পদ ফেরত গ্রহণ করা হয়েছে।', 'Resource released cleanly.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('ফেরত ব্যর্থ', 'Release failed')) }),
  });

  const allAssets = availableRes?.data || [];
  const disasters = lookupsRes?.data?.disasters || [];
  const operations = lookupsRes?.data?.response_operations || [];
  const allocations = allocRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-send-check-fill text-primary me-2"></i>
            {t('সম্পদ বরাদ্দ ও ব্যবহার ব্যবস্থাপনা', 'Resource Allocation & Utilization')}
          </h4>
          <p className="text-muted small mb-0">
            {t('দুর্যোগ ও সাড়াদান অভিযানে সম্পদ বরাদ্দ, ব্যবহার ও নিরাপদ ফেরত প্রক্রিয়া', 'Transaction-safe assignment, tracking, and release across disasters & response operations')}
          </p>
        </div>
        <Link to="/resource/dashboard" className="btn btn-sm btn-outline-secondary">{t('ড্যাশবোর্ড', 'Dashboard')}</Link>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatResourceApiError(error, t('তালিকা লোড ব্যর্থ', 'Failed to load allocations'))} />}

      <div className="row g-3">
        {canDeployResources(user) && (
          <div className="col-lg-4">
            <form
              className="card border-0 shadow-sm"
              onSubmit={(e) => {
                e.preventDefault();
                createMutation.mutate({
                  ...form,
                  disaster_event_id: form.disaster_event_id || null,
                  response_operation_id: form.response_operation_id || null,
                  expected_return_date: form.expected_return_date || null,
                });
              }}
            >
              <div className="card-header bg-white fw-bold">{t('নতুন সম্পদ বরাদ্দ', 'Allocate Available Resource')}</div>
              <div className="card-body d-grid gap-2">
                <div>
                  <label className="form-label small fw-semibold">{t('সম্পদ নির্বাচন *', 'Select Resource *')}</label>
                  <select className="form-select form-select-sm" required value={form.resource_asset_id} onChange={(e) => setForm((p) => ({ ...p, resource_asset_id: e.target.value }))}>
                    <option value="">{t('উপলব্ধ সম্পদ বাছুন...', 'Choose available resource...')}</option>
                    {allAssets.map((a) => (
                      <option key={a.id} value={a.id} disabled={a.status !== 'AVAILABLE'}>
                        {a.asset_code} — {t(a.name_bn, a.name_en)} [{a.status}]
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="form-label small fw-semibold">{t('দুর্যোগ ইভেন্ট', 'Disaster Event')}</label>
                  <select className="form-select form-select-sm" value={form.disaster_event_id} onChange={(e) => setForm((p) => ({ ...p, disaster_event_id: e.target.value }))}>
                    <option value="">{t('নির্বাচন করুন...', 'Select disaster...')}</option>
                    {disasters.map((d) => (
                      <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="form-label small fw-semibold">{t('সাড়াদান অভিযান', 'Response Operation')}</label>
                  <select className="form-select form-select-sm" value={form.response_operation_id} onChange={(e) => setForm((p) => ({ ...p, response_operation_id: e.target.value }))}>
                    <option value="">{t('নির্বাচন করুন (ঐচ্ছিক)...', 'Select operation (optional)...')}</option>
                    {operations.map((op) => (
                      <option key={op.id} value={op.id}>{op.operation_no} — {t(op.title_bn || op.title_en, op.title_en)}</option>
                    ))}
                  </select>
                  {operations.length === 0 && (
                    <small className="text-muted d-block mt-1">
                      {t('কোন সক্রিয় সাড়াদান অভিযান পাওয়া যায়নি। ', 'No active response operations found. ')}
                      <Link to="/response/operations/new">{t('নতুন অভিযান তৈরি করুন', 'Create operation')}</Link>
                    </small>
                  )}
                </div>
                <input className="form-control form-control-sm" required placeholder={t('বরাদ্দের স্থান *', 'Allocation Location *')} value={form.allocation_location} onChange={(e) => setForm((p) => ({ ...p, allocation_location: e.target.value }))} />
                <div className="row g-2">
                  <div className="col-6">
                    <label className="form-label small">{t('বরাদ্দ তারিখ *', 'Assignment Date *')}</label>
                    <input type="date" className="form-control form-control-sm" required value={form.assignment_date} onChange={(e) => setForm((p) => ({ ...p, assignment_date: e.target.value }))} />
                  </div>
                  <div className="col-6">
                    <label className="form-label small">{t('সম্ভাব্য ফেরত', 'Expected Return')}</label>
                    <input type="date" className="form-control form-control-sm" value={form.expected_return_date} onChange={(e) => setForm((p) => ({ ...p, expected_return_date: e.target.value }))} />
                  </div>
                </div>
                <input className="form-control form-control-sm" required placeholder={t('দায়িত্বপ্রাপ্ত কর্মকর্তা *', 'Responsible Officer *')} value={form.responsible_officer_name} onChange={(e) => setForm((p) => ({ ...p, responsible_officer_name: e.target.value }))} />
                <input className="form-control form-control-sm" placeholder={t('কর্মকর্তার ফোন', 'Officer Contact')} value={form.responsible_officer_contact} onChange={(e) => setForm((p) => ({ ...p, responsible_officer_contact: e.target.value }))} />
                <textarea className="form-control form-control-sm" rows={2} placeholder={t('উদ্দেশ্য ও নোট', 'Purpose')} value={form.purpose} onChange={(e) => setForm((p) => ({ ...p, purpose: e.target.value }))} />
                <button type="submit" className="btn btn-sm btn-primary" disabled={createMutation.isPending}>
                  {t('বরাদ্দ নিশ্চিত করুন', 'Allocate Resource')}
                </button>
              </div>
            </form>
          </div>
        )}

        <div className={canDeployResources(user) ? 'col-lg-8' : 'col-12'}>
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
              <span className="fw-bold">{t('বরাদ্দ তালিকা', 'Allocation Ledger')}</span>
              <div className="d-flex gap-2">
                <input className="form-control form-control-sm" style={{ width: '180px' }} placeholder={t('খুঁজুন...', 'Search...')} value={search} onChange={(e) => setSearch(e.target.value)} />
                <select className="form-select form-select-sm" style={{ width: '140px' }} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                  <option value="">{t('সকল অবস্থা', 'All')}</option>
                  <option value="ASSIGNED">ASSIGNED</option>
                  <option value="IN_USE">IN_USE</option>
                  <option value="RETURNED">RETURNED</option>
                </select>
              </div>
            </div>

            {isLoading ? (
              <div className="p-3"><Loader /></div>
            ) : allocations.length === 0 ? (
              <div className="p-3"><EmptyState title={t('কোন বরাদ্দ নেই', 'No allocations found')} /></div>
            ) : (
              <div className="table-responsive">
                <table className="table table-sm align-middle mb-0 small">
                  <thead className="table-light">
                    <tr>
                      <th>{t('নম্বর ও সম্পদ', 'Alloc & Asset')}</th>
                      <th>{t('দুর্যোগ / অভিযান', 'Disaster / Op')}</th>
                      <th>{t('স্থান ও কর্মকর্তা', 'Location & Officer')}</th>
                      <th>{t('তারিখ', 'Dates')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {allocations.map((al) => (
                      <tr key={al.id}>
                        <td>
                          <div className="fw-bold font-monospace">{al.allocation_no}</div>
                          {al.asset && (
                            <Link to={`/resource/assets/${al.asset.id}`} className="text-decoration-none">
                              {al.asset.asset_code} — {t(al.asset.name_bn, al.asset.name_en)}
                            </Link>
                          )}
                        </td>
                        <td>
                          <div>{al.disaster_event ? t(al.disaster_event.name_bn, al.disaster_event.name_en) : '—'}</div>
                          {al.response_operation && <small className="text-muted">{al.response_operation.operation_no}</small>}
                        </td>
                        <td>
                          <div>{al.allocation_location}</div>
                          <small className="text-muted">{al.responsible_officer_name || '—'}</small>
                        </td>
                        <td>{al.assignment_date} &rarr; {al.actual_return_date || al.expected_return_date || '—'}</td>
                        <td><AllocationStatusBadge status={al.status} /></td>
                        <td className="text-end">
                          {canDeployResources(user) && (al.status === 'ASSIGNED' || al.status === 'IN_USE') && (
                            <div className="btn-group btn-group-sm">
                              {al.status === 'ASSIGNED' && (
                                <button type="button" className="btn btn-outline-info" onClick={() => inUseMutation.mutate(al.id)}>
                                  {t('ব্যবহার', 'In Use')}
                                </button>
                              )}
                              <button type="button" className="btn btn-outline-success" onClick={() => releaseMutation.mutate({ id: al.id, condition: 'GOOD' })}>
                                {t('ফেরত', 'Return')}
                              </button>
                            </div>
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

export default ResourceAllocationPage;
