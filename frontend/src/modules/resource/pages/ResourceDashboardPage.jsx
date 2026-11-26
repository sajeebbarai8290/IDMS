import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { resourceApi } from '../../../api/resourceApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canDeployResources,
  canExportResources,
  canManageResourceCategories,
  canWriteResourceAssets,
  formatBdt,
  formatResourceApiError,
  isResourceDeployBlackout,
  triggerResourceBlobDownload,
} from '../utils/resourcePermissions';

export const ResourceDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [categoryFilter, setCategoryFilter] = useState('');
  const [disasterFilter, setDisasterFilter] = useState('');
  const [showCatModal, setShowCatModal] = useState(false);
  const [catForm, setCatForm] = useState({
    code: '',
    name_en: '',
    name_bn: '',
    description: '',
    color_hex: '#0d6efd',
    is_human_resource: false,
  });
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['resource', 'lookups'],
    queryFn: () => resourceApi.getLookups(),
  });

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['resource', 'dashboard', categoryFilter, disasterFilter],
    queryFn: () =>
      resourceApi.getDashboard({
        ...(categoryFilter ? { category_id: categoryFilter } : {}),
        ...(disasterFilter ? { disaster_event_id: disasterFilter } : {}),
      }),
  });

  const createCatMutation = useMutation({
    mutationFn: (payload) => resourceApi.createCategory(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['resource'] });
      setCatForm({ code: '', name_en: '', name_bn: '', description: '', color_hex: '#0d6efd', is_human_resource: false });
      setShowCatModal(false);
      setFeedback({ variant: 'success', message: t('নতুন সম্পদ শ্রেণি যুক্ত হয়েছে।', 'Resource category created.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('শ্রেণি সংরক্ষণ ব্যর্থ।', 'Failed to add category.')) });
    },
  });

  const handleExport = async () => {
    try {
      const res = await resourceApi.exportCsv(categoryFilter ? { category_id: categoryFilter } : {});
      triggerResourceBlobDownload(res.data, 'idms-resource-register.csv');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('এক্সপোর্ট ব্যর্থ।', 'Export failed.')) });
    }
  };

  if (isLoading) return <Loader />;

  const summary = dashRes?.data?.summary || {};
  const byCategory = dashRes?.data?.by_category || [];
  const byDisaster = dashRes?.data?.utilization_by_disaster || [];
  const geoDist = dashRes?.data?.geographical_distribution || [];
  const reminders = dashRes?.data?.maintenance_reminders || { due_assets: [], scheduled_tasks: [] };
  const categories = lookupsRes?.data?.categories || [];
  const disasters = lookupsRes?.data?.disasters || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-truck-front-fill text-primary me-2"></i>
            {t('সম্পদ ব্যবস্থাপনা ড্যাশবোর্ড (M07)', 'Resource Management Dashboard (M07)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'যানবাহন, নৌযান, উদ্ধার সরঞ্জাম, মানবসম্পদ, বরাদ্দ ও রক্ষণাবেক্ষণের কেন্দ্রীয় তথ্যভাণ্ডার',
              'Centralized register of vehicles, boats, rescue/medical gear, human resources, allocations & maintenance'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <select
            className="form-select form-select-sm"
            style={{ width: '175px' }}
            value={categoryFilter}
            onChange={(e) => setCategoryFilter(e.target.value)}
          >
            <option value="">{t('সকল শ্রেণি', 'All Categories')}</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
            ))}
          </select>

          <select
            className="form-select form-select-sm"
            style={{ width: '195px' }}
            value={disasterFilter}
            onChange={(e) => setDisasterFilter(e.target.value)}
          >
            <option value="">{t('সকল দুর্যোগ ইভেন্ট', 'All Disasters')}</option>
            {disasters.map((d) => (
              <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)}</option>
            ))}
          </select>

          <Link to="/resource/map" className="btn btn-sm btn-outline-primary">
            <i className="bi bi-geo-alt me-1"></i>
            {t('জিআইএস মানচিত্র', 'GIS Map')}
          </Link>
          <Link to="/resource/assets" className="btn btn-sm btn-outline-secondary">
            {t('সম্পদ তালিকা', 'Registry')}
          </Link>
          {canExportResources(user) && (
            <button type="button" className="btn btn-sm btn-outline-success" onClick={handleExport}>
              <i className="bi bi-download me-1"></i>
              {t('সিএসভি এক্সপোর্ট', 'Export CSV')}
            </button>
          )}
          {canWriteResourceAssets(user) && (
            <Link to="/resource/assets/new" className="btn btn-sm btn-primary">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন সম্পদ নিবন্ধন', 'Register Resource')}
            </Link>
          )}
        </div>
      </div>

      {isResourceDeployBlackout(user) && (
        <AlertBanner
          variant="info"
          message={t(
            'সুপার অ্যাডমিন অপারেশনাল ব্ল্যাকআউট: আপনি সম্পদ শ্রেণি কনফিগার ও নিবন্ধন দেখতে পারবেন, তবে জরুরি বরাদ্দ/মোতায়েন সম্পাদন করতে পারবেন না।',
            'Super Admin Operational Blackout: You can configure categories and manage asset registry metadata, but cannot execute operational resource deployments.'
          )}
        />
      )}

      {error && <AlertBanner variant="danger" message={formatResourceApiError(error, t('ড্যাশবোর্ড লোড ব্যর্থ', 'Failed to load dashboard'))} />}
      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {/* KPI Cards */}
      <div className="row g-3 mb-4">
        {[
          { bn: 'মোট সম্পদ', en: 'Total Resources', val: summary.total_resources ?? 0, icon: 'bi-boxes', color: 'primary', to: '/resource/assets' },
          { bn: 'উপলব্ধ সম্পদ', en: 'Available Resources', val: summary.available_resources ?? 0, icon: 'bi-check-circle-fill', color: 'success', to: '/resource/assets?status=AVAILABLE' },
          { bn: 'বরাদ্দকৃত ও ব্যবহৃত', en: 'Assigned / In Use', val: summary.assigned_resources ?? 0, icon: 'bi-send-check-fill', color: 'info', to: '/resource/allocations' },
          { bn: 'রক্ষণাবেক্ষণে', en: 'Under Maintenance', val: summary.under_maintenance_resources ?? 0, icon: 'bi-wrench-adjustable-circle-fill', color: 'warning', to: '/resource/maintenance' },
          { bn: 'সক্রিয় মানবসম্পদ মোতায়েন', en: 'Active HR Deployments', val: summary.active_human_deployments ?? 0, icon: 'bi-people-fill', color: 'secondary', to: '/resource/human-deployments' },
          { bn: 'রক্ষণাবেক্ষণ রিমাইন্ডার', en: 'Due / Overdue Reminders', val: summary.maintenance_reminders_count ?? 0, icon: 'bi-bell-fill', color: 'danger', to: '/resource/maintenance' },
        ].map((k) => (
          <div className="col-6 col-md-4 col-xl-2" key={k.en}>
            <Link to={k.to} className="text-decoration-none">
              <div className="card border-0 shadow-sm h-100">
                <div className="card-body">
                  <div className="d-flex justify-content-between align-items-start">
                    <span className="text-muted small">{t(k.bn, k.en)}</span>
                    <i className={`bi ${k.icon} text-${k.color} fs-5`}></i>
                  </div>
                  <div className={`fs-3 fw-bold text-${k.color} mt-1`}>{k.val}</div>
                </div>
              </div>
            </Link>
          </div>
        ))}
      </div>

      {/* Quick Workbench Links & Financial Overview */}
      <div className="row g-3 mb-4">
        <div className="col-lg-8">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white d-flex justify-content-between align-items-center">
              <span className="fw-bold">{t('শ্রেণিভিত্তিক সম্পদ প্রাপ্যতা', 'Availability by Resource Category')}</span>
              {canManageResourceCategories(user) && (
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setShowCatModal(true)}>
                  <i className="bi bi-plus me-1"></i>{t('নতুন শ্রেণি যোগ', 'Add Category')}
                </button>
              )}
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('শ্রেণি', 'Category')}</th>
                      <th className="text-end">{t('মোট', 'Total')}</th>
                      <th className="text-end">{t('উপলব্ধ', 'Available')}</th>
                      <th className="text-end">{t('বরাদ্দকৃত', 'Assigned/In Use')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {byCategory.length === 0 ? (
                      <tr><td colSpan={4} className="text-center text-muted py-3">{t('কোন তথ্য নেই', 'No category data')}</td></tr>
                    ) : (
                      byCategory.map((c) => (
                        <tr key={c.code}>
                          <td>
                            <span className="badge me-2" style={{ backgroundColor: c.color_hex || '#6c757d' }}>&nbsp;</span>
                            <span className="fw-medium">{t(c.name_bn, c.name_en)}</span>
                          </td>
                          <td className="text-end fw-bold">{c.total}</td>
                          <td className="text-end text-success fw-semibold">{c.available}</td>
                          <td className="text-end text-primary">{c.assigned_or_in_use}</td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white fw-bold">{t('আর্থিক ও দ্রুত কার্যক্রম', 'Valuation & Workbenches')}</div>
            <div className="card-body d-flex flex-column justify-content-between gap-2">
              <div className="p-2 bg-light rounded">
                <div className="text-muted small">{t('নিবন্ধিত সম্পদের মোট মূল্য', 'Total Registered Asset Value')}</div>
                <div className="fs-5 fw-bold text-dark">{formatBdt(summary.total_asset_value_bdt)}</div>
              </div>
              <div className="p-2 bg-light rounded">
                <div className="text-muted small">{t('মোট মেরামত ও রক্ষণাবেক্ষণ ব্যয়', 'Total Maintenance & Repair Cost')}</div>
                <div className="fs-5 fw-bold text-warning">{formatBdt(summary.total_repair_cost_bdt)}</div>
              </div>
              <div className="d-grid gap-2 mt-1">
                {canDeployResources(user) && (
                  <Link to="/resource/allocations" className="btn btn-sm btn-outline-primary">
                    <i className="bi bi-send me-1"></i>{t('সম্পদ বরাদ্দ ও ফেরত', 'Allocations & Releases')}
                  </Link>
                )}
                <Link to="/resource/human-deployments" className="btn btn-sm btn-outline-dark">
                  <i className="bi bi-person-badge me-1"></i>{t('মানবসম্পদ মোতায়েন', 'Human Resource Deployments')}
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Disaster Utilization + Geo Distribution + Maintenance Reminders */}
      <div className="row g-3">
        <div className="col-lg-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white fw-bold">{t('দুর্যোগভিত্তিক সম্পদ ব্যবহার', 'Resource Utilization by Disaster')}</div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-sm mb-0 align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>{t('দুর্যোগ', 'Disaster')}</th>
                      <th className="text-end">{t('মোট', 'Total')}</th>
                      <th className="text-end">{t('সক্রিয়', 'Active')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {byDisaster.length === 0 ? (
                      <tr><td colSpan={3} className="text-center text-muted py-3">{t('কোন বরাদ্দ রেকর্ড নেই', 'No disaster allocations yet')}</td></tr>
                    ) : (
                      byDisaster.map((d) => (
                        <tr key={d.disaster_event_id}>
                          <td>
                            <div className="fw-medium small">{t(d.disaster_name_bn, d.disaster_name_en)}</div>
                            <small className="text-muted">{d.disaster_code}</small>
                          </td>
                          <td className="text-end fw-bold">{d.total_allocations}</td>
                          <td className="text-end"><span className="badge bg-primary">{d.active_allocations}</span></td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white fw-bold">{t('ভৌগোলিক সম্পদ বণ্টন', 'Geographical Resource Distribution')}</div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-sm mb-0 align-middle">
                  <thead className="table-light">
                    <tr>
                      <th>{t('অধিক্ষেত্র', 'Administrative Unit')}</th>
                      <th className="text-end">{t('মোট', 'Total')}</th>
                      <th className="text-end">{t('উপলব্ধ', 'Avail.')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {geoDist.length === 0 ? (
                      <tr><td colSpan={3} className="text-center text-muted py-3">{t('কোন তথ্য নেই', 'No data')}</td></tr>
                    ) : (
                      geoDist.map((g) => (
                        <tr key={g.administrative_unit_id}>
                          <td>
                            <div className="fw-medium small">{t(g.unit_name_bn, g.unit_name_en)}</div>
                            <small className="text-muted">{g.tier_level}</small>
                          </td>
                          <td className="text-end fw-bold">{g.total_resources}</td>
                          <td className="text-end text-success fw-semibold">{g.available_count}</td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
              <span>{t('রক্ষণাবেক্ষণ রিমাইন্ডার', 'Maintenance Reminders')}</span>
              <Link to="/resource/maintenance" className="small text-decoration-none">{t('সব দেখুন', 'View all')}</Link>
            </div>
            <div className="card-body small">
              {(reminders.due_assets || []).length === 0 && (reminders.scheduled_tasks || []).length === 0 ? (
                <p className="text-muted mb-0">{t('আসন্ন কোন রক্ষণাবেক্ষণ রিমাইন্ডার নেই।', 'No upcoming or overdue maintenance reminders.')}</p>
              ) : (
                <ul className="list-group list-group-flush">
                  {(reminders.due_assets || []).slice(0, 5).map((a) => (
                    <li key={a.resource_asset_id} className="list-group-item px-0 d-flex justify-content-between align-items-center">
                      <div>
                        <Link to={`/resource/assets/${a.resource_asset_id}`} className="fw-semibold text-decoration-none">
                          {a.asset_code}
                        </Link>
                        <div className="text-muted">{t(a.name_bn, a.name_en)}</div>
                      </div>
                      <span className={`badge ${a.is_overdue ? 'bg-danger' : 'bg-warning text-dark'}`}>
                        {a.due_date}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Add Category Modal */}
      {showCatModal && (
        <div className="modal d-block" style={{ backgroundColor: 'rgba(0,0,0,0.45)' }}>
          <div className="modal-dialog">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                createCatMutation.mutate(catForm);
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title">{t('নতুন সম্পদ শ্রেণি যোগ করুন', 'Add Configurable Resource Category')}</h5>
                <button type="button" className="btn-close" onClick={() => setShowCatModal(false)}></button>
              </div>
              <div className="modal-body">
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('কোড *', 'Category Code *')}</label>
                  <input
                    className="form-control form-control-sm"
                    required
                    placeholder="e.g. DRONE_UNIT"
                    value={catForm.code}
                    onChange={(e) => setCatForm((p) => ({ ...p, code: e.target.value.toUpperCase() }))}
                  />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('ইংরেজি নাম *', 'Name (English) *')}</label>
                  <input
                    className="form-control form-control-sm"
                    required
                    value={catForm.name_en}
                    onChange={(e) => setCatForm((p) => ({ ...p, name_en: e.target.value }))}
                  />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('বাংলা নাম *', 'Name (Bangla) *')}</label>
                  <input
                    className="form-control form-control-sm"
                    required
                    value={catForm.name_bn}
                    onChange={(e) => setCatForm((p) => ({ ...p, name_bn: e.target.value }))}
                  />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('বিবরণ', 'Description')}</label>
                  <textarea
                    className="form-control form-control-sm"
                    rows={2}
                    value={catForm.description}
                    onChange={(e) => setCatForm((p) => ({ ...p, description: e.target.value }))}
                  />
                </div>
                <div className="form-check">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id="isHrCat"
                    checked={catForm.is_human_resource}
                    onChange={(e) => setCatForm((p) => ({ ...p, is_human_resource: e.target.checked }))}
                  />
                  <label className="form-check-label small" htmlFor="isHrCat">
                    {t('মানবসম্পদ শ্রেণি', 'Human Resource Category')}
                  </label>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-sm btn-secondary" onClick={() => setShowCatModal(false)}>
                  {t('বাতিল', 'Cancel')}
                </button>
                <button type="submit" className="btn btn-sm btn-primary" disabled={createCatMutation.isPending}>
                  {t('সংরক্ষণ করুন', 'Save Category')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default ResourceDashboardPage;
