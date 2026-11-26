import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { damageApi } from '../../../api/damageApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canExportDamage,
  formatBdt,
  formatDamageApiError,
  isExternalPartner,
  triggerBlobDownload,
} from '../utils/damagePermissions';
import { DamageSeverityBadge, DamageStatusBadge } from '../components/DamageStatusBadges';

export const DamageReportsPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();

  const [activeTab, setActiveTab] = useState('disaster'); // 'disaster' | 'location' | 'category' | 'financial'
  const [disasterId, setDisasterId] = useState('');
  const [includeUnverified, setIncludeUnverified] = useState(false);
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['damage', 'lookups'],
    queryFn: () => damageApi.getLookups(),
  });

  const { data: reportRes, isLoading, error } = useQuery({
    queryKey: ['damage', 'reports', activeTab, disasterId, includeUnverified],
    queryFn: () =>
      damageApi.getReports({
        type: activeTab,
        ...(disasterId ? { disaster_event_id: disasterId } : {}),
        ...(includeUnverified ? { include_unverified: true } : {}),
      }),
  });

  const handleExport = async () => {
    try {
      const res = await damageApi.exportCsv({
        ...(disasterId ? { disaster_event_id: disasterId } : {}),
      });
      triggerBlobDownload(res.data, `idms-damage-${activeTab}-report.csv`);
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatDamageApiError(err, t('এক্সপোর্ট ব্যর্থ।', 'Export failed.')) });
    }
  };

  if (isLoading) return <Loader />;

  const data = reportRes?.data || {};
  const disasters = lookupsRes?.data?.disasters || [];
  const disasterWise = data.disaster_wise || [];
  const locationWise = data.location_wise || [];
  const categoryWise = data.category_wise || [];
  const financialRows = data.financial_loss || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-file-earmark-bar-graph-fill text-danger me-2"></i>
            {t('ক্ষয়ক্ষতি ও আর্থিক লোকসান প্রতিবেদন (M09)', 'Damage & Financial Loss Reports (M09)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'দুর্যোগ-ভিত্তিক, এলাকা-ভিত্তিক, খাত-ভিত্তিক ও আর্থিক ক্ষয়ক্ষতির আনুষ্ঠানিক প্রতিবেদন (শুধুমাত্র যাচাইকৃত ও অনুমোদিত)',
              'Disaster-wise, location-wise, category-wise, and financial loss reports (verified & approved assessments)'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2 align-items-center">
          <select
            className="form-select form-select-sm"
            style={{ width: '200px' }}
            value={disasterId}
            onChange={(e) => setDisasterId(e.target.value)}
          >
            <option value="">{t('সকল দুর্যোগ', 'All Disasters')}</option>
            {disasters.map((d) => (
              <option key={d.id} value={d.id}>{t(d.name_bn, d.name_en)}</option>
            ))}
          </select>

          {!isExternalPartner(user) && (
            <div className="form-check form-switch mb-0">
              <input
                className="form-check-input"
                type="checkbox"
                id="includeUnverifiedSwitch"
                checked={includeUnverified}
                onChange={(e) => setIncludeUnverified(e.target.checked)}
              />
              <label className="form-check-label small" htmlFor="includeUnverifiedSwitch">
                {t('অযাচাইকৃত সহ', 'Include Unverified')}
              </label>
            </div>
          )}

          {canExportDamage(user) && (
            <button type="button" className="btn btn-sm btn-outline-success" onClick={handleExport}>
              <i className="bi bi-download me-1"></i>
              {t('সিএসভি এক্সপোর্ট', 'Export CSV')}
            </button>
          )}
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}
      {error && (
        <AlertBanner variant="danger" message={formatDamageApiError(error, t('প্রতিবেদন লোড ব্যর্থ।', 'Failed to load reports.'))} />
      )}

      <div className="row g-3 mb-3">
        <div className="col-md-4">
          <div className="card border-0 shadow-sm p-3">
            <div className="small text-muted">{t('প্রতিবেদনে অন্তর্ভুক্ত ডি-ফর্ম', 'Assessments in Report')}</div>
            <div className="fs-4 fw-bold text-primary">{data.total_assessments || 0}</div>
          </div>
        </div>
        <div className="col-md-4">
          <div className="card border-0 shadow-sm p-3">
            <div className="small text-muted">{t('মোট আনুমানিক আর্থিক ক্ষতি', 'Total Estimated Loss')}</div>
            <div className="fs-4 fw-bold text-danger">{formatBdt(data.total_estimated_loss_bdt)}</div>
          </div>
        </div>
        <div className="col-md-4">
          <div className="card border-0 shadow-sm p-3">
            <div className="small text-muted">{t('প্রতিবেদন মানদণ্ড', 'Reporting Basis')}</div>
            <div className="fs-6 fw-semibold text-success">
              {data.verified_only
                ? t('শুধুমাত্র যাচাইকৃত ও অনুমোদিত', 'Verified & Approved Only')
                : t('সকল নিরূপণ অন্তর্ভুক্ত', 'All Assessments Included')}
            </div>
          </div>
        </div>
      </div>

      {/* Report Tabs */}
      <ul className="nav nav-tabs mb-3">
        <li className="nav-item">
          <button
            type="button"
            className={`nav-link ${activeTab === 'disaster' ? 'active fw-semibold' : ''}`}
            onClick={() => setActiveTab('disaster')}
          >
            {t('দুর্যোগ-ভিত্তিক প্রতিবেদন', 'Disaster-wise Report')}
          </button>
        </li>
        <li className="nav-item">
          <button
            type="button"
            className={`nav-link ${activeTab === 'location' ? 'active fw-semibold' : ''}`}
            onClick={() => setActiveTab('location')}
          >
            {t('এলাকা-ভিত্তিক প্রতিবেদন', 'Location-wise Report')}
          </button>
        </li>
        <li className="nav-item">
          <button
            type="button"
            className={`nav-link ${activeTab === 'category' ? 'active fw-semibold' : ''}`}
            onClick={() => setActiveTab('category')}
          >
            {t('খাত-ভিত্তিক প্রতিবেদন', 'Category-wise Assessment')}
          </button>
        </li>
        <li className="nav-item">
          <button
            type="button"
            className={`nav-link ${activeTab === 'financial' ? 'active fw-semibold' : ''}`}
            onClick={() => setActiveTab('financial')}
          >
            {t('আর্থিক ক্ষয়ক্ষতি বিবরণী', 'Estimated Financial Loss')}
          </button>
        </li>
      </ul>

      <div className="card border-0 shadow-sm">
        <div className="table-responsive">
          {activeTab === 'disaster' && (
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('দুর্যোগ', 'Disaster Event')}</th>
                  <th className="text-end">{t('নিরূপণ', 'D-Forms')}</th>
                  <th className="text-end">{t('ক্ষতিগ্রস্ত পরিবার', 'Affected HH')}</th>
                  <th className="text-end">{t('জনসংখ্যা', 'Population')}</th>
                  <th className="text-end">{t('সম্পূর্ণ', 'Full')}</th>
                  <th className="text-end">{t('আংশিক', 'Partial')}</th>
                  <th className="text-end">{t('মোট আর্থিক ক্ষতি', 'Total Loss (BDT)')}</th>
                </tr>
              </thead>
              <tbody>
                {disasterWise.map((row) => (
                  <tr key={row.disaster_event_id}>
                    <td>
                      <div className="fw-semibold">{t(row.name_bn, row.name_en)}</div>
                      <div className="small text-muted">{row.disaster_code}</div>
                    </td>
                    <td className="text-end">{row.assessment_count}</td>
                    <td className="text-end">{Number(row.affected_households || 0).toLocaleString()}</td>
                    <td className="text-end">{Number(row.affected_population || 0).toLocaleString()}</td>
                    <td className="text-end">{row.total_fully_damaged}</td>
                    <td className="text-end">{row.total_partially_damaged}</td>
                    <td className="text-end fw-bold text-danger">{formatBdt(row.total_estimated_loss_bdt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {activeTab === 'location' && (
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('প্রশাসনিক এলাকা', 'Administrative Unit')}</th>
                  <th>{t('স্তর', 'Tier')}</th>
                  <th className="text-center">{t('তীব্রতা', 'Max Severity')}</th>
                  <th className="text-end">{t('নিরূপণ', 'Assessments')}</th>
                  <th className="text-end">{t('পরিবার', 'Households')}</th>
                  <th className="text-end">{t('মোট আর্থিক ক্ষতি', 'Est. Loss (BDT)')}</th>
                </tr>
              </thead>
              <tbody>
                {locationWise.map((row) => (
                  <tr key={row.administrative_unit_id}>
                    <td className="fw-semibold">{t(row.name_bn, row.name_en)}</td>
                    <td>{row.tier_level}</td>
                    <td className="text-center"><DamageSeverityBadge severity={row.max_severity} /></td>
                    <td className="text-end">{row.assessment_count}</td>
                    <td className="text-end">{Number(row.affected_households || 0).toLocaleString()}</td>
                    <td className="text-end fw-bold text-danger">{formatBdt(row.total_estimated_loss_bdt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {activeTab === 'category' && (
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('ক্ষয়ক্ষতির খাত', 'Damage Category')}</th>
                  <th className="text-end">{t('এন্ট্রি সংখ্যা', 'Entries')}</th>
                  <th className="text-end">{t('সম্পূর্ণ ক্ষতিগ্রস্ত', 'Fully Damaged')}</th>
                  <th className="text-end">{t('আংশিক ক্ষতিগ্রস্ত', 'Partially Damaged')}</th>
                  <th className="text-end">{t('সামান্য ক্ষতি', 'Minor Damage')}</th>
                  <th className="text-end">{t('আনুমানিক মেরামত/ক্ষতি', 'Est. Repair / Loss')}</th>
                </tr>
              </thead>
              <tbody>
                {categoryWise.map((row) => (
                  <tr key={row.code}>
                    <td className="fw-semibold">{t(row.name_bn, row.name_en)}</td>
                    <td className="text-end">{row.assessment_lines}</td>
                    <td className="text-end">{row.fully_damaged_count}</td>
                    <td className="text-end">{row.partially_damaged_count}</td>
                    <td className="text-end">{row.minor_damaged_count}</td>
                    <td className="text-end fw-bold text-danger">{formatBdt(row.estimated_loss_bdt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {activeTab === 'financial' && (
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('ডি-ফর্ম কোড', 'D-Form Code')}</th>
                  <th>{t('দুর্যোগ', 'Disaster')}</th>
                  <th>{t('এলাকা', 'Location')}</th>
                  <th>{t('তারিখ', 'Date')}</th>
                  <th className="text-center">{t('অবস্থা', 'Status')}</th>
                  <th className="text-end">{t('মোট ক্ষতি (টাকা)', 'Estimated Loss (BDT)')}</th>
                </tr>
              </thead>
              <tbody>
                {financialRows.map((row) => (
                  <tr key={row.id}>
                    <td className="fw-semibold">{row.d_form_code}</td>
                    <td>{t(row.disaster_name_bn, row.disaster_name_en)}</td>
                    <td>{t(row.administrative_unit_bn, row.administrative_unit)}</td>
                    <td>{row.assessment_date}</td>
                    <td className="text-center"><DamageStatusBadge status={row.verification_status} /></td>
                    <td className="text-end fw-bold text-danger">{formatBdt(row.total_estimated_loss_bdt)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
};

export default DamageReportsPage;
