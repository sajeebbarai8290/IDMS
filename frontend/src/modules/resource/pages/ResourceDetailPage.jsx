import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { resourceApi } from '../../../api/resourceApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  AllocationStatusBadge,
  MaintenanceStatusBadge,
  ResourceStatusBadge,
} from '../components/ResourceStatusBadges';
import {
  canDeleteResourceAssets,
  canDeployResources,
  canWriteResourceAssets,
  formatBdt,
  formatResourceApiError,
  getTodayIsoDate,
  triggerResourceBlobDownload,
} from '../utils/resourcePermissions';

export const ResourceDetailPage = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [tab, setTab] = useState('overview');
  const [feedback, setFeedback] = useState(null);
  const [confirmRetire, setConfirmRetire] = useState(false);

  // Attachment upload
  const [attKind, setAttKind] = useState('DOCUMENT');
  const [attTitle, setAttTitle] = useState('');
  const [attFile, setAttFile] = useState(null);

  // Allocation form
  const [allocForm, setAllocForm] = useState(() => ({
    disaster_event_id: '',
    response_operation_id: '',
    allocation_location: '',
    assignment_date: getTodayIsoDate(),
    expected_return_date: '',
    responsible_officer_name: '',
    responsible_officer_contact: '',
    purpose: '',
  }));
  const [releaseCondition, setReleaseCondition] = useState('GOOD');
  const [releaseNotes, setReleaseNotes] = useState('');

  // Maintenance form
  const [mntForm, setMntForm] = useState(() => ({
    record_type: 'PREVENTIVE',
    title: '',
    scheduled_date: getTodayIsoDate(),
    next_due_date: '',
    repair_cost_bdt: 0,
    vendor_or_workshop: '',
    status: 'SCHEDULED',
    performed_by_name: '',
    inspection_findings: '',
    inspection_result: '',
  }));
  const [mntFile, setMntFile] = useState(null);

  const { data: assetRes, isLoading, error } = useQuery({
    queryKey: ['resource', 'assets', id],
    queryFn: () => resourceApi.getAsset(id),
  });

  const { data: lookupsRes } = useQuery({
    queryKey: ['resource', 'lookups'],
    queryFn: () => resourceApi.getLookups(),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['resource'] });

  const uploadMutation = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      fd.append('attachment_kind', attKind);
      if (attTitle) fd.append('title', attTitle);
      fd.append('file', attFile);
      return resourceApi.uploadAttachment(id, fd);
    },
    onSuccess: () => {
      invalidate();
      setAttTitle('');
      setAttFile(null);
      setFeedback({ variant: 'success', message: t('নথি/ছবি সফলভাবে আপলোড হয়েছে।', 'Document/photograph uploaded.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('আপলোড ব্যর্থ', 'Upload failed')) }),
  });

  const allocateMutation = useMutation({
    mutationFn: (payload) => resourceApi.createAllocation(payload),
    onSuccess: () => {
      invalidate();
      setAllocForm({
        disaster_event_id: '',
        response_operation_id: '',
        allocation_location: '',
        assignment_date: new Date().toISOString().slice(0, 10),
        expected_return_date: '',
        responsible_officer_name: '',
        responsible_officer_contact: '',
        purpose: '',
      });
      setFeedback({ variant: 'success', message: t('সম্পদ বরাদ্দ সফল হয়েছে।', 'Resource allocated successfully.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('বরাদ্দ ব্যর্থ', 'Allocation failed')) }),
  });

  const inUseMutation = useMutation({
    mutationFn: (allocId) => resourceApi.markAllocationInUse(allocId, {}),
    onSuccess: () => {
      invalidate();
      setFeedback({ variant: 'success', message: t('সম্পদ চলমান ব্যবহারে চিহ্নিত হয়েছে।', 'Resource marked IN USE.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('হালনাগাদ ব্যর্থ', 'Update failed')) }),
  });

  const releaseMutation = useMutation({
    mutationFn: (allocId) =>
      resourceApi.releaseAllocation(allocId, {
        return_condition: releaseCondition,
        return_condition_notes: releaseNotes || null,
      }),
    onSuccess: () => {
      invalidate();
      setReleaseNotes('');
      setFeedback({ variant: 'success', message: t('সম্পদ ফেরত গ্রহণ করা হয়েছে।', 'Resource released and returned.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('ফেরত ব্যর্থ', 'Release failed')) }),
  });

  const maintenanceMutation = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      Object.entries(mntForm).forEach(([k, v]) => {
        if (v !== '' && v !== null) fd.append(k, v);
      });
      if (mntFile) fd.append('attachment', mntFile);
      return resourceApi.createMaintenance(id, fd);
    },
    onSuccess: () => {
      invalidate();
      setMntForm({
        record_type: 'PREVENTIVE',
        title: '',
        scheduled_date: new Date().toISOString().slice(0, 10),
        next_due_date: '',
        repair_cost_bdt: 0,
        vendor_or_workshop: '',
        status: 'SCHEDULED',
        performed_by_name: '',
        inspection_findings: '',
        inspection_result: '',
      });
      setMntFile(null);
      setFeedback({ variant: 'success', message: t('রক্ষণাবেক্ষণ রেকর্ড সংরক্ষিত হয়েছে।', 'Maintenance record saved.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('রক্ষণাবেক্ষণ ব্যর্থ', 'Maintenance save failed')) }),
  });

  const updateMntMutation = useMutation({
    mutationFn: ({ mntId, payload }) => resourceApi.updateMaintenance(mntId, payload),
    onSuccess: () => {
      invalidate();
      setFeedback({ variant: 'success', message: t('রক্ষণাবেক্ষণ অবস্থা হালনাগাদ হয়েছে।', 'Maintenance status updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('হালনাগাদ ব্যর্থ', 'Update failed')) }),
  });

  const retireMutation = useMutation({
    mutationFn: () => resourceApi.deleteAsset(id),
    onSuccess: () => {
      invalidate();
      navigate('/resource/assets');
    },
    onError: (err) => {
      setConfirmRetire(false);
      setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('অবসর/অপসারণ ব্যর্থ', 'Retire failed')) });
    },
  });

  if (isLoading) return <Loader />;
  if (error) return <AlertBanner variant="danger" message={formatResourceApiError(error, t('সম্পদ লোড ব্যর্থ', 'Failed to load resource'))} />;

  const asset = assetRes?.data;
  if (!asset) return null;

  const disasters = lookupsRes?.data?.disasters || [];
  const operations = lookupsRes?.data?.response_operations || [];
  const isAvailable = asset.status === 'AVAILABLE' && asset.is_active;

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <div className="d-flex align-items-center gap-2">
            <span className="badge bg-dark font-monospace">{asset.asset_code}</span>
            <ResourceStatusBadge status={asset.status} />
            {asset.category && (
              <span className="badge bg-light text-dark border">{t(asset.category.name_bn, asset.category.name_en)}</span>
            )}
          </div>
          <h4 className="fw-bold mt-1 mb-0">{t(asset.name_bn, asset.name_en)}</h4>
          <small className="text-muted">
            {asset.owning_organization || 'MoDMR'} • {asset.administrative_unit ? t(asset.administrative_unit.name_bn, asset.administrative_unit.name_en) : ''}
          </small>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <Link to="/resource/assets" className="btn btn-sm btn-outline-secondary">
            {t('তালিকা', 'Back to Register')}
          </Link>
          {canWriteResourceAssets(user) && (
            <Link to={`/resource/assets/${asset.id}/edit`} className="btn btn-sm btn-outline-primary">
              <i className="bi bi-pencil me-1"></i>{t('সম্পাদনা', 'Edit')}
            </Link>
          )}
          {canDeleteResourceAssets(user) && (
            <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => setConfirmRetire(true)}>
              <i className="bi bi-archive me-1"></i>{t('অবসর / অপসারণ', 'Retire Asset')}
            </button>
          )}
        </div>
      </div>

      <ConfirmDialog
        isOpen={confirmRetire}
        title={t('সম্পদ অবসর ও অপসারণ নিশ্চিত করুন', 'Confirm Resource Retirement')}
        titleEn="Retire Resource Asset"
        message={t(
          `আপনি কি নিশ্চিতভাবে "${asset.asset_code}" সম্পদটি সক্রিয় রেজিস্টার থেকে অবসর দিতে চান?`,
          `Are you sure you want to retire and remove "${asset.asset_code}" from the active register?`
        )}
        confirmText={t('হ্যাঁ, অবসর দিন', 'Retire Asset')}
        cancelText={t('বাতিল', 'Cancel')}
        onConfirm={() => retireMutation.mutate()}
        onCancel={() => setConfirmRetire(false)}
        isLoading={retireMutation.isPending}
      />

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <ul className="nav nav-tabs mb-3">
        {[
          { key: 'overview', bn: 'বিবরণ ও নথিপত্র', en: 'Overview & Attachments' },
          { key: 'allocations', bn: `বরাদ্দ ও ব্যবহার (${(asset.allocations || []).length})`, en: `Allocations & Utilization (${(asset.allocations || []).length})` },
          { key: 'maintenance', bn: `রক্ষণাবেক্ষণ ও পরিদর্শন (${(asset.maintenances || []).length})`, en: `Maintenance & Inspections (${(asset.maintenances || []).length})` },
          { key: 'human', bn: `মানবসম্পদ ইতিহাস (${(asset.human_deployments || []).length})`, en: `HR Deployments (${(asset.human_deployments || []).length})` },
        ].map((tb) => (
          <li className="nav-item" key={tb.key}>
            <button type="button" className={`nav-link ${tab === tb.key ? 'active fw-semibold' : ''}`} onClick={() => setTab(tb.key)}>
              {t(tb.bn, tb.en)}
            </button>
          </li>
        ))}
      </ul>

      {/* TAB 1: OVERVIEW & ATTACHMENTS */}
      {tab === 'overview' && (
        <div className="row g-3">
          <div className="col-lg-7">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-bold">{t('সম্পদের বিস্তারিত তথ্য', 'Resource Profile & Specifications')}</div>
              <div className="card-body small">
                <div className="row g-2">
                  <div className="col-md-6"><strong>{t('ধরন:', 'Type:')}</strong> {asset.resource_type}</div>
                  <div className="col-md-6"><strong>{t('নিবন্ধন নম্বর:', 'Registration No:')}</strong> {asset.registration_no || '—'}</div>
                  <div className="col-md-6"><strong>{t('বর্তমান অবস্থান:', 'Current Location:')}</strong> {asset.current_location || '—'}</div>
                  <div className="col-md-6"><strong>{t('ধারণক্ষমতা:', 'Capacity:')}</strong> {asset.capacity_info || '—'}</div>
                  <div className="col-md-6"><strong>{t('দায়িত্বপ্রাপ্ত কর্মকর্তা:', 'Responsible Officer:')}</strong> {asset.responsible_officer_name || '—'}</div>
                  <div className="col-md-6"><strong>{t('যোগাযোগ:', 'Contact:')}</strong> {asset.responsible_officer_contact || '—'}</div>
                  <div className="col-md-6"><strong>{t('সংগ্রহের তারিখ:', 'Acquisition Date:')}</strong> {asset.acquisition_date || '—'}</div>
                  <div className="col-md-6"><strong>{t('সংগ্রহ মূল্য:', 'Acquisition Value:')}</strong> {asset.acquisition_value_bdt !== null ? formatBdt(asset.acquisition_value_bdt) : '—'}</div>
                  <div className="col-md-6"><strong>{t('পরবর্তী রক্ষণাবেক্ষণ:', 'Next Maintenance Due:')}</strong> {asset.next_maintenance_due_at || '—'}</div>
                  <div className="col-md-6"><strong>{t('জিপিএস:', 'Coordinates:')}</strong> {asset.latitude ? `${asset.latitude}, ${asset.longitude}` : '—'}</div>
                </div>
                {asset.description && <p className="mt-3 mb-2">{asset.description}</p>}
                {asset.specifications && Object.keys(asset.specifications).length > 0 && (
                  <pre className="bg-light p-2 rounded mt-2 mb-0">{JSON.stringify(asset.specifications, null, 2)}</pre>
                )}
              </div>
            </div>
          </div>

          <div className="col-lg-5">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-bold">{t('সহায়ক নথিপত্র ও ছবি', 'Supporting Documents & Photographs')}</div>
              <div className="card-body">
                {canWriteResourceAssets(user) && (
                  <form
                    className="row g-2 mb-3 pb-3 border-bottom"
                    onSubmit={(e) => {
                      e.preventDefault();
                      if (attFile) uploadMutation.mutate();
                    }}
                  >
                    <div className="col-5">
                      <select className="form-select form-select-sm" value={attKind} onChange={(e) => setAttKind(e.target.value)}>
                        <option value="DOCUMENT">{t('নথি (Document)', 'Document')}</option>
                        <option value="PHOTOGRAPH">{t('ছবি (Photo)', 'Photograph')}</option>
                      </select>
                    </div>
                    <div className="col-7">
                      <input className="form-control form-control-sm" placeholder={t('শিরোনাম', 'Title')} value={attTitle} onChange={(e) => setAttTitle(e.target.value)} />
                    </div>
                    <div className="col-8">
                      <input type="file" className="form-control form-control-sm" required onChange={(e) => setAttFile(e.target.files?.[0] || null)} />
                    </div>
                    <div className="col-4">
                      <button type="submit" className="btn btn-sm btn-primary w-100" disabled={uploadMutation.isPending}>
                        {t('আপলোড', 'Upload')}
                      </button>
                    </div>
                  </form>
                )}

                {(asset.attachments || []).length === 0 ? (
                  <p className="text-muted small mb-0">{t('কোন সংযুক্তি নেই।', 'No documents or photographs uploaded.')}</p>
                ) : (
                  <ul className="list-group list-group-flush small">
                    {asset.attachments.map((att) => (
                      <li key={att.id} className="list-group-item px-0 d-flex justify-content-between align-items-center">
                        <div>
                          <span className="badge bg-light text-dark border me-2">{att.attachment_kind}</span>
                          <strong>{att.title || att.original_name}</strong>
                          <div className="text-muted font-monospace" style={{ fontSize: '11px' }}>SHA-256: {att.sha256_hash?.slice(0, 16)}...</div>
                        </div>
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-secondary"
                          onClick={async () => {
                            const res = await resourceApi.downloadAttachment(asset.id, att.id);
                            triggerResourceBlobDownload(res.data, att.original_name);
                          }}
                        >
                          <i className="bi bi-download"></i>
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: ALLOCATIONS & UTILIZATION HISTORY */}
      {tab === 'allocations' && (
        <div className="row g-3">
          {canDeployResources(user) && (
            <div className="col-lg-4">
              <div className="card border-0 shadow-sm">
                <div className="card-header bg-white fw-bold">{t('সম্পদ বরাদ্দ করুন', 'Allocate Resource')}</div>
                <div className="card-body">
                  {!isAvailable ? (
                    <AlertBanner
                      variant="warning"
                      message={t(
                        `এই সম্পদটি বর্তমানে "${asset.status}" অবস্থায় রয়েছে এবং নতুন বরাদ্দের জন্য উপলব্ধ নয়।`,
                        `This resource is currently ${asset.status} and cannot be assigned until released or restored to AVAILABLE.`
                      )}
                    />
                  ) : (
                    <form
                      className="d-grid gap-2"
                      onSubmit={(e) => {
                        e.preventDefault();
                        allocateMutation.mutate({
                          ...allocForm,
                          resource_asset_id: asset.id,
                          disaster_event_id: allocForm.disaster_event_id || null,
                          response_operation_id: allocForm.response_operation_id || null,
                          expected_return_date: allocForm.expected_return_date || null,
                        });
                      }}
                    >
                      <div>
                        <label className="form-label small fw-semibold">{t('দুর্যোগ ইভেন্ট', 'Disaster Event')}</label>
                        <select className="form-select form-select-sm" value={allocForm.disaster_event_id} onChange={(e) => setAllocForm((p) => ({ ...p, disaster_event_id: e.target.value }))}>
                          <option value="">{t('নির্বাচন করুন...', 'Select disaster...')}</option>
                          {disasters.map((d) => (
                            <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="form-label small fw-semibold">{t('সাড়াদান অভিযান (ঐচ্ছিক)', 'Response Operation (Optional)')}</label>
                        <select className="form-select form-select-sm" value={allocForm.response_operation_id} onChange={(e) => setAllocForm((p) => ({ ...p, response_operation_id: e.target.value }))}>
                          <option value="">{t('নির্বাচন করুন...', 'Select operation...')}</option>
                          {operations.map((op) => (
                            <option key={op.id} value={op.id}>{op.operation_no} — {t(op.title_bn || op.title_en, op.title_en)}</option>
                          ))}
                        </select>
                        {operations.length === 0 && (
                          <small className="text-muted d-block mt-1">
                            {t('কোন সক্রিয় অভিযান নেই। ', 'No active operations. ')}
                            <Link to="/response/operations/new">{t('তৈরি করুন', 'Create one')}</Link>
                          </small>
                        )}
                      </div>
                      <div>
                        <label className="form-label small fw-semibold">{t('বরাদ্দ স্থান *', 'Allocation Location *')}</label>
                        <input className="form-control form-control-sm" required value={allocForm.allocation_location} onChange={(e) => setAllocForm((p) => ({ ...p, allocation_location: e.target.value }))} />
                      </div>
                      <div className="row g-2">
                        <div className="col-6">
                          <label className="form-label small fw-semibold">{t('বরাদ্দ তারিখ *', 'Assign Date *')}</label>
                          <input type="date" className="form-control form-control-sm" required value={allocForm.assignment_date} onChange={(e) => setAllocForm((p) => ({ ...p, assignment_date: e.target.value }))} />
                        </div>
                        <div className="col-6">
                          <label className="form-label small fw-semibold">{t('সম্ভাব্য ফেরত তারিখ', 'Expected Return')}</label>
                          <input type="date" className="form-control form-control-sm" value={allocForm.expected_return_date} onChange={(e) => setAllocForm((p) => ({ ...p, expected_return_date: e.target.value }))} />
                        </div>
                      </div>
                      <div>
                        <label className="form-label small fw-semibold">{t('দায়িত্বপ্রাপ্ত কর্মকর্তা *', 'Responsible Officer *')}</label>
                        <input className="form-control form-control-sm" required value={allocForm.responsible_officer_name} onChange={(e) => setAllocForm((p) => ({ ...p, responsible_officer_name: e.target.value }))} />
                      </div>
                      <div>
                        <label className="form-label small fw-semibold">{t('উদ্দেশ্য', 'Purpose')}</label>
                        <textarea className="form-control form-control-sm" rows={2} value={allocForm.purpose} onChange={(e) => setAllocForm((p) => ({ ...p, purpose: e.target.value }))} />
                      </div>
                      <button type="submit" className="btn btn-sm btn-primary" disabled={allocateMutation.isPending}>
                        {t('বরাদ্দ নিশ্চিত করুন', 'Confirm Allocation')}
                      </button>
                    </form>
                  )}
                </div>
              </div>
            </div>
          )}

          <div className={canDeployResources(user) ? 'col-lg-8' : 'col-12'}>
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>{t('ব্যবহার ও বরাদ্দের ইতিহাস', 'Allocation & Utilization History')}</span>
                {canDeployResources(user) && (
                  <div className="d-flex gap-2 align-items-center small">
                    <select className="form-select form-select-sm" style={{ width: '160px' }} value={releaseCondition} onChange={(e) => setReleaseCondition(e.target.value)}>
                      <option value="GOOD">{t('ফেরত: ভালো অবস্থা', 'Return: GOOD')}</option>
                      <option value="MAINTENANCE_REQUIRED">{t('ফেরত: রক্ষণাবেক্ষণ প্রয়োজন', 'Return: MAINTENANCE')}</option>
                      <option value="DAMAGED">{t('ফেরত: ক্ষতিগ্রস্ত', 'Return: DAMAGED')}</option>
                    </select>
                  </div>
                )}
              </div>
              <div className="table-responsive">
                <table className="table table-sm align-middle mb-0 small">
                  <thead className="table-light">
                    <tr>
                      <th>{t('নম্বর', 'Alloc No')}</th>
                      <th>{t('দুর্যোগ / অভিযান', 'Disaster / Operation')}</th>
                      <th>{t('স্থান ও কর্মকর্তা', 'Location & Officer')}</th>
                      <th>{t('সময়কাল', 'Period')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(asset.allocations || []).length === 0 ? (
                      <tr><td colSpan={6} className="text-center text-muted py-3">{t('কোন বরাদ্দ ইতিহাস নেই।', 'No allocations recorded yet.')}</td></tr>
                    ) : (
                      asset.allocations.map((al) => (
                        <tr key={al.id}>
                          <td className="fw-bold font-monospace">{al.allocation_no}</td>
                          <td>
                            <div>{al.disaster_event ? t(al.disaster_event.name_bn, al.disaster_event.name_en) : '—'}</div>
                            {al.response_operation && <small className="text-muted">{al.response_operation.operation_no}</small>}
                          </td>
                          <td>
                            <div>{al.allocation_location}</div>
                            <small className="text-muted">{al.responsible_officer_name || '—'}</small>
                          </td>
                          <td>
                            <div>{al.assignment_date} &rarr; {al.actual_return_date || al.expected_return_date || '—'}</div>
                          </td>
                          <td><AllocationStatusBadge status={al.status} /></td>
                          <td className="text-end">
                            {canDeployResources(user) && (al.status === 'ASSIGNED' || al.status === 'IN_USE') && (
                              <div className="btn-group btn-group-sm">
                                {al.status === 'ASSIGNED' && (
                                  <button type="button" className="btn btn-outline-info" onClick={() => inUseMutation.mutate(al.id)}>
                                    {t('ব্যবহার শুরু', 'Mark In-Use')}
                                  </button>
                                )}
                                <button type="button" className="btn btn-outline-success" onClick={() => releaseMutation.mutate(al.id)}>
                                  {t('ফেরত নিন', 'Release')}
                                </button>
                              </div>
                            )}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: MAINTENANCE & INSPECTIONS */}
      {tab === 'maintenance' && (
        <div className="row g-3">
          {canWriteResourceAssets(user) && (
            <div className="col-lg-4">
              <form
                className="card border-0 shadow-sm"
                onSubmit={(e) => {
                  e.preventDefault();
                  maintenanceMutation.mutate();
                }}
              >
                <div className="card-header bg-white fw-bold">{t('রক্ষণাবেক্ষণ / পরিদর্শন লগ', 'Schedule or Record Maintenance')}</div>
                <div className="card-body d-grid gap-2">
                  <div className="row g-2">
                    <div className="col-6">
                      <label className="form-label small fw-semibold">{t('ধরন', 'Type')}</label>
                      <select className="form-select form-select-sm" value={mntForm.record_type} onChange={(e) => setMntForm((p) => ({ ...p, record_type: e.target.value }))}>
                        <option value="SCHEDULED">SCHEDULED</option>
                        <option value="PREVENTIVE">PREVENTIVE</option>
                        <option value="CORRECTIVE">CORRECTIVE</option>
                        <option value="INSPECTION">INSPECTION</option>
                        <option value="REPAIR">REPAIR</option>
                      </select>
                    </div>
                    <div className="col-6">
                      <label className="form-label small fw-semibold">{t('অবস্থা', 'Status')}</label>
                      <select className="form-select form-select-sm" value={mntForm.status} onChange={(e) => setMntForm((p) => ({ ...p, status: e.target.value }))}>
                        <option value="SCHEDULED">SCHEDULED</option>
                        <option value="IN_PROGRESS">IN_PROGRESS</option>
                        <option value="COMPLETED">COMPLETED</option>
                      </select>
                    </div>
                  </div>
                  <input className="form-control form-control-sm" required placeholder={t('শিরোনাম *', 'Maintenance / Inspection Title *')} value={mntForm.title} onChange={(e) => setMntForm((p) => ({ ...p, title: e.target.value }))} />
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
                  <div className="row g-2">
                    <div className="col-6">
                      <label className="form-label small">{t('মেরামত খরচ (টাকা)', 'Repair Cost (BDT)')}</label>
                      <input type="number" min="0" className="form-control form-control-sm" value={mntForm.repair_cost_bdt} onChange={(e) => setMntForm((p) => ({ ...p, repair_cost_bdt: Number(e.target.value) }))} />
                    </div>
                    <div className="col-6">
                      <label className="form-label small">{t('পরিদর্শন ফলাফল', 'Inspection Result')}</label>
                      <select className="form-select form-select-sm" value={mntForm.inspection_result} onChange={(e) => setMntForm((p) => ({ ...p, inspection_result: e.target.value }))}>
                        <option value="">—</option>
                        <option value="PASS">PASS</option>
                        <option value="CONDITIONAL">CONDITIONAL</option>
                        <option value="FAIL">FAIL</option>
                      </select>
                    </div>
                  </div>
                  <input className="form-control form-control-sm" placeholder={t('ওয়ার্কশপ / ভেন্ডর', 'Vendor / Workshop')} value={mntForm.vendor_or_workshop} onChange={(e) => setMntForm((p) => ({ ...p, vendor_or_workshop: e.target.value }))} />
                  <textarea className="form-control form-control-sm" rows={2} placeholder={t('পরিদর্শন পর্যবেক্ষণ', 'Inspection findings')} value={mntForm.inspection_findings} onChange={(e) => setMntForm((p) => ({ ...p, inspection_findings: e.target.value }))} />
                  <div>
                    <label className="form-label small">{t('পরিদর্শন প্রতিবেদন / বিল (ঐচ্ছিক)', 'Inspection Report / Invoice')}</label>
                    <input type="file" className="form-control form-control-sm" onChange={(e) => setMntFile(e.target.files?.[0] || null)} />
                  </div>
                  <button type="submit" className="btn btn-sm btn-warning" disabled={maintenanceMutation.isPending}>
                    {t('রেকর্ড সংরক্ষণ করুন', 'Save Maintenance Record')}
                  </button>
                </div>
              </form>
            </div>
          )}

          <div className={canWriteResourceAssets(user) ? 'col-lg-8' : 'col-12'}>
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-bold">{t('রক্ষণাবেক্ষণ ও পরিদর্শন তালিকা', 'Maintenance & Inspection History')}</div>
              <div className="table-responsive">
                <table className="table table-sm align-middle mb-0 small">
                  <thead className="table-light">
                    <tr>
                      <th>{t('নম্বর', 'Ref No')}</th>
                      <th>{t('বিবরণ', 'Title & Type')}</th>
                      <th>{t('তারিখ / পরবর্তী', 'Dates')}</th>
                      <th>{t('খরচ', 'Cost')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(asset.maintenances || []).length === 0 ? (
                      <tr><td colSpan={6} className="text-center text-muted py-3">{t('কোন রক্ষণাবেক্ষণ রেকর্ড নেই।', 'No maintenance records logged.')}</td></tr>
                    ) : (
                      asset.maintenances.map((m) => (
                        <tr key={m.id}>
                          <td className="fw-bold font-monospace">{m.maintenance_no}</td>
                          <td>
                            <div className="fw-semibold">{m.title}</div>
                            <small className="text-muted">{m.record_type} {m.inspection_result ? `• ${m.inspection_result}` : ''}</small>
                          </td>
                          <td>
                            <div>{m.performed_date || m.scheduled_date || '—'}</div>
                            {m.next_due_date && <small className="text-muted">Next: {m.next_due_date}</small>}
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
                                    triggerResourceBlobDownload(res.data, m.attachment_original_name || 'report');
                                  }}
                                >
                                  <i className="bi bi-download"></i>
                                </button>
                              )}
                              {canWriteResourceAssets(user) && m.status === 'SCHEDULED' && (
                                <button
                                  type="button"
                                  className="btn btn-outline-warning"
                                  onClick={() => updateMntMutation.mutate({ mntId: m.id, payload: { status: 'IN_PROGRESS' } })}
                                >
                                  {t('শুরু', 'Start')}
                                </button>
                              )}
                              {canWriteResourceAssets(user) && (m.status === 'SCHEDULED' || m.status === 'IN_PROGRESS') && (
                                <button
                                  type="button"
                                  className="btn btn-outline-success"
                                  onClick={() => updateMntMutation.mutate({ mntId: m.id, payload: { status: 'COMPLETED', inspection_result: 'PASS' } })}
                                >
                                  {t('সম্পন্ন', 'Complete')}
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: HUMAN RESOURCE DEPLOYMENTS */}
      {tab === 'human' && (
        <div className="card border-0 shadow-sm">
          <div className="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span>{t('মানবসম্পদ মোতায়েন ইতিহাস', 'Human Resource Deployment History')}</span>
            <Link to="/resource/human-deployments" className="btn btn-sm btn-outline-primary">
              {t('মানবসম্পদ ওয়ার্কবেঞ্চ', 'Open HR Workbench')}
            </Link>
          </div>
          <div className="table-responsive">
            <table className="table table-sm align-middle mb-0 small">
              <thead className="table-light">
                <tr>
                  <th>{t('নম্বর', 'Ref No')}</th>
                  <th>{t('কর্মী / বিশেষজ্ঞ', 'Personnel')}</th>
                  <th>{t('অর্পিত দায়িত্ব', 'Assigned Duty')}</th>
                  <th>{t('সময়কাল', 'Period')}</th>
                  <th>{t('অবস্থা', 'Status')}</th>
                </tr>
              </thead>
              <tbody>
                {(asset.human_deployments || []).length === 0 ? (
                  <tr><td colSpan={5} className="text-center text-muted py-3">{t('কোন মানবসম্পদ মোতায়েন নেই।', 'No human resource deployments for this asset.')}</td></tr>
                ) : (
                  asset.human_deployments.map((h) => (
                    <tr key={h.id}>
                      <td className="fw-bold font-monospace">{h.deployment_no}</td>
                      <td>{h.personnel_name}</td>
                      <td>{h.assigned_duty}</td>
                      <td>{h.deployment_start_date} &rarr; {h.actual_return_date || h.deployment_end_date || '—'}</td>
                      <td><AllocationStatusBadge status={h.availability_status} /></td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

export default ResourceDetailPage;
