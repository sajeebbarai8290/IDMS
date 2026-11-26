import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { projectApi } from '../../../api/projectApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ProjectGisMap from '../components/ProjectGisMap';
import {
  DisbursementStatusBadge,
  MilestoneApprovalBadge,
  ProjectStatusBadge,
  RiskSeverityBadge,
} from '../components/ProjectStatusBadges';
import {
  canApproveDisbursement,
  canApproveProject,
  canCertifyMilestone,
  canCreateDisbursement,
  canCreateMonitoring,
  canExecuteProjectLifecycle,
  canUpdateMilestone,
  canUpdateProject,
  formatBdt,
  formatProjectApiError,
  triggerBlobDownload,
} from '../utils/projectPermissions';

const getTodayIso = () => new Date().toISOString().slice(0, 10);

export const ProjectDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [activeTab, setActiveTab] = useState('overview');
  const [feedback, setFeedback] = useState(null);
  const [lifecycleNote, setLifecycleNote] = useState('');
  const [delayReason, setDelayReason] = useState('');
  const [milestoneEdits, setMilestoneEdits] = useState({});

  // Milestone form
  const [mForm, setMForm] = useState({
    wbs_code: '',
    milestone_title: '',
    milestone_title_bn: '',
    item_type: 'MILESTONE',
    deliverables: '',
    responsible_organization: '',
    assigned_officer_name: '',
    planned_start_date: '',
    planned_end_date: '',
    weight_percentage: 20,
  });

  // Financial form
  const [fForm, setFForm] = useState({
    record_type: 'EXPENDITURE',
    project_milestone_id: '',
    fiscal_year: '2025-2026',
    amount_bdt: '',
    description: '',
  });
  const [fDoc, setFDoc] = useState(null);

  // Monitoring report form
  const [monForm, setMonForm] = useState(() => ({
    visit_date: getTodayIso(),
    observed_physical_pct: '',
    delay_observed: false,
    delay_days: 0,
    delay_reason: '',
    findings: '',
    recommendations: '',
    latitude: '',
    longitude: '',
  }));
  const [monFile, setMonFile] = useState(null);

  // Risk form
  const [rForm, setRForm] = useState({
    record_kind: 'RISK',
    title: '',
    severity: 'MEDIUM',
    likelihood: 'MEDIUM',
    mitigation_plan: '',
    owner_name: '',
  });

  const { data: projRes, isLoading, error } = useQuery({
    queryKey: ['project', 'scheme', id],
    queryFn: () => projectApi.getProject(id),
  });

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['project'] });

  const lifecycleMut = useMutation({
    mutationFn: (payload) => projectApi.transitionLifecycle(id, payload),
    onSuccess: () => {
      refresh();
      setLifecycleNote('');
      setDelayReason('');
      setFeedback({ variant: 'success', message: t('প্রকল্পের অবস্থা হালনাগাদ হয়েছে।', 'Project lifecycle transitioned.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Transition failed.') }),
  });

  const deleteMut = useMutation({
    mutationFn: () => projectApi.deleteProject(id),
    onSuccess: () => {
      refresh();
      navigate('/project/schemes');
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Delete failed.') }),
  });

  const milestoneCreateMut = useMutation({
    mutationFn: (payload) => projectApi.createMilestone(id, payload),
    onSuccess: () => {
      refresh();
      setMForm({
        wbs_code: '',
        milestone_title: '',
        milestone_title_bn: '',
        item_type: 'MILESTONE',
        deliverables: '',
        responsible_organization: '',
        assigned_officer_name: '',
        planned_start_date: '',
        planned_end_date: '',
        weight_percentage: 20,
      });
      setFeedback({ variant: 'success', message: t('WBS কার্যক্রম / মাইলস্টোন যোগ হয়েছে।', 'WBS milestone added.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Failed to add milestone.') }),
  });

  const milestoneProgMut = useMutation({
    mutationFn: ({ milestoneId, pct, delay_reason }) =>
      projectApi.updateMilestoneProgress(milestoneId, {
        completion_percentage: pct,
        ...(delay_reason ? { delay_reason } : {}),
      }),
    onSuccess: () => {
      refresh();
      setFeedback({ variant: 'success', message: t('মাইলস্টোন অগ্রগতি হালনাগাদ হয়েছে।', 'Milestone progress updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Progress update failed.') }),
  });

  const boundaryMut = useMutation({
    mutationFn: (payload) => projectApi.uploadBoundary(id, payload),
    onSuccess: () => {
      refresh();
      setFeedback({ variant: 'success', message: t('জিআইএস সীমানা হালনাগাদ হয়েছে।', 'GIS boundary updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Boundary upload failed.') }),
  });

  const milestoneCertMut = useMutation({
    mutationFn: ({ milestoneId, approval_status }) => projectApi.certifyMilestone(milestoneId, { approval_status }),
    onSuccess: () => {
      refresh();
      setFeedback({ variant: 'success', message: t('মাইলস্টোন প্রত্যয়ন সম্পন্ন হয়েছে।', 'Milestone certified.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Certification failed.') }),
  });

  const finCreateMut = useMutation({
    mutationFn: (formData) => projectApi.recordFinancial(id, formData),
    onSuccess: () => {
      refresh();
      setFForm({ record_type: 'EXPENDITURE', project_milestone_id: '', fiscal_year: '2025-2026', amount_bdt: '', description: '' });
      setFDoc(null);
      setFeedback({ variant: 'success', message: t('আর্থিক এন্ট্রি সংরক্ষিত হয়েছে।', 'Financial entry recorded.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Financial recording failed.') }),
  });

  const finApproveMut = useMutation({
    mutationFn: ({ disbursementId, payment_status }) => projectApi.approveFinancial(disbursementId, { payment_status }),
    onSuccess: () => {
      refresh();
      setFeedback({ variant: 'success', message: t('আর্থিক ভাউচার অনুমোদিত হয়েছে।', 'Financial voucher updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Financial approval failed.') }),
  });

  const monCreateMut = useMutation({
    mutationFn: (formData) => projectApi.createMonitoringReport(id, formData),
    onSuccess: () => {
      refresh();
      setMonForm({
        visit_date: getTodayIso(),
        observed_physical_pct: '',
        delay_observed: false,
        delay_days: 0,
        delay_reason: '',
        findings: '',
        recommendations: '',
        latitude: '',
        longitude: '',
      });
      setMonFile(null);
      setFeedback({ variant: 'success', message: t('পরিদর্শন প্রতিবেদন দাখিল হয়েছে।', 'Monitoring visit report submitted.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Monitoring submission failed.') }),
  });

  const riskCreateMut = useMutation({
    mutationFn: (payload) => projectApi.createRisk(id, payload),
    onSuccess: () => {
      refresh();
      setRForm({ record_kind: 'RISK', title: '', severity: 'MEDIUM', likelihood: 'MEDIUM', mitigation_plan: '', owner_name: '' });
      setFeedback({ variant: 'success', message: t('ঝুঁকি / সমস্যা যোগ হয়েছে।', 'Risk/Issue added.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Risk creation failed.') }),
  });

  const riskUpdateMut = useMutation({
    mutationFn: ({ riskId, status }) => projectApi.updateRisk(riskId, { status }),
    onSuccess: () => {
      refresh();
      setFeedback({ variant: 'success', message: t('ঝুঁকির অবস্থা হালনাগাদ হয়েছে।', 'Risk status updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Risk update failed.') }),
  });

  const downloadBlob = async (fetcher, filename) => {
    try {
      const res = await fetcher();
      triggerBlobDownload(res.data, filename);
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatProjectApiError(err, 'Download failed.') });
    }
  };

  if (isLoading) return <Loader />;
  if (error) return <AlertBanner variant="danger" message={formatProjectApiError(error, 'Failed to load project.')} />;

  const p = projRes?.data;
  if (!p) return null;

  const isClosed = p.status === 'CLOSED';
  const projectGeojson = {
    type: 'FeatureCollection',
    features: [
      ...(p.boundary_geojson
        ? [{ type: 'Feature', geometry: p.boundary_geojson, properties: { id: p.id, project_code: p.project_code, title_en: p.title_en, title_bn: p.title_bn, status: p.status, physical_progress_pct: p.physical_progress_pct } }]
        : []),
      ...(p.latitude && p.longitude
        ? [{ type: 'Feature', geometry: { type: 'Point', coordinates: [p.longitude, p.latitude] }, properties: { id: p.id, project_code: p.project_code, title_en: p.title_en, title_bn: p.title_bn, status: p.status, physical_progress_pct: p.physical_progress_pct } }]
        : []),
      ...(p.locations || [])
        .filter((l) => l.latitude && l.longitude)
        .map((l) => ({
          type: 'Feature',
          geometry: { type: 'Point', coordinates: [l.longitude, l.latitude] },
          properties: { id: p.id, feature_type: 'project_location', project_code: p.project_code, title_en: l.site_name_en || p.title_en, title_bn: l.site_name_bn || p.title_bn, status: p.status },
        })),
    ],
  };

  return (
    <div className="container-fluid py-3">
      {/* Header */}
      <div className="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
          <div className="d-flex align-items-center gap-2 mb-1">
            <span className="badge bg-light text-dark border">{p.project_code}</span>
            <ProjectStatusBadge status={p.status} />
            {p.is_delayed && <span className="badge bg-danger">{t('বিলম্বিত', 'Delayed')} ({p.delay_days}d)</span>}
          </div>
          <h4 className="fw-bold mb-1">{t(p.title_bn, p.title_en)}</h4>
          <p className="text-muted small mb-0">
            {p.category ? t(p.category.name_bn, p.category.name_en) : ''} • {p.administrative_unit ? t(p.administrative_unit.name_bn, p.administrative_unit.name_en) : ''} • {p.implementing_organization}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <Link to="/project/schemes" className="btn btn-sm btn-outline-secondary">
            {t('তালিকা', 'Projects')}
          </Link>
          {!isClosed && canUpdateProject(user) && (
            <Link to={`/project/schemes/${id}/edit`} className="btn btn-sm btn-outline-primary">
              <i className="bi bi-pencil me-1"></i>
              {t('সম্পাদনা', 'Edit')}
            </Link>
          )}
          {['PROPOSED', 'UNDER_REVIEW'].includes(p.status) && canUpdateProject(user) && (
            <button
              type="button"
              className="btn btn-sm btn-outline-danger"
              onClick={() => deleteMut.mutate()}
              disabled={deleteMut.isPending}
            >
              {t('মুছে ফেলুন', 'Delete Draft')}
            </button>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {/* Summary Strip */}
      <div className="row g-2 mb-3">
        <div className="col-6 col-md-3">
          <div className="card border-0 shadow-sm p-2">
            <small className="text-muted">{t('কার্যকর বাজেট', 'Effective Budget')}</small>
            <div className="fw-bold text-primary">{formatBdt(p.effective_budget_bdt)}</div>
          </div>
        </div>
        <div className="col-6 col-md-3">
          <div className="card border-0 shadow-sm p-2">
            <small className="text-muted">{t('ব্যয় / অবশিষ্ট', 'Spent / Remaining')}</small>
            <div className="fw-bold text-dark">
              {formatBdt(p.expenditure_bdt)} <small className="text-muted">/ {formatBdt(p.remaining_balance_bdt)}</small>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-3">
          <div className="card border-0 shadow-sm p-2">
            <small className="text-muted">{t('ভৌত অগ্রগতি', 'Physical Progress')}</small>
            <div className="fw-bold text-success">{p.physical_progress_pct}%</div>
          </div>
        </div>
        <div className="col-6 col-md-3">
          <div className="card border-0 shadow-sm p-2">
            <small className="text-muted">{t('আর্থিক অগ্রগতি', 'Financial Progress')}</small>
            <div className="fw-bold text-info">{p.financial_progress_pct}%</div>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <ul className="nav nav-tabs mb-3">
        {[
          { key: 'overview', bn: 'সারসংক্ষেপ ও অবস্থান', en: 'Overview & GIS' },
          { key: 'wbs', bn: `কর্মপরিকল্পনা ও মাইলস্টোন (${p.milestones?.length || 0})`, en: `WBS & Milestones (${p.milestones?.length || 0})` },
          { key: 'financials', bn: `আর্থিক ব্যবস্থাপনা (${p.disbursements?.length || 0})`, en: `Financials (${p.disbursements?.length || 0})` },
          { key: 'monitoring', bn: `পরিদর্শন ও ঝুঁকি (${(p.monitoring_reports?.length || 0) + (p.risks?.length || 0)})`, en: `Monitoring & Risks (${(p.monitoring_reports?.length || 0) + (p.risks?.length || 0)})` },
        ].map((tab) => (
          <li className="nav-item" key={tab.key}>
            <button
              type="button"
              className={`nav-link ${activeTab === tab.key ? 'active fw-semibold' : ''}`}
              onClick={() => setActiveTab(tab.key)}
            >
              {t(tab.bn, tab.en)}
            </button>
          </li>
        ))}
      </ul>

      {/* TAB 1: Overview, Lifecycle & GIS */}
      {activeTab === 'overview' && (
        <div className="row g-3">
          <div className="col-lg-7">
            <div className="card border-0 shadow-sm mb-3">
              <div className="card-header bg-white fw-semibold">{t('প্রকল্পের বিবরণ', 'Project Profile')}</div>
              <div className="card-body">
                <dl className="row mb-0 small">
                  <dt className="col-sm-4">{t('স্মারক নং', 'Reference No')}</dt>
                  <dd className="col-sm-8">{p.reference_no || '-'}</dd>
                  <dt className="col-sm-4">{t('তহবিল উৎস', 'Funding Source')}</dt>
                  <dd className="col-sm-8">{p.funding_source}</dd>
                  <dt className="col-sm-4">{t('প্রকল্প ব্যবস্থাপক', 'Project Manager')}</dt>
                  <dd className="col-sm-8">{p.project_manager_name} {p.project_manager_contact ? `(${p.project_manager_contact})` : ''}</dd>
                  <dt className="col-sm-4">{t('সময়কাল', 'Timeline')}</dt>
                  <dd className="col-sm-8">{p.start_date} &rarr; {p.expected_end_date} {p.actual_end_date ? `(${t('সমাপ্ত:', 'Actual:')} ${p.actual_end_date})` : ''}</dd>
                  <dt className="col-sm-4">{t('বিবরণ', 'Description')}</dt>
                  <dd className="col-sm-8">{p.description || '-'}</dd>
                  <dt className="col-sm-4">{t('উদ্দেশ্য', 'Objectives')}</dt>
                  <dd className="col-sm-8">{p.objectives || '-'}</dd>
                </dl>
              </div>
            </div>

            {!isClosed && (
              <div className="card border-0 shadow-sm mb-3">
                <div className="card-header bg-white fw-semibold">{t('প্রকল্প জীবনচক্র নিয়ন্ত্রণ', 'Project Lifecycle Transitions')}</div>
                <div className="card-body">
                  <div className="row g-2 mb-2">
                    <div className="col-md-6">
                      <input
                        className="form-control form-control-sm"
                        placeholder={t('মন্তব্য / অনুমোদন নোট...', 'Transition notes...')}
                        value={lifecycleNote}
                        onChange={(e) => setLifecycleNote(e.target.value)}
                      />
                    </div>
                    <div className="col-md-6">
                      <input
                        className="form-control form-control-sm"
                        placeholder={t('বিলম্ব/স্থগিতের কারণ (প্রয়োজনে)', 'Suspension/delay reason (if applicable)')}
                        value={delayReason}
                        onChange={(e) => setDelayReason(e.target.value)}
                      />
                    </div>
                  </div>
                  <div className="d-flex flex-wrap gap-2">
                    {p.status === 'PROPOSED' && canUpdateProject(user) && (
                      <button type="button" className="btn btn-sm btn-info text-white" onClick={() => lifecycleMut.mutate({ status: 'UNDER_REVIEW', notes: lifecycleNote })}>
                        {t('পর্যালোচনায় পাঠান', 'Submit for Review')}
                      </button>
                    )}
                    {p.status === 'UNDER_REVIEW' && canApproveProject(user) && (
                      <>
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => lifecycleMut.mutate({ status: 'APPROVED', notes: lifecycleNote })}>
                          {t('অনুমোদন করুন (4-Eye)', 'Approve Scheme')}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => lifecycleMut.mutate({ status: 'PROPOSED', notes: lifecycleNote })}>
                          {t('সংশোধনের জন্য ফেরত', 'Return to Proposed')}
                        </button>
                      </>
                    )}
                    {p.status === 'APPROVED' && canExecuteProjectLifecycle(user) && (
                      <button type="button" className="btn btn-sm btn-success" onClick={() => lifecycleMut.mutate({ status: 'ONGOING', notes: lifecycleNote })}>
                        {t('কাজ শুরু করুন (Ongoing)', 'Start Execution')}
                      </button>
                    )}
                    {p.status === 'ONGOING' && canExecuteProjectLifecycle(user) && (
                      <button type="button" className="btn btn-sm btn-success" onClick={() => lifecycleMut.mutate({ status: 'COMPLETED', notes: lifecycleNote })}>
                        {t('সম্পন্ন ঘোষণা করুন', 'Mark Completed')}
                      </button>
                    )}
                    {['APPROVED', 'ONGOING'].includes(p.status) && canApproveProject(user) && (
                      <button type="button" className="btn btn-sm btn-warning" onClick={() => lifecycleMut.mutate({ status: 'SUSPENDED', notes: lifecycleNote, delay_reason: delayReason })}>
                        {t('স্থগিত করুন', 'Suspend')}
                      </button>
                    )}
                    {p.status === 'SUSPENDED' && canApproveProject(user) && (
                      <button type="button" className="btn btn-sm btn-success" onClick={() => lifecycleMut.mutate({ status: 'ONGOING', notes: lifecycleNote })}>
                        {t('পুনরায় চালু করুন', 'Resume Ongoing')}
                      </button>
                    )}
                    {['COMPLETED', 'SUSPENDED'].includes(p.status) && canApproveProject(user) && (
                      <button type="button" className="btn btn-sm btn-dark" onClick={() => lifecycleMut.mutate({ status: 'CLOSED', notes: lifecycleNote })}>
                        {t('প্রকল্প সমাপ্ত (Close)', 'Close Project')}
                      </button>
                    )}
                  </div>
                </div>
              </div>
            )}

            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-semibold">{t('আওতাভুক্ত এলাকাসমূহ', 'Project Geographical Locations')}</div>
              <div className="card-body p-0">
                <table className="table table-sm mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('প্রশাসনিক ইউনিট', 'Unit')}</th>
                      <th>{t('ওয়ার্ড', 'Ward')}</th>
                      <th>{t('সাইট', 'Site')}</th>
                      <th>{t('স্থানাঙ্ক', 'Coordinates')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(p.locations || []).length === 0 && (
                      <tr>
                        <td colSpan="4" className="text-center text-muted py-2 small">{p.location_summary || t('প্রাথমিক অবস্থান ব্যবহৃত হচ্ছে', 'Using primary project location')}</td>
                      </tr>
                    )}
                    {(p.locations || []).map((loc) => (
                      <tr key={loc.id}>
                        <td>{loc.administrative_unit ? t(loc.administrative_unit.name_bn, loc.administrative_unit.name_en) : ''}</td>
                        <td>{loc.ward_no || '-'}</td>
                        <td>{t(loc.site_name_bn || loc.site_name_en, loc.site_name_en) || '-'}</td>
                        <td className="small">{loc.latitude && loc.longitude ? `${loc.latitude}, ${loc.longitude}` : '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div className="col-lg-5">
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-semibold">{t('জিআইএস কর্মস্থল ও সীমানা', 'Site GIS Location & Boundary')}</div>
              <div className="card-body">
                <ProjectGisMap
                  geojson={projectGeojson}
                  height="400px"
                  mode={!isClosed && canUpdateProject(user) ? 'pick' : 'explore'}
                  pickerLat={p.latitude}
                  pickerLng={p.longitude}
                  pickerBoundary={p.boundary_geojson}
                  onBoundaryParsed={(geojson) => boundaryMut.mutate({ boundary_geojson: geojson })}
                />
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: WBS & Milestones */}
      {activeTab === 'wbs' && (
        <div className="row g-3">
          {!isClosed && canUpdateMilestone(user) && (
            <div className="col-lg-4">
              <form
                className="card border-0 shadow-sm"
                onSubmit={(e) => {
                  e.preventDefault();
                  milestoneCreateMut.mutate({
                    ...mForm,
                    weight_percentage: Number(mForm.weight_percentage),
                    planned_start_date: mForm.planned_start_date || null,
                    planned_end_date: mForm.planned_end_date || null,
                  });
                }}
              >
                <div className="card-header bg-white fw-semibold">{t('নতুন WBS কার্যক্রম / মাইলস্টোন', 'Add WBS Activity / Milestone')}</div>
                <div className="card-body d-flex flex-column gap-2">
                  <div className="row g-2">
                    <div className="col-4">
                      <input className="form-control form-control-sm" placeholder="WBS *" required value={mForm.wbs_code} onChange={(e) => setMForm((p0) => ({ ...p0, wbs_code: e.target.value }))} />
                    </div>
                    <div className="col-4">
                      <select className="form-select form-select-sm" value={mForm.item_type} onChange={(e) => setMForm((p0) => ({ ...p0, item_type: e.target.value }))}>
                        <option value="PHASE">PHASE</option>
                        <option value="ACTIVITY">ACTIVITY</option>
                        <option value="MILESTONE">MILESTONE</option>
                      </select>
                    </div>
                    <div className="col-4">
                      <input type="number" step="0.5" min="0.5" max="100" className="form-control form-control-sm" placeholder="Weight %" value={mForm.weight_percentage} onChange={(e) => setMForm((p0) => ({ ...p0, weight_percentage: e.target.value }))} />
                    </div>
                  </div>
                  <input className="form-control form-control-sm" placeholder={t('শিরোনাম (EN) *', 'Title (EN) *')} required value={mForm.milestone_title} onChange={(e) => setMForm((p0) => ({ ...p0, milestone_title: e.target.value }))} />
                  <input className="form-control form-control-sm" placeholder={t('শিরোনাম (BN)', 'Title (BN)')} value={mForm.milestone_title_bn} onChange={(e) => setMForm((p0) => ({ ...p0, milestone_title_bn: e.target.value }))} />
                  <input className="form-control form-control-sm" placeholder={t('ডেলিভারেবলস', 'Deliverables')} value={mForm.deliverables} onChange={(e) => setMForm((p0) => ({ ...p0, deliverables: e.target.value }))} />
                  <input className="form-control form-control-sm" placeholder={t('দায়িত্বপ্রাপ্ত কর্মকর্তা', 'Assigned Officer')} value={mForm.assigned_officer_name} onChange={(e) => setMForm((p0) => ({ ...p0, assigned_officer_name: e.target.value }))} />
                  <div className="row g-2">
                    <div className="col-6">
                      <input type="date" className="form-control form-control-sm" value={mForm.planned_start_date} onChange={(e) => setMForm((p0) => ({ ...p0, planned_start_date: e.target.value }))} />
                    </div>
                    <div className="col-6">
                      <input type="date" className="form-control form-control-sm" value={mForm.planned_end_date} onChange={(e) => setMForm((p0) => ({ ...p0, planned_end_date: e.target.value }))} />
                    </div>
                  </div>
                  <button type="submit" className="btn btn-sm btn-primary" disabled={milestoneCreateMut.isPending}>
                    {t('মাইলস্টোন যোগ করুন', 'Add WBS Item')}
                  </button>
                </div>
              </form>
            </div>
          )}

          <div className={!isClosed && canUpdateMilestone(user) ? 'col-lg-8' : 'col-12'}>
            <div className="card border-0 shadow-sm">
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>WBS</th>
                      <th>{t('কার্যক্রম ও ডেলিভারেবলস', 'Activity & Deliverables')}</th>
                      <th className="text-center">{t('ওজন', 'Weight')}</th>
                      <th className="text-center">{t('অগ্রগতি', 'Progress')}</th>
                      <th>{t('প্রত্যয়ন', 'Certification')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(p.milestones || []).length === 0 && (
                      <tr><td colSpan="6" className="text-center text-muted py-4">{t('কোনো WBS মাইলস্টোন নেই', 'No milestones recorded')}</td></tr>
                    )}
                    {(p.milestones || []).map((m) => (
                      <tr key={m.id}>
                        <td className="fw-semibold">{m.wbs_code}</td>
                        <td>
                          <div className="fw-semibold small">{t(m.milestone_title_bn || m.milestone_title, m.milestone_title)}</div>
                          <small className="text-muted">{m.item_type} {m.deliverables ? `• ${m.deliverables}` : ''}</small>
                          {m.assigned_officer_name && <div className="small text-muted">{t('কর্মকর্তা:', 'Officer:')} {m.assigned_officer_name}</div>}
                        </td>
                        <td className="text-center">{m.weight_percentage}%</td>
                        <td className="text-center fw-semibold">{m.completion_percentage}%</td>
                        <td><MilestoneApprovalBadge status={m.approval_status} /></td>
                        <td className="text-end">
                          <div className="d-inline-flex align-items-center gap-1">
                            {!isClosed && canUpdateMilestone(user) && m.approval_status !== 'APPROVED_PIO' && (
                              <>
                                <input
                                  type="number"
                                  min="0"
                                  max="100"
                                  step="1"
                                  className="form-control form-control-sm"
                                  style={{ width: '68px' }}
                                  value={milestoneEdits[m.id] ?? m.completion_percentage}
                                  onChange={(e) =>
                                    setMilestoneEdits((prev) => ({ ...prev, [m.id]: e.target.value }))
                                  }
                                />
                                <button
                                  type="button"
                                  className="btn btn-outline-primary btn-sm"
                                  onClick={() => {
                                    const pct = Math.min(100, Math.max(0, Number(milestoneEdits[m.id] ?? m.completion_percentage)));
                                    milestoneProgMut.mutate({ milestoneId: m.id, pct });
                                  }}
                                >
                                  {t('হালনাগাদ', 'Save %')}
                                </button>
                              </>
                            )}
                            {!isClosed && canCertifyMilestone(user) && m.approval_status !== 'APPROVED_PIO' && (
                              <button
                                type="button"
                                className="btn btn-outline-success btn-sm"
                                onClick={() => milestoneCertMut.mutate({ milestoneId: m.id, approval_status: 'APPROVED_PIO' })}
                              >
                                {t('প্রত্যয়ন', 'Certify')}
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 3: Financials */}
      {activeTab === 'financials' && (
        <div className="row g-3">
          {!isClosed && canCreateDisbursement(user) && (
            <div className="col-lg-4">
              <form
                className="card border-0 shadow-sm"
                onSubmit={(e) => {
                  e.preventDefault();
                  const fd = new FormData();
                  fd.append('record_type', fForm.record_type);
                  if (fForm.project_milestone_id) fd.append('project_milestone_id', fForm.project_milestone_id);
                  if (fForm.fiscal_year) fd.append('fiscal_year', fForm.fiscal_year);
                  fd.append('amount_bdt', fForm.amount_bdt);
                  if (fForm.description) fd.append('description', fForm.description);
                  if (fDoc) fd.append('supporting_doc', fDoc);
                  finCreateMut.mutate(fd);
                }}
              >
                <div className="card-header bg-white fw-semibold">{t('আর্থিক লেনদেন / বিল নিবন্ধন', 'Record Financial Entry')}</div>
                <div className="card-body d-flex flex-column gap-2">
                  <select className="form-select form-select-sm" value={fForm.record_type} onChange={(e) => setFForm((p0) => ({ ...p0, record_type: e.target.value }))}>
                    <option value="FUND_RELEASE">{t('অর্থ ছাড় (Fund Release)', 'Fund Release')}</option>
                    <option value="EXPENDITURE">{t('ব্যয় (Expenditure)', 'Expenditure')}</option>
                    <option value="BUDGET_REVISION">{t('বাজেট সংশোধন (Budget Revision)', 'Budget Revision')}</option>
                  </select>
                  <select className="form-select form-select-sm" value={fForm.project_milestone_id} onChange={(e) => setFForm((p0) => ({ ...p0, project_milestone_id: e.target.value }))}>
                    <option value="">{t('সংশ্লিষ্ট মাইলস্টোন (ঐচ্ছিক)', 'Link Milestone (Optional)')}</option>
                    {(p.milestones || []).map((m) => (
                      <option key={m.id} value={m.id}>{m.wbs_code} — {m.milestone_title}</option>
                    ))}
                  </select>
                  <input className="form-control form-control-sm" placeholder={t('অর্থবছর (যেমন ২০২৫-২০২৬)', 'Fiscal Year')} value={fForm.fiscal_year} onChange={(e) => setFForm((p0) => ({ ...p0, fiscal_year: e.target.value }))} />
                  <input type="number" step="0.01" min="0.01" className="form-control form-control-sm" placeholder={t('পরিমাণ (টাকা) *', 'Amount (BDT) *')} required value={fForm.amount_bdt} onChange={(e) => setFForm((p0) => ({ ...p0, amount_bdt: e.target.value }))} />
                  <textarea className="form-control form-control-sm" rows={2} placeholder={t('বিবরণ', 'Description')} value={fForm.description} onChange={(e) => setFForm((p0) => ({ ...p0, description: e.target.value }))} />
                  <input type="file" className="form-control form-control-sm" onChange={(e) => setFDoc(e.target.files?.[0] || null)} />
                  <button type="submit" className="btn btn-sm btn-primary" disabled={finCreateMut.isPending}>
                    {t('সংরক্ষণ করুন', 'Submit Voucher')}
                  </button>
                </div>
              </form>
            </div>
          )}

          <div className={!isClosed && canCreateDisbursement(user) ? 'col-lg-8' : 'col-12'}>
            <div className="card border-0 shadow-sm">
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('ভাউচার নং', 'Voucher')}</th>
                      <th>{t('ধরন', 'Type')}</th>
                      <th className="text-end">{t('পরিমাণ', 'Amount')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                      <th>{t('নথি', 'Document')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(p.disbursements || []).length === 0 && (
                      <tr><td colSpan="6" className="text-center text-muted py-4">{t('কোনো আর্থিক রেকর্ড নেই', 'No financial records')}</td></tr>
                    )}
                    {(p.disbursements || []).map((d) => (
                      <tr key={d.id}>
                        <td>
                          <div className="fw-semibold small">{d.voucher_no}</div>
                          <small className="text-muted">{d.description || d.fiscal_year || ''}</small>
                        </td>
                        <td><span className="badge bg-light text-dark border">{d.record_type}</span></td>
                        <td className="text-end fw-semibold">{formatBdt(d.amount_bdt)}</td>
                        <td><DisbursementStatusBadge status={d.payment_status} /></td>
                        <td>
                          {d.has_supporting_doc ? (
                            <button
                              type="button"
                              className="btn btn-sm btn-link p-0"
                              onClick={() => downloadBlob(() => projectApi.downloadFinancialDoc(d.id), d.supporting_doc_name || 'voucher.pdf')}
                            >
                              <i className="bi bi-paperclip me-1"></i>
                              {t('ডাউনলোড', 'Download')}
                            </button>
                          ) : (
                            <span className="text-muted small">-</span>
                          )}
                        </td>
                        <td className="text-end">
                          {!isClosed && d.payment_status === 'PENDING' && canApproveDisbursement(user) && (
                            <div className="d-inline-flex gap-1">
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-success"
                                onClick={() =>
                                  finApproveMut.mutate({
                                    disbursementId: d.id,
                                    payment_status: d.record_type === 'FUND_RELEASE' ? 'RELEASED' : 'RECONCILED',
                                  })
                                }
                              >
                                {t('অনুমোদন', 'Approve')}
                              </button>
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-danger"
                                onClick={() => finApproveMut.mutate({ disbursementId: d.id, payment_status: 'REJECTED' })}
                              >
                                {t('বাতিল', 'Reject')}
                              </button>
                            </div>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* TAB 4: Monitoring & Risks */}
      {activeTab === 'monitoring' && (
        <div className="row g-3">
          <div className="col-lg-6">
            {!isClosed && canCreateMonitoring(user) && (
              <form
                className="card border-0 shadow-sm mb-3"
                onSubmit={(e) => {
                  e.preventDefault();
                  const fd = new FormData();
                  fd.append('visit_date', monForm.visit_date);
                  fd.append('observed_physical_pct', monForm.observed_physical_pct);
                  fd.append('delay_observed', monForm.delay_observed ? '1' : '0');
                  if (monForm.delay_observed) {
                    fd.append('delay_days', String(monForm.delay_days || 0));
                    if (monForm.delay_reason) fd.append('delay_reason', monForm.delay_reason);
                  }
                  fd.append('findings', monForm.findings);
                  if (monForm.recommendations) fd.append('recommendations', monForm.recommendations);
                  if (monFile) fd.append('attachment', monFile);
                  monCreateMut.mutate(fd);
                }}
              >
                <div className="card-header bg-white fw-semibold">{t('নতুন পরিদর্শন প্রতিবেদন', 'Submit Monitoring Visit Report')}</div>
                <div className="card-body d-flex flex-column gap-2">
                  <div className="row g-2">
                    <div className="col-6">
                      <input type="date" className="form-control form-control-sm" required value={monForm.visit_date} onChange={(e) => setMonForm((p0) => ({ ...p0, visit_date: e.target.value }))} />
                    </div>
                    <div className="col-6">
                      <input type="number" step="0.5" min="0" max="100" className="form-control form-control-sm" placeholder={t('পর্যবেক্ষিত ভৌত % *', 'Observed Phy % *')} required value={monForm.observed_physical_pct} onChange={(e) => setMonForm((p0) => ({ ...p0, observed_physical_pct: e.target.value }))} />
                    </div>
                  </div>
                  <div className="form-check form-check-inline">
                    <input id="delayChk" type="checkbox" className="form-check-input" checked={monForm.delay_observed} onChange={(e) => setMonForm((p0) => ({ ...p0, delay_observed: e.target.checked }))} />
                    <label htmlFor="delayChk" className="form-check-label small">{t('প্রকল্পে বিলম্ব পরিলক্ষিত হয়েছে', 'Delay observed on site')}</label>
                  </div>
                  {monForm.delay_observed && (
                    <div className="row g-2">
                      <div className="col-4">
                        <input type="number" min="0" className="form-control form-control-sm" placeholder={t('বিলম্ব (দিন)', 'Delay Days')} value={monForm.delay_days} onChange={(e) => setMonForm((p0) => ({ ...p0, delay_days: e.target.value }))} />
                      </div>
                      <div className="col-8">
                        <input className="form-control form-control-sm" placeholder={t('বিলম্বের কারণ', 'Delay Reason')} value={monForm.delay_reason} onChange={(e) => setMonForm((p0) => ({ ...p0, delay_reason: e.target.value }))} />
                      </div>
                    </div>
                  )}
                  <textarea className="form-control form-control-sm" rows={2} placeholder={t('মাঠ পর্যবেক্ষণ ও তথ্য *', 'Inspection findings *')} required value={monForm.findings} onChange={(e) => setMonForm((p0) => ({ ...p0, findings: e.target.value }))} />
                  <input className="form-control form-control-sm" placeholder={t('সুপারিশমালা', 'Recommendations')} value={monForm.recommendations} onChange={(e) => setMonForm((p0) => ({ ...p0, recommendations: e.target.value }))} />
                  <input type="file" className="form-control form-control-sm" onChange={(e) => setMonFile(e.target.files?.[0] || null)} />
                  <button type="submit" className="btn btn-sm btn-primary" disabled={monCreateMut.isPending}>
                    {t('প্রতিবেদন দাখিল করুন', 'Submit Report')}
                  </button>
                </div>
              </form>
            )}

            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-semibold">{t('পরিদর্শন ইতিহাস', 'Monitoring Visit Reports')}</div>
              <div className="list-group list-group-flush">
                {(p.monitoring_reports || []).length === 0 && (
                  <div className="list-group-item text-muted small text-center py-3">{t('কোনো পরিদর্শন প্রতিবেদন নেই', 'No monitoring reports yet')}</div>
                )}
                {(p.monitoring_reports || []).map((r) => (
                  <div className="list-group-item" key={r.id}>
                    <div className="d-flex justify-content-between">
                      <strong className="small">{r.report_no} — {r.visit_date}</strong>
                      <span className="badge bg-light text-dark border">{r.observed_physical_pct}%</span>
                    </div>
                    <div className="small text-muted">{r.inspector_name}</div>
                    <div className="small mt-1">{r.findings}</div>
                    {r.has_attachment && (
                      <button
                        type="button"
                        className="btn btn-sm btn-link p-0 mt-1"
                        onClick={() => downloadBlob(() => projectApi.downloadMonitoringAttachment(r.id), r.attachment_original_name || 'inspection-file')}
                      >
                        <i className="bi bi-paperclip me-1"></i>
                        {r.attachment_original_name || t('সংযুক্তি ডাউনলোড', 'Download Evidence')}
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="col-lg-6">
            {!isClosed && canCreateMonitoring(user) && (
              <form
                className="card border-0 shadow-sm mb-3"
                onSubmit={(e) => {
                  e.preventDefault();
                  riskCreateMut.mutate(rForm);
                }}
              >
                <div className="card-header bg-white fw-semibold">{t('ঝুঁকি ও সমস্যা নিবন্ধন', 'Log Risk or Issue')}</div>
                <div className="card-body d-flex flex-column gap-2">
                  <div className="row g-2">
                    <div className="col-4">
                      <select className="form-select form-select-sm" value={rForm.record_kind} onChange={(e) => setRForm((p0) => ({ ...p0, record_kind: e.target.value }))}>
                        <option value="RISK">RISK</option>
                        <option value="ISSUE">ISSUE</option>
                      </select>
                    </div>
                    <div className="col-4">
                      <select className="form-select form-select-sm" value={rForm.severity} onChange={(e) => setRForm((p0) => ({ ...p0, severity: e.target.value }))}>
                        <option value="LOW">LOW</option>
                        <option value="MEDIUM">MEDIUM</option>
                        <option value="HIGH">HIGH</option>
                        <option value="CRITICAL">CRITICAL</option>
                      </select>
                    </div>
                    <div className="col-4">
                      <input className="form-control form-control-sm" placeholder={t('দায়িত্বশীল', 'Owner')} value={rForm.owner_name} onChange={(e) => setRForm((p0) => ({ ...p0, owner_name: e.target.value }))} />
                    </div>
                  </div>
                  <input className="form-control form-control-sm" placeholder={t('ঝুঁকি/সমস্যার শিরোনাম *', 'Title *')} required value={rForm.title} onChange={(e) => setRForm((p0) => ({ ...p0, title: e.target.value }))} />
                  <input className="form-control form-control-sm" placeholder={t('প্রশমন পরিকল্পনা', 'Mitigation Plan')} value={rForm.mitigation_plan} onChange={(e) => setRForm((p0) => ({ ...p0, mitigation_plan: e.target.value }))} />
                  <button type="submit" className="btn btn-sm btn-outline-danger" disabled={riskCreateMut.isPending}>
                    {t('ঝুঁকি যোগ করুন', 'Add Risk / Issue')}
                  </button>
                </div>
              </form>
            )}

            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white fw-semibold">{t('ঝুঁকি ও সমস্যা রেজিস্টার', 'Risks & Issues Register')}</div>
              <div className="list-group list-group-flush">
                {(p.risks || []).length === 0 && (
                  <div className="list-group-item text-muted small text-center py-3">{t('কোনো ঝুঁকি বা সমস্যা নেই', 'No risks or issues logged')}</div>
                )}
                {(p.risks || []).map((rk) => (
                  <div className="list-group-item" key={rk.id}>
                    <div className="d-flex justify-content-between align-items-center">
                      <div>
                        <span className="badge bg-light text-dark border me-1">{rk.risk_code}</span>
                        <RiskSeverityBadge severity={rk.severity} />
                      </div>
                      <span className="badge bg-secondary">{rk.status}</span>
                    </div>
                    <div className="fw-semibold small mt-1">{rk.title}</div>
                    {rk.mitigation_plan && <div className="small text-muted">{t('প্রশমন:', 'Mitigation:')} {rk.mitigation_plan}</div>}
                    {!isClosed && canCreateMonitoring(user) && rk.status !== 'RESOLVED' && (
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-success mt-1"
                        onClick={() => riskUpdateMut.mutate({ riskId: rk.id, status: 'RESOLVED' })}
                      >
                        {t('সমাধান চিহ্নিত করুন', 'Mark Resolved')}
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProjectDetailPage;
