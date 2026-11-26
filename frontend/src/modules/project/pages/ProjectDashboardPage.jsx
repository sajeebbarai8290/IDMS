import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { projectApi } from '../../../api/projectApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canCreateProject,
  canExportProjects,
  canManageProjectCategories,
  formatBdt,
  formatProjectApiError,
  isProjectBlackout,
  PROJECT_STATUS_META,
  triggerBlobDownload,
} from '../utils/projectPermissions';

export const ProjectDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [categoryFilter, setCategoryFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [showCatModal, setShowCatModal] = useState(false);
  const [catForm, setCatForm] = useState({ code: '', name_en: '', name_bn: '', description: '', color_hex: '#0d6efd' });
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['project', 'lookups'],
    queryFn: () => projectApi.getLookups(),
  });

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['project', 'dashboard', categoryFilter, statusFilter],
    queryFn: () =>
      projectApi.getDashboard({
        ...(categoryFilter ? { category_id: categoryFilter } : {}),
        ...(statusFilter ? { status: statusFilter } : {}),
      }),
  });

  const createCatMutation = useMutation({
    mutationFn: (payload) => projectApi.createCategory(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['project'] });
      setCatForm({ code: '', name_en: '', name_bn: '', description: '', color_hex: '#0d6efd' });
      setShowCatModal(false);
      setFeedback({ variant: 'success', message: t('প্রকল্পের ধরন সংরক্ষণ করা হয়েছে।', 'Project category added.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatProjectApiError(err, t('ধরন সংরক্ষণ ব্যর্থ।', 'Failed to create category.')) });
    },
  });

  const handleExportCsv = async () => {
    try {
      const res = await projectApi.exportCsv();
      triggerBlobDownload(res.data, 'idms-projects-export.csv');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatProjectApiError(err, t('এক্সপোর্ট ব্যর্থ।', 'Export failed.')) });
    }
  };

  if (isLoading) return <Loader />;

  const summary = dashRes?.data?.summary || {};
  const byCategory = dashRes?.data?.by_category || [];
  const byLocation = dashRes?.data?.progress_by_location || [];
  const categories = lookupsRes?.data?.categories || [];
  const statuses = lookupsRes?.data?.lifecycle_statuses || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-kanban-fill text-primary me-2"></i>
            {t('প্রকল্প ব্যবস্থাপনা ড্যাশবোর্ড (M06)', 'Project Management Dashboard (M06)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'উন্নয়ন, দুর্যোগ ঝুঁকি হ্রাস (ডিআরআর), পুনর্বাসন ও পুনরুদ্ধার প্রকল্পের আর্থিক ও ভৌত অগ্রগতি',
              'Development, DRR, rehabilitation, and recovery schemes — WBS milestones, budget & field monitoring'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <select
            className="form-select form-select-sm"
            style={{ width: '185px' }}
            value={categoryFilter}
            onChange={(e) => setCategoryFilter(e.target.value)}
          >
            <option value="">{t('সকল প্রকল্প ধরন', 'All Categories')}</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
            ))}
          </select>

          <select
            className="form-select form-select-sm"
            style={{ width: '160px' }}
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
            {statuses.map((s) => (
              <option key={s} value={s}>{t(PROJECT_STATUS_META[s]?.bn || s, PROJECT_STATUS_META[s]?.en || s)}</option>
            ))}
          </select>

          <Link to="/project/map" className="btn btn-sm btn-outline-primary">
            <i className="bi bi-map me-1"></i>
            {t('জিআইএস মানচিত্র', 'GIS Map')}
          </Link>
          <Link to="/project/reports" className="btn btn-sm btn-outline-info">
            <i className="bi bi-file-earmark-bar-graph me-1"></i>
            {t('প্রতিবেদন', 'Reports')}
          </Link>
          {canExportProjects(user) && (
            <button type="button" className="btn btn-sm btn-outline-success" onClick={handleExportCsv}>
              <i className="bi bi-download me-1"></i>
              {t('CSV এক্সপোর্ট', 'Export CSV')}
            </button>
          )}
          {canCreateProject(user) && (
            <Link to="/project/schemes/new" className="btn btn-sm btn-primary">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন প্রকল্প', 'New Project')}
            </Link>
          )}
        </div>
      </div>

      {isProjectBlackout(user) && (
        <AlertBanner
          variant="info"
          message={t(
            'সুপার অ্যাডমিন অপারেশনাল ব্ল্যাকআউট সক্রিয়: আপনি ড্যাশবোর্ড, প্রতিবেদন ও ধরন পরিচালনা করতে পারবেন, তবে প্রকল্প তৈরি/অনুমোদন/ব্যয় অনুমোদন করতে পারবেন না।',
            'Super Admin Operational Blackout active: You may view projects, export reports, and configure categories, but cannot create, approve, or mutate operational project records.'
          )}
        />
      )}

      {error && <AlertBanner variant="danger" message={formatProjectApiError(error, t('ড্যাশবোর্ড লোড ব্যর্থ', 'Failed to load dashboard'))} />}
      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {/* KPI Row 1: Counts */}
      <div className="row g-3 mb-3">
        {[
          { bn: 'মোট প্রকল্প', en: 'Total Projects', val: summary.total_projects ?? 0, icon: 'bi-folder2-open', color: 'primary', to: '/project/schemes' },
          { bn: 'সক্রিয় প্রকল্প', en: 'Active Projects', val: summary.active_projects ?? 0, icon: 'bi-play-circle-fill', color: 'success', to: '/project/schemes?status=ONGOING' },
          { bn: 'সম্পন্ন প্রকল্প', en: 'Completed Projects', val: summary.completed_projects ?? 0, icon: 'bi-check2-all', color: 'info', to: '/project/schemes?status=COMPLETED' },
          { bn: 'বিলম্বিত প্রকল্প', en: 'Delayed Projects', val: summary.delayed_projects ?? 0, icon: 'bi-exclamation-triangle-fill', color: 'danger', to: '/project/schemes?is_delayed=1' },
        ].map((card) => (
          <div className="col-6 col-lg-3" key={card.en}>
            <Link to={card.to} className="text-decoration-none">
              <div className="card border-0 shadow-sm h-100">
                <div className="card-body d-flex align-items-center justify-content-between">
                  <div>
                    <div className="text-muted small">{t(card.bn, card.en)}</div>
                    <div className={`fs-3 fw-bold text-${card.color}`}>{card.val}</div>
                  </div>
                  <i className={`bi ${card.icon} fs-2 text-${card.color} opacity-50`}></i>
                </div>
              </div>
            </Link>
          </div>
        ))}
      </div>

      {/* KPI Row 2: Financials & Overall Progress */}
      <div className="row g-3 mb-4">
        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small">{t('মোট অনুমোদিত বাজেট', 'Total Approved Budget')}</div>
              <div className="fs-5 fw-bold text-primary mt-1">{formatBdt(summary.total_approved_budget_bdt)}</div>
              <div className="small text-muted mt-1">
                {t('কার্যকর বাজেট:', 'Effective:')} {formatBdt(summary.total_effective_budget_bdt)}
              </div>
            </div>
          </div>
        </div>
        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small">{t('মোট অর্থ ছাড় ও ব্যয়', 'Released & Expenditure')}</div>
              <div className="fs-5 fw-bold text-success mt-1">{formatBdt(summary.total_expenditure_bdt)}</div>
              <div className="small text-muted mt-1">
                {t('অর্থ ছাড়:', 'Released:')} {formatBdt(summary.total_released_funds_bdt)}
              </div>
            </div>
          </div>
        </div>
        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small">{t('আর্থিক অগ্রগতি (%)', 'Financial Progress')}</div>
              <div className="fs-4 fw-bold text-info mt-1">{summary.financial_progress_pct ?? 0}%</div>
              <div className="progress mt-2" style={{ height: '8px' }}>
                <div className="progress-bar bg-info" style={{ width: `${summary.financial_progress_pct ?? 0}%` }} />
              </div>
            </div>
          </div>
        </div>
        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small">{t('গড় ভৌত অগ্রগতি (%)', 'Avg. Physical Progress')}</div>
              <div className="fs-4 fw-bold text-success mt-1">{summary.avg_physical_progress_pct ?? 0}%</div>
              <div className="progress mt-2" style={{ height: '8px' }}>
                <div className="progress-bar bg-success" style={{ width: `${summary.avg_physical_progress_pct ?? 0}%` }} />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Category & Location Progress Tables */}
      <div className="row g-3">
        <div className="col-lg-5">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white d-flex justify-content-between align-items-center">
              <span className="fw-semibold">{t('প্রকল্পের ধরন অনুযায়ী বাজেট', 'Portfolio by Category')}</span>
              {canManageProjectCategories(user) && (
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setShowCatModal((v) => !v)}>
                  <i className="bi bi-plus me-1"></i>
                  {t('নতুন ধরন', 'Add Category')}
                </button>
              )}
            </div>
            <div className="card-body p-0">
              {showCatModal && (
                <form
                  className="p-3 border-bottom bg-light"
                  onSubmit={(e) => {
                    e.preventDefault();
                    createCatMutation.mutate(catForm);
                  }}
                >
                  <div className="row g-2">
                    <div className="col-md-4">
                      <input
                        className="form-control form-control-sm"
                        placeholder="CODE"
                        required
                        value={catForm.code}
                        onChange={(e) => setCatForm((p) => ({ ...p, code: e.target.value }))}
                      />
                    </div>
                    <div className="col-md-4">
                      <input
                        className="form-control form-control-sm"
                        placeholder={t('নাম (ইংরেজি)', 'Name (EN)')}
                        required
                        value={catForm.name_en}
                        onChange={(e) => setCatForm((p) => ({ ...p, name_en: e.target.value }))}
                      />
                    </div>
                    <div className="col-md-4">
                      <input
                        className="form-control form-control-sm"
                        placeholder={t('নাম (বাংলা)', 'Name (BN)')}
                        required
                        value={catForm.name_bn}
                        onChange={(e) => setCatForm((p) => ({ ...p, name_bn: e.target.value }))}
                      />
                    </div>
                  </div>
                  <div className="mt-2 d-flex justify-content-end gap-2">
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setShowCatModal(false)}>
                      {t('বাতিল', 'Cancel')}
                    </button>
                    <button type="submit" className="btn btn-sm btn-primary" disabled={createCatMutation.isPending}>
                      {t('সংরক্ষণ', 'Save')}
                    </button>
                  </div>
                </form>
              )}

              <div className="table-responsive">
                <table className="table table-sm table-hover mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('ধরন', 'Category')}</th>
                      <th className="text-center">{t('সংখ্যা', 'Count')}</th>
                      <th className="text-end">{t('অনুমোদিত বাজেট', 'Approved')}</th>
                      <th className="text-end">{t('ব্যয়', 'Expenditure')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {byCategory.length === 0 && (
                      <tr>
                        <td colSpan="4" className="text-center text-muted py-3">{t('কোনো তথ্য নেই', 'No data')}</td>
                      </tr>
                    )}
                    {byCategory.map((row) => (
                      <tr key={row.code}>
                        <td>
                          <span className="badge me-2" style={{ backgroundColor: row.color_hex || '#0d6efd' }}>&nbsp;</span>
                          {t(row.name_bn, row.name_en)}
                        </td>
                        <td className="text-center fw-semibold">{row.count}</td>
                        <td className="text-end small">{formatBdt(row.approved_budget_bdt)}</td>
                        <td className="text-end small">{formatBdt(row.expenditure_bdt)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-7">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white d-flex justify-content-between align-items-center">
              <span className="fw-semibold">{t('অবস্থান অনুযায়ী প্রকল্পের অগ্রগতি', 'Project Progress by Location')}</span>
              <span className="badge bg-light text-dark border">
                {t('খোলা ঝুঁকি:', 'Open Risks:')} {summary.open_risks_count ?? 0} | {t('পরিদর্শন:', 'Visits:')} {summary.monitoring_reports_count ?? 0}
              </span>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-sm table-hover mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('প্রশাসনিক এলাকা', 'Jurisdiction')}</th>
                      <th className="text-center">{t('প্রকল্প', 'Projects')}</th>
                      <th className="text-center">{t('বিলম্বিত', 'Delayed')}</th>
                      <th>{t('ভৌত অগ্রগতি', 'Physical')}</th>
                      <th>{t('আর্থিক অগ্রগতি', 'Financial')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {byLocation.length === 0 && (
                      <tr>
                        <td colSpan="5" className="text-center text-muted py-3">{t('কোনো তথ্য নেই', 'No data')}</td>
                      </tr>
                    )}
                    {byLocation.map((loc) => (
                      <tr key={loc.administrative_unit_id || loc.name_en}>
                        <td>
                          <div className="fw-semibold small">{t(loc.name_bn, loc.name_en)}</div>
                          <small className="text-muted">{loc.tier_level}</small>
                        </td>
                        <td className="text-center">{loc.project_count}</td>
                        <td className="text-center">
                          {loc.delayed_count > 0 ? <span className="badge bg-danger">{loc.delayed_count}</span> : <span className="text-muted">0</span>}
                        </td>
                        <td style={{ minWidth: '120px' }}>
                          <div className="d-flex align-items-center gap-2">
                            <div className="progress flex-grow-1" style={{ height: '6px' }}>
                              <div className="progress-bar bg-success" style={{ width: `${loc.avg_physical_progress_pct}%` }} />
                            </div>
                            <small>{loc.avg_physical_progress_pct}%</small>
                          </div>
                        </td>
                        <td style={{ minWidth: '120px' }}>
                          <div className="d-flex align-items-center gap-2">
                            <div className="progress flex-grow-1" style={{ height: '6px' }}>
                              <div className="progress-bar bg-info" style={{ width: `${loc.financial_progress_pct}%` }} />
                            </div>
                            <small>{loc.financial_progress_pct}%</small>
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
      </div>
    </div>
  );
};

export default ProjectDashboardPage;
