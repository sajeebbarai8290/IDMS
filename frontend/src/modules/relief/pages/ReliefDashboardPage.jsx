import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { reliefApi } from '../../../api/reliefApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canCreateAllocation,
  canCreateRequisition,
  canManageReliefCategories,
  canRecordDistribution,
  downloadBlobFile,
  formatReliefApiError,
  isSuperAdminBlackout,
} from '../utils/reliefPermissions';
import { RequisitionStatusBadge } from '../components/ReliefStatusBadges';

export const ReliefDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [disasterFilter, setDisasterFilter] = useState('');
  const [showCatModal, setShowCatModal] = useState(false);
  const [catForm, setCatForm] = useState({ code: '', name_en: '', name_bn: '', description: '' });
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['relief', 'lookups'],
    queryFn: () => reliefApi.getLookups(),
  });

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['relief', 'dashboard', disasterFilter],
    queryFn: () => reliefApi.getDashboard(disasterFilter ? { disaster_event_id: disasterFilter } : {}),
  });

  const { data: categoriesRes } = useQuery({
    queryKey: ['relief', 'categories'],
    queryFn: () => reliefApi.getCategories(),
  });

  const createCatMutation = useMutation({
    mutationFn: (payload) => reliefApi.createCategory(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relief', 'categories'] });
      setCatForm({ code: '', name_en: '', name_bn: '', description: '' });
      setShowCatModal(false);
      setFeedback({ variant: 'success', message: t('ত্রাণ ক্যাটাগরি যোগ করা হয়েছে।', 'Relief category added successfully.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatReliefApiError(err, t('ত্রাণ ক্যাটাগরি সংরক্ষণে ত্রুটি।', 'Failed to create relief category.')) });
    },
  });

  const handleExportMuster = async () => {
    try {
      const blob = await reliefApi.exportMusterCsv(disasterFilter ? { disaster_event_id: disasterFilter } : {});
      downloadBlobFile(blob, `relief-muster-${new Date().toISOString().slice(0, 10)}.csv`);
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatReliefApiError(err, t('মাস্টার রোল এক্সপোর্ট ব্যর্থ হয়েছে।', 'Muster roll export failed.')) });
    }
  };

  if (isLoading) return <Loader />;

  const summary = dashRes?.data?.summary || {};
  const statusBreakdown = dashRes?.data?.requisition_status_breakdown || {};
  const areaStats = dashRes?.data?.area_wise_stats || [];
  const warehouses = dashRes?.data?.warehouse_stock_summary || [];
  const disasters = lookupsRes?.data?.disasters || [];
  const categories = categoriesRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-box-seam text-success me-2"></i>
            {t('ত্রাণ বিতরণ ব্যবস্থাপনা ড্যাশবোর্ড', 'Relief Distribution Management Dashboard')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'চাহিদাপত্র, সরকারি বরাদ্দ, গুদাম মজুদ এবং উপকারভোগী পর্যায়ে ত্রাণ বিতরণ পর্যবেক্ষণ',
              'Central oversight of relief requisitions, official allocations, warehouse inventory, and beneficiary hand-off'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <select
            className="form-select form-select-sm"
            style={{ width: '230px' }}
            value={disasterFilter}
            onChange={(e) => setDisasterFilter(e.target.value)}
          >
            <option value="">{t('সকল দুর্যোগ ইভেন্ট', 'All Disaster Events')}</option>
            {disasters.map((d) => (
              <option key={d.id} value={d.id}>
                {t(d.name_bn || d.name_en, d.name_en)} ({d.disaster_code})
              </option>
            ))}
          </select>

          {canCreateRequisition(user) && (
            <Link to="/relief/requisitions/new" className="btn btn-sm btn-success">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন চাহিদাপত্র', 'New Requisition')}
            </Link>
          )}
          {canCreateAllocation(user) && (
            <Link to="/relief/allocations/new" className="btn btn-sm btn-primary">
              <i className="bi bi-truck me-1"></i>
              {t('বরাদ্দ আদেশ', 'New Allocation')}
            </Link>
          )}
          {canRecordDistribution(user) && (
            <Link to="/relief/distributions" className="btn btn-sm btn-warning text-dark fw-semibold">
              <i className="bi bi-hand-index-thumb me-1"></i>
              {t('ত্রাণ বিতরণ করুন', 'Record Distribution')}
            </Link>
          )}
          <button type="button" className="btn btn-sm btn-outline-secondary" onClick={handleExportMuster}>
            <i className="bi bi-download me-1"></i>
            {t('মাস্টার রোল (CSV)', 'Muster CSV')}
          </button>
        </div>
      </div>

      {isSuperAdminBlackout(user) && (
        <AlertBanner
          variant="info"
          message={t(
            'সুপার অ্যাডমিন অপারেশনাল ব্ল্যাকআউট সক্রিয়: আপনি শুধুমাত্র পর্যবেক্ষণ, রিপোর্ট রপ্তানি এবং ত্রাণ ক্যাটাগরি কনফিগার করতে পারবেন।',
            'Super Admin Operational Blackout active: You may view analytics, export muster rolls, and configure relief categories, but cannot create or approve operational transactions.'
          )}
        />
      )}

      {error && <AlertBanner variant="danger" message={error?.message || t('ড্যাশবোর্ড তথ্য লোড ব্যর্থ', 'Failed to load dashboard data')} />}
      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {/* KPI Cards */}
      <div className="row g-3 mb-4">
        {[
          { labelBn: 'মোট চাহিদাপত্র', labelEn: 'Total Requisitions', val: summary.total_requests ?? 0, icon: 'bi-file-earmark-text', color: 'primary', to: '/relief/requisitions' },
          { labelBn: 'অনুমোদনের অপেক্ষায়', labelEn: 'Pending Approvals', val: summary.pending_approvals ?? 0, icon: 'bi-hourglass-split', color: 'warning', to: '/relief/requisitions?status=SUBMITTED' },
          { labelBn: 'মঞ্জুরিকৃত বরাদ্দ', labelEn: 'Approved Allocations', val: summary.approved_allocations ?? 0, icon: 'bi-clipboard-check', color: 'info', to: '/relief/allocations' },
          { labelBn: 'বিতরণ অগ্রগতি', labelEn: 'Distribution Progress', val: `${summary.distribution_progress_pct ?? 0}%`, icon: 'bi-graph-up-arrow', color: 'success', to: '/relief/allocations' },
          { labelBn: 'উপকৃত পরিবার', labelEn: 'Beneficiaries Served', val: summary.beneficiaries_served ?? 0, icon: 'bi-people-fill', color: 'dark', to: '/relief/distributions' },
          { labelBn: 'ইস্যুকৃত ভাউচার', labelEn: 'Vouchers Issued', val: summary.vouchers_issued ?? 0, icon: 'bi-receipt-cutoff', color: 'secondary', to: '/relief/distributions' },
        ].map((kpi) => (
          <div className="col-6 col-md-4 col-xl-2" key={kpi.labelEn}>
            <Link to={kpi.to} className="text-decoration-none">
              <div className="card border-0 shadow-sm h-100">
                <div className="card-body">
                  <div className="d-flex justify-content-between align-items-center mb-2">
                    <span className={`badge bg-${kpi.color} bg-opacity-10 text-${kpi.color} p-2`}>
                      <i className={`bi ${kpi.icon} fs-5`}></i>
                    </span>
                  </div>
                  <div className="fs-4 fw-bold text-dark">{kpi.val}</div>
                  <div className="small text-muted">{t(kpi.labelBn, kpi.labelEn)}</div>
                </div>
              </div>
            </Link>
          </div>
        ))}
      </div>

      <div className="row g-3 mb-4">
        {/* Area-wise progress */}
        <div className="col-lg-7">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-geo-alt text-primary me-2"></i>
                {t('এলাকাভিত্তিক বরাদ্দ বনাম বিতরণ অগ্রগতি', 'Area-wise Allocation vs. Distribution Progress')}
              </h6>
              <Link to="/relief/allocations" className="btn btn-sm btn-link text-decoration-none">
                {t('সব দেখুন', 'View All')}
              </Link>
            </div>
            <div className="card-body">
              {areaStats.length === 0 ? (
                <p className="text-muted small mb-0">{t('এখনও কোন বরাদ্দ রেকর্ড নেই।', 'No allocation statistics available yet.')}</p>
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>{t('প্রশাসনিক এলাকা', 'Jurisdiction')}</th>
                        <th className="text-end">{t('বরাদ্দ', 'Allocated')}</th>
                        <th className="text-end">{t('বিতরণকৃত', 'Distributed')}</th>
                        <th style={{ width: '30%' }}>{t('অগ্রগতি', 'Progress')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {areaStats.map((row) => {
                        const pct = row.quantity_allocated > 0
                          ? Math.min(100, Math.round((row.quantity_distributed / row.quantity_allocated) * 100))
                          : 0;
                        return (
                          <tr key={row.administrative_unit_id}>
                            <td className="fw-medium">{t(row.unit_name_bn, row.unit_name_en)}</td>
                            <td className="text-end">{row.quantity_allocated}</td>
                            <td className="text-end">{row.quantity_distributed}</td>
                            <td>
                              <div className="d-flex align-items-center gap-2">
                                <div className="progress flex-grow-1" style={{ height: '8px' }}>
                                  <div className="progress-bar bg-success" style={{ width: `${pct}%` }} />
                                </div>
                                <small className="text-muted">{pct}%</small>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Warehouse stock & Requisition breakdown */}
        <div className="col-lg-5">
          <div className="card border-0 shadow-sm mb-3">
            <div className="card-header bg-white border-bottom">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-funnel text-info me-2"></i>
                {t('চাহিদাপত্র অবস্থা বিভাজন', 'Requisition Status Breakdown')}
              </h6>
            </div>
            <div className="card-body d-flex flex-wrap gap-2">
              {Object.keys(statusBreakdown).length === 0 ? (
                <span className="text-muted small">{t('কোন চাহিদাপত্র নেই', 'No requisitions recorded')}</span>
              ) : (
                Object.entries(statusBreakdown).map(([st, count]) => (
                  <div key={st} className="border rounded px-2 py-1 d-flex align-items-center gap-2 bg-light">
                    <RequisitionStatusBadge status={st} />
                    <span className="fw-bold">{count}</span>
                  </div>
                ))
              )}
            </div>
          </div>

          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white border-bottom">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-building text-warning me-2"></i>
                {t('সরবরাহকারী গুদাম ও মজুদ সারসংক্ষেপ', 'Supplying Warehouses & Stock Summary')}
              </h6>
            </div>
            <div className="card-body">
              {warehouses.length === 0 ? (
                <p className="text-muted small mb-0">{t('সক্রিয় গুদাম পাওয়া যায়নি।', 'No active warehouses found.')}</p>
              ) : (
                <ul className="list-group list-group-flush">
                  {warehouses.map((w) => (
                    <li key={w.id} className="list-group-item px-0 d-flex justify-content-between align-items-center">
                      <div>
                        <div className="fw-semibold small">{t(w.warehouse_name_bn || w.warehouse_name, w.warehouse_name)}</div>
                        <small className="text-muted">{w.warehouse_code} • {w.tier}</small>
                      </div>
                      <span className="badge bg-success bg-opacity-10 text-success">
                        {w.total_units_on_hand} {t('ইউনিট', 'Units')}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Relief Categories Catalog */}
      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
          <h6 className="fw-bold mb-0">
            <i className="bi bi-tags text-secondary me-2"></i>
            {t('ত্রাণ সামগ্রী ক্যাটাগরি তালিকা', 'Configured Relief Categories')}
          </h6>
          {canManageReliefCategories(user) && (
            <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setShowCatModal(true)}>
              <i className="bi bi-plus-lg me-1"></i>
              {t('নতুন ক্যাটাগরি', 'Add Category')}
            </button>
          )}
        </div>
        <div className="card-body d-flex flex-wrap gap-2">
          {categories.map((c) => (
            <div key={c.id} className="border rounded px-3 py-2 bg-light small">
              <div className="fw-bold">{t(c.name_bn, c.name_en)}</div>
              <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                {c.code} • {c.commodities_count ?? 0} {t('আইটেম', 'items')}
              </div>
            </div>
          ))}
        </div>
      </div>

      {showCatModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.45)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                createCatMutation.mutate(catForm);
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title fw-bold">{t('নতুন ত্রাণ ক্যাটাগরি যোগ করুন', 'Create Relief Category')}</h5>
                <button type="button" className="btn-close" onClick={() => setShowCatModal(false)}></button>
              </div>
              <div className="modal-body">
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('কোড', 'Category Code')}</label>
                  <input
                    className="form-control"
                    required
                    placeholder="e.g. HYGIENE_KITS"
                    value={catForm.code}
                    onChange={(e) => setCatForm((p) => ({ ...p, code: e.target.value.toUpperCase() }))}
                  />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('ইংরেজি নাম', 'English Name')}</label>
                  <input
                    className="form-control"
                    required
                    value={catForm.name_en}
                    onChange={(e) => setCatForm((p) => ({ ...p, name_en: e.target.value }))}
                  />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('বাংলা নাম', 'Bangla Name')}</label>
                  <input
                    className="form-control"
                    required
                    value={catForm.name_bn}
                    onChange={(e) => setCatForm((p) => ({ ...p, name_bn: e.target.value }))}
                  />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('বিবরণ', 'Description')}</label>
                  <textarea
                    className="form-control"
                    rows={2}
                    value={catForm.description}
                    onChange={(e) => setCatForm((p) => ({ ...p, description: e.target.value }))}
                  />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setShowCatModal(false)}>
                  {t('বাতিল', 'Cancel')}
                </button>
                <button type="submit" className="btn btn-primary" disabled={createCatMutation.isPending}>
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

export default ReliefDashboardPage;
