import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { resourceApi } from '../../../api/resourceApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { MaintenanceStatusBadge } from '../components/ResourceStatusBadges';
import {
  canWriteResourceAssets,
  formatBdt,
  formatResourceApiError,
  getTodayIsoDate,
  triggerResourceBlobDownload,
} from '../utils/resourcePermissions';

export const ResourceMaintenancePage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [statusFilter, setStatusFilter] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [selectedAssetId, setSelectedAssetId] = useState('');
  const [mntForm, setMntForm] = useState(() => ({
    record_type: 'SCHEDULED',
    title: '',
    scheduled_date: getTodayIsoDate(),
    next_due_date: '',
    repair_cost_bdt: 0,
    vendor_or_workshop: '',
    status: 'SCHEDULED',
    inspection_findings: '',
    inspection_result: '',
  }));

  const { data: assetsRes } = useQuery({
    queryKey: ['resource', 'assets', 'MNT_LIST'],
    queryFn: () => resourceApi.getAssets({ per_page: 100 }),
  });

  const { data: remindersRes } = useQuery({
    queryKey: ['resource', 'reminders'],
    queryFn: () => resourceApi.getReminders({ days: 30 }),
  });

  const { data: listRes, isLoading, error } = useQuery({
    queryKey: ['resource', 'maintenances', statusFilter],
    queryFn: () => resourceApi.getMaintenances({ per_page: 25, ...(statusFilter ? { status: statusFilter } : {}) }),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['resource'] });

  const createMutation = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      Object.entries(mntForm).forEach(([k, v]) => {
        if (v !== '' && v !== null) fd.append(k, v);
      });
      return resourceApi.createMaintenance(selectedAssetId, fd);
    },
    onSuccess: () => {
      invalidate();
      setMntForm({
        record_type: 'SCHEDULED',
        title: '',
        scheduled_date: new Date().toISOString().slice(0, 10),
        next_due_date: '',
        repair_cost_bdt: 0,
        vendor_or_workshop: '',
        status: 'SCHEDULED',
        inspection_findings: '',
        inspection_result: '',
      });
      setFeedback({ variant: 'success', message: t('রক্ষণাবেক্ষণ রেকর্ড সংরক্ষিত হয়েছে।', 'Maintenance log saved.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('সংরক্ষণ ব্যর্থ', 'Save failed')) }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }) => resourceApi.updateMaintenance(id, payload),
    onSuccess: invalidate,
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('হালনাগাদ ব্যর্থ', 'Update failed')) }),
  });

  const assets = assetsRes?.data || [];
  const dueAssets = remindersRes?.data?.due_assets || [];
  const records = listRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-wrench-adjustable-circle-fill text-warning me-2"></i>
            {t('রক্ষণাবেক্ষণ, পরিদর্শন ও রিমাইন্ডার', 'Maintenance Schedules, Inspections & Reminders')}
          </h4>
          <p className="text-muted small mb-0">
            {t('মেরামত খরচ, পরিদর্শন প্রতিবেদন ও আসন্ন সার্ভিস রিমাইন্ডার', 'Preventive schedules, inspection reports, repair costs, and due reminders')}
          </p>
        </div>
        <Link to="/resource/dashboard" className="btn btn-sm btn-outline-secondary">{t('ড্যাশবোর্ড', 'Dashboard')}</Link>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatResourceApiError(error, t('তালিকা লোড ব্যর্থ', 'Failed to load maintenance records'))} />}

      {dueAssets.length > 0 && (
        <div className="card border-warning mb-3 shadow-sm">
          <div className="card-header bg-warning bg-opacity-25 fw-bold small">
            <i className="bi bi-bell-fill text-danger me-1"></i>
            {t(`আগামী ৩০ দিনে বা মেয়াদোত্তীর্ণ রক্ষণাবেক্ষণ রিমাইন্ডার (${dueAssets.length})`, `Upcoming / Overdue Maintenance Reminders (${dueAssets.length})`)}
          </div>
          <div className="card-body py-2">
            <div className="d-flex flex-wrap gap-2">
              {dueAssets.map((a) => (
                <Link key={a.resource_asset_id} to={`/resource/assets/${a.resource_asset_id}`} className={`badge text-decoration-none ${a.is_overdue ? 'bg-danger' : 'bg-warning text-dark'}`}>
                  {a.asset_code} ({a.due_date})
                </Link>
              ))}
            </div>
          </div>
        </div>
      )}

      <div className="row g-3">
        {canWriteResourceAssets(user) && (
          <div className="col-lg-4">
            <form
              className="card border-0 shadow-sm"
              onSubmit={(e) => {
                e.preventDefault();
                createMutation.mutate();
              }}
            >
              <div className="card-header bg-white fw-bold">{t('নতুন রক্ষণাবেক্ষণ / পরিদর্শন', 'New Maintenance / Inspection')}</div>
              <div className="card-body d-grid gap-2">
                <select className="form-select form-select-sm" required value={selectedAssetId} onChange={(e) => setSelectedAssetId(e.target.value)}>
                  <option value="">{t('সম্পদ নির্বাচন করুন *', 'Select resource *')}</option>
                  {assets.map((a) => (
                    <option key={a.id} value={a.id}>{a.asset_code} — {t(a.name_bn, a.name_en)} [{a.status}]</option>
                  ))}
                </select>
                <div className="row g-2">
                  <div className="col-6">
                    <select className="form-select form-select-sm" value={mntForm.record_type} onChange={(e) => setMntForm((p) => ({ ...p, record_type: e.target.value }))}>
                      <option value="SCHEDULED">SCHEDULED</option>
                      <option value="PREVENTIVE">PREVENTIVE</option>
                      <option value="CORRECTIVE">CORRECTIVE</option>
                      <option value="INSPECTION">INSPECTION</option>
                      <option value="REPAIR">REPAIR</option>
                    </select>
                  </div>
                  <div className="col-6">
                    <select className="form-select form-select-sm" value={mntForm.status} onChange={(e) => setMntForm((p) => ({ ...p, status: e.target.value }))}>
                      <option value="SCHEDULED">SCHEDULED</option>
                      <option value="IN_PROGRESS">IN_PROGRESS</option>
                      <option value="COMPLETED">COMPLETED</option>
                    </select>
                  </div>
                </div>
                <input className="form-control form-control-sm" required placeholder={t('শিরোনাম *', 'Title *')} value={mntForm.title} onChange={(e) => setMntForm((p) => ({ ...p, title: e.target.value }))} />
                <div className="row g-2">
                  <div className="col-6">
                    <label className="form-label small">{t('নির্ধারিত তারিখ', 'Scheduled Date')}</label>
                    <input type="date" className="form-control form-control-sm" value={mntForm.scheduled_date} onChange={(e) => setMntForm((p) => ({ ...p, scheduled_date: e.target.value }))} />
                  </div>
                  <div className="col-6">
                    <label className="form-label small">{t('পরবর্তী তারিখ', 'Next Due Date')}</label>
                    <input type="date" className="form-control form-control-sm" value={mntForm.next_due_date} onChange={(e) => setMntForm((p) => ({ ...p, next_due_date: e.target.value }))} />
                  </div>
                </div>
                <input type="number" min="0" className="form-control form-control-sm" placeholder={t('মেরামত খরচ (টাকা)', 'Repair Cost (BDT)')} value={mntForm.repair_cost_bdt} onChange={(e) => setMntForm((p) => ({ ...p, repair_cost_bdt: Number(e.target.value) }))} />
                <input className="form-control form-control-sm" placeholder={t('ওয়ার্কশপ / ভেন্ডর', 'Vendor / Workshop')} value={mntForm.vendor_or_workshop} onChange={(e) => setMntForm((p) => ({ ...p, vendor_or_workshop: e.target.value }))} />
                <button type="submit" className="btn btn-sm btn-warning" disabled={createMutation.isPending}>
                  {t('সংরক্ষণ করুন', 'Save Record')}
                </button>
              </div>
            </form>
          </div>
        )}

        <div className={canWriteResourceAssets(user) ? 'col-lg-8' : 'col-12'}>
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white d-flex justify-content-between align-items-center">
              <span className="fw-bold">{t('রক্ষণাবেক্ষণ ও পরিদর্শন রেকর্ড', 'Maintenance & Inspection Log')}</span>
              <select className="form-select form-select-sm" style={{ width: '160px' }} value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}>
                <option value="">{t('সকল অবস্থা', 'All')}</option>
                <option value="SCHEDULED">SCHEDULED</option>
                <option value="IN_PROGRESS">IN_PROGRESS</option>
                <option value="COMPLETED">COMPLETED</option>
              </select>
            </div>
            {isLoading ? (
              <div className="p-3"><Loader /></div>
            ) : records.length === 0 ? (
              <div className="p-3"><EmptyState title={t('কোন রেকর্ড নেই', 'No maintenance records found')} /></div>
            ) : (
              <div className="table-responsive">
                <table className="table table-sm align-middle mb-0 small">
                  <thead className="table-light">
                    <tr>
                      <th>{t('নম্বর ও সম্পদ', 'Ref & Asset')}</th>
                      <th>{t('শিরোনাম ও ধরন', 'Title & Type')}</th>
                      <th>{t('তারিখ / পরবর্তী', 'Schedule')}</th>
                      <th>{t('খরচ', 'Cost')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {records.map((m) => (
                      <tr key={m.id}>
                        <td>
                          <div className="fw-bold font-monospace">{m.maintenance_no}</div>
                          {m.asset && (
                            <Link to={`/resource/assets/${m.asset.id}`} className="text-decoration-none">
                              {m.asset.asset_code}
                            </Link>
                          )}
                        </td>
                        <td>
                          <div className="fw-semibold">{m.title}</div>
                          <small className="text-muted">{m.record_type}</small>
                        </td>
                        <td>
                          <div>{m.performed_date || m.scheduled_date || '—'}</div>
                          {m.next_due_date && <small className="text-muted">Due: {m.next_due_date}</small>}
                        </td>
                        <td>{formatBdt(m.repair_cost_bdt)}</td>
                        <td><MaintenanceStatusBadge status={m.status} /></td>
                        <td className="text-end">
                          <div className="btn-group btn-group-sm">
                            {m.has_attachment && (
                              <button
                                type="button"
                                className="btn btn-outline-secondary"
                                onClick={async () => {
                                  const res = await resourceApi.downloadMaintenanceAttachment(m.id);
                                  triggerResourceBlobDownload(res.data, m.attachment_original_name || 'inspection');
                                }}
                              >
                                <i className="bi bi-download"></i>
                              </button>
                            )}
                            {canWriteResourceAssets(user) && m.status === 'SCHEDULED' && (
                              <button type="button" className="btn btn-outline-warning" onClick={() => updateMutation.mutate({ id: m.id, payload: { status: 'IN_PROGRESS' } })}>
                                {t('শুরু', 'Start')}
                              </button>
                            )}
                            {canWriteResourceAssets(user) && (m.status === 'SCHEDULED' || m.status === 'IN_PROGRESS') && (
                              <button type="button" className="btn btn-outline-success" onClick={() => updateMutation.mutate({ id: m.id, payload: { status: 'COMPLETED', inspection_result: 'PASS' } })}>
                                {t('সম্পন্ন', 'Complete')}
                              </button>
                            )}
                          </div>
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

export default ResourceMaintenancePage;
