import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { damageApi } from '../../../api/damageApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canCreateDamage,
  canExportDamage,
  canManageDamageCategories,
  DAMAGE_STATUS_META,
  formatBdt,
  formatDamageApiError,
  isDamageBlackout,
  triggerBlobDownload,
} from '../utils/damagePermissions';
import { DamageSeverityBadge } from '../components/DamageStatusBadges';

export const DamageDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [disasterFilter, setDisasterFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [showCatModal, setShowCatModal] = useState(false);
  const [catForm, setCatForm] = useState({
    code: '',
    name_en: '',
    name_bn: '',
    default_unit: 'UNITS',
    description: '',
    color_hex: '#0d6efd',
  });
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['damage', 'lookups'],
    queryFn: () => damageApi.getLookups(),
  });

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['damage', 'dashboard', disasterFilter, statusFilter],
    queryFn: () =>
      damageApi.getDashboard({
        ...(disasterFilter ? { disaster_event_id: disasterFilter } : {}),
        ...(statusFilter ? { verification_status: statusFilter } : {}),
      }),
  });

  const createCatMutation = useMutation({
    mutationFn: (payload) => damageApi.createCategory(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['damage'] });
      setCatForm({
        code: '',
        name_en: '',
        name_bn: '',
        default_unit: 'UNITS',
        description: '',
        color_hex: '#0d6efd',
      });
      setShowCatModal(false);
      setFeedback({
        variant: 'success',
        message: t('নতুন ক্ষয়ক্ষতির খাত সংরক্ষণ করা হয়েছে।', 'Damage category created successfully.'),
      });
    },
    onError: (err) => {
      setFeedback({
        variant: 'danger',
        message: formatDamageApiError(err, t('খাত সংরক্ষণ ব্যর্থ।', 'Failed to save category.')),
      });
    },
  });

  const handleExportCsv = async () => {
    try {
      const res = await damageApi.exportCsv({
        ...(disasterFilter ? { disaster_event_id: disasterFilter } : {}),
        ...(statusFilter ? { verification_status: statusFilter } : {}),
      });
      triggerBlobDownload(res.data, 'idms-damage-assessments.csv');
    } catch (err) {
      setFeedback({
        variant: 'danger',
        message: formatDamageApiError(err, t('এক্সপোর্ট ব্যর্থ হয়েছে।', 'CSV export failed.')),
      });
    }
  };

  if (isLoading) return <Loader />;

  const summary = dashRes?.data?.summary || {};
  const byCategory = dashRes?.data?.damage_by_category || [];
  const byArea = dashRes?.data?.damage_by_area || [];
  const byStatus = dashRes?.data?.by_status || {};
  const disasters = lookupsRes?.data?.disasters || [];
  const statuses = lookupsRes?.data?.verification_statuses || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-clipboard2-data-fill text-danger me-2"></i>
            {t('ক্ষয়ক্ষতি নিরূপণ ড্যাশবোর্ড (ডি-ফর্ম - M09)', 'Damage Assessment Dashboard (D-Form - M09)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'দুর্যোগজনিত ক্ষয়ক্ষতি, প্রভাবিত পরিবার, আর্থিক ক্ষতি এবং যাচাইকরণ অগ্রগতির সারসংক্ষেপ',
              'Disaster damage & loss analytics, affected households, category breakdown & verification pipeline'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <select
            className="form-select form-select-sm"
            style={{ width: '190px' }}
            value={disasterFilter}
            onChange={(e) => setDisasterFilter(e.target.value)}
          >
            <option value="">{t('সকল দুর্যোগ', 'All Disasters')}</option>
            {disasters.map((d) => (
              <option key={d.id} value={d.id}>
                {t(d.name_bn, d.name_en)}
              </option>
            ))}
          </select>

          <select
            className="form-select form-select-sm"
            style={{ width: '165px' }}
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
            {statuses.map((s) => (
              <option key={s} value={s}>
                {t(DAMAGE_STATUS_META[s]?.bn || s, DAMAGE_STATUS_META[s]?.en || s)}
              </option>
            ))}
          </select>

          <Link to="/damage/map" className="btn btn-sm btn-outline-primary">
            <i className="bi bi-map me-1"></i>
            {t('জিআইএস ও হিটম্যাপ', 'GIS & Heatmap')}
          </Link>
          <Link to="/damage/reports" className="btn btn-sm btn-outline-info">
            <i className="bi bi-file-earmark-bar-graph me-1"></i>
            {t('প্রতিবেদন', 'Reports')}
          </Link>
          {canExportDamage(user) && (
            <button type="button" className="btn btn-sm btn-outline-success" onClick={handleExportCsv}>
              <i className="bi bi-download me-1"></i>
              {t('সিএসভি এক্সপোর্ট', 'Export CSV')}
            </button>
          )}
          {canManageDamageCategories(user) && (
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setShowCatModal(true)}>
              <i className="bi bi-tags me-1"></i>
              {t('নতুন খাত', 'Add Category')}
            </button>
          )}
          {canCreateDamage(user) && (
            <Link to="/damage/assessments/new" className="btn btn-sm btn-danger">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন ডি-ফর্ম', 'New D-Form')}
            </Link>
          )}
        </div>
      </div>

      {isDamageBlackout(user) && (
        <AlertBanner
          variant="info"
          message={t(
            'সুপার অ্যাডমিন শুধুমাত্র পর্যবেক্ষণ, খাত ব্যবস্থাপনা এবং এক্সপোর্ট করতে পারবেন; মাঠ পর্যায়ের নিরূপণ পরিবর্তন বা যাচাই করা নিষিদ্ধ।',
            'Super Admin operational blackout active: read-only monitoring, category configuration, and export allowed.'
          )}
        />
      )}

      {error && (
        <AlertBanner
          variant="danger"
          message={formatDamageApiError(error, t('ড্যাশবোর্ড লোড করতে ব্যর্থ।', 'Failed to load dashboard.'))}
        />
      )}

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {/* KPI Cards */}
      <div className="row g-3 mb-4">
        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small mb-1">{t('মোট নিরূপণ (D-Forms)', 'Total Assessments')}</div>
              <div className="fs-3 fw-bold text-primary">{summary.total_assessments || 0}</div>
              <div className="small text-muted mt-1">
                {t('অপেক্ষমাণ যাচাই', 'Pending Review')}: {summary.pending_verification || 0} |{' '}
                {t('খসড়া', 'Draft')}: {summary.draft_assessments || 0}
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small mb-1">{t('যাচাইকৃত ও অনুমোদিত', 'Verified & Approved')}</div>
              <div className="fs-3 fw-bold text-success">{summary.verified_assessments || 0}</div>
              <div className="small text-muted mt-1">
                {t('চূড়ান্ত অনুমোদিত', 'Approved')}: {summary.approved_assessments || 0} |{' '}
                {t('প্রত্যাখ্যাত/সংশোধন', 'Rejected/Corr')}:{' '}
                {(summary.rejected_assessments || 0) + (summary.correction_requested || 0)}
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small mb-1">{t('মোট ক্ষতিগ্রস্ত পরিবার', 'Total Affected Households')}</div>
              <div className="fs-3 fw-bold text-warning">
                {Number(summary.total_affected_households || 0).toLocaleString()}
              </div>
              <div className="small text-muted mt-1">
                {t('মোট জনসংখ্যা', 'Population')}: {Number(summary.total_affected_population || 0).toLocaleString()}
              </div>
            </div>
          </div>
        </div>

        <div className="col-md-3">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small mb-1">{t('আনুমানিক ক্ষয়ক্ষতি ও লোকসান', 'Estimated Damage & Loss')}</div>
              <div className="fs-4 fw-bold text-danger">{formatBdt(summary.total_estimated_loss_bdt)}</div>
              <div className="small text-muted mt-1">
                {t('সম্পূর্ণ', 'Full')}: {summary.total_fully_damaged || 0} |{' '}
                {t('আংশিক', 'Partial')}: {summary.total_partially_damaged || 0} |{' '}
                {t('সামান্য', 'Minor')}: {summary.total_minor_damaged || 0}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Status chips */}
      <div className="card border-0 shadow-sm mb-4">
        <div className="card-body py-2 d-flex flex-wrap gap-3 align-items-center">
          <span className="small fw-semibold text-muted">{t('যাচাইকরণ অবস্থা:', 'Workflow Breakdown:')}</span>
          {Object.entries(DAMAGE_STATUS_META).map(([key, meta]) => (
            <span key={key} className="badge bg-light text-dark border">
              <span className={`text-${meta.variant} me-1`}>●</span>
              {t(meta.bn, meta.en)}: <strong>{byStatus[key] || 0}</strong>
            </span>
          ))}
        </div>
      </div>

      {/* Damage by Category & Geographical Area */}
      <div className="row g-3">
        <div className="col-lg-6">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span>{t('খাতভিত্তিক ক্ষয়ক্ষতি (Damage by Category)', 'Damage by Category')}</span>
              <Link to="/damage/assessments" className="small text-decoration-none">
                {t('সকল তালিকা দেখুন', 'View All')}
              </Link>
            </div>
            <div className="card-body p-0">
              {byCategory.length === 0 ? (
                <div className="p-4 text-center text-muted small">
                  {t('কোনো খাতভিত্তিক তথ্য পাওয়া যায়নি।', 'No category damage records found.')}
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm table-hover mb-0 align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>{t('খাত', 'Category')}</th>
                        <th className="text-end">{t('সম্পূর্ণ', 'Full')}</th>
                        <th className="text-end">{t('আংশিক', 'Partial')}</th>
                        <th className="text-end">{t('সামান্য', 'Minor')}</th>
                        <th className="text-end">{t('আনুমানিক ক্ষতি', 'Est. Loss')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {byCategory.map((row) => (
                        <tr key={row.code}>
                          <td>
                            <span
                              className="d-inline-block rounded-circle me-2"
                              style={{ width: 10, height: 10, backgroundColor: row.color_hex || '#0d6efd' }}
                            />
                            <span className="fw-medium">{t(row.name_bn, row.name_en)}</span>
                          </td>
                          <td className="text-end">{row.fully_damaged_count}</td>
                          <td className="text-end">{row.partially_damaged_count}</td>
                          <td className="text-end">{row.minor_damaged_count}</td>
                          <td className="text-end fw-semibold">{formatBdt(row.estimated_loss_bdt)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-6">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span>{t('এলাকাভিত্তিক ক্ষয়ক্ষতি (Damage by Geographical Area)', 'Damage by Geographical Area')}</span>
              <Link to="/damage/map" className="small text-decoration-none">
                {t('জিআইএস মানচিত্র', 'GIS Map')}
              </Link>
            </div>
            <div className="card-body p-0">
              {byArea.length === 0 ? (
                <div className="p-4 text-center text-muted small">
                  {t('কোনো এলাকাভিত্তিক তথ্য পাওয়া যায়নি।', 'No area breakdown available.')}
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm table-hover mb-0 align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>{t('প্রশাসনিক এলাকা', 'Area')}</th>
                        <th className="text-center">{t('তীব্রতা', 'Severity')}</th>
                        <th className="text-end">{t('নিরূপণ', 'D-Forms')}</th>
                        <th className="text-end">{t('পরিবার', 'Households')}</th>
                        <th className="text-end">{t('আর্থিক ক্ষতি', 'Est. Loss')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {byArea.map((area) => (
                        <tr key={area.administrative_unit_id}>
                          <td>
                            <div className="fw-medium">{t(area.name_bn, area.name_en)}</div>
                            <div className="small text-muted">{area.tier_level}</div>
                          </td>
                          <td className="text-center">
                            <DamageSeverityBadge severity={area.max_severity} />
                          </td>
                          <td className="text-end">
                            {area.assessment_count}{' '}
                            <span className="text-success small">({area.verified_count})</span>
                          </td>
                          <td className="text-end">{Number(area.affected_households || 0).toLocaleString()}</td>
                          <td className="text-end fw-semibold">{formatBdt(area.total_estimated_loss_bdt)}</td>
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

      {/* Add Category Modal */}
      {showCatModal && (
        <div className="modal d-block" style={{ backgroundColor: 'rgba(0,0,0,0.45)' }}>
          <div className="modal-dialog">
            <div className="modal-content">
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  createCatMutation.mutate(catForm);
                }}
              >
                <div className="modal-header">
                  <h5 className="modal-title">{t('নতুন ক্ষয়ক্ষতি খাত যোগ করুন', 'Add Configurable Damage Category')}</h5>
                  <button type="button" className="btn-close" onClick={() => setShowCatModal(false)}></button>
                </div>
                <div className="modal-body">
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('কোড (Code)', 'Code')} *</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder="e.g. RIVER_EMBANKMENT"
                      value={catForm.code}
                      onChange={(e) => setCatForm({ ...catForm, code: e.target.value })}
                      required
                    />
                  </div>
                  <div className="row g-2 mb-2">
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('ইংরেজি নাম', 'Name (English)')} *</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={catForm.name_en}
                        onChange={(e) => setCatForm({ ...catForm, name_en: e.target.value })}
                        required
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('বাংলা নাম', 'Name (Bangla)')} *</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={catForm.name_bn}
                        onChange={(e) => setCatForm({ ...catForm, name_bn: e.target.value })}
                        required
                      />
                    </div>
                  </div>
                  <div className="row g-2 mb-2">
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('পরিমাপের একক', 'Default Unit')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={catForm.default_unit}
                        onChange={(e) => setCatForm({ ...catForm, default_unit: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('রং', 'Color')}</label>
                      <input
                        type="color"
                        className="form-control form-control-color form-control-sm w-100"
                        value={catForm.color_hex}
                        onChange={(e) => setCatForm({ ...catForm, color_hex: e.target.value })}
                      />
                    </div>
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('বিবরণ', 'Description')}</label>
                    <textarea
                      className="form-control form-control-sm"
                      rows="2"
                      value={catForm.description}
                      onChange={(e) => setCatForm({ ...catForm, description: e.target.value })}
                    />
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-sm btn-secondary" onClick={() => setShowCatModal(false)}>
                    {t('বাতিল', 'Cancel')}
                  </button>
                  <button type="submit" className="btn btn-sm btn-primary" disabled={createCatMutation.isPending}>
                    {createCatMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('সংরক্ষণ করুন', 'Save Category')}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default DamageDashboardPage;
