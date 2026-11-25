import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import nidApi from '../../../api/nidApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import { canInitiateNid } from '../utils/nidPermissions';
import NidProviderBanner from '../components/NidProviderBanner';
import NidStatusBadge from '../components/NidStatusBadge';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';

export const NidDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();

  const { data, isLoading, error, refetch, isFetching } = useQuery({
    queryKey: ['nidDashboard'],
    queryFn: async () => {
      const res = await nidApi.getDashboard();
      return res?.data || res || {};
    },
    refetchInterval: 30000,
  });

  if (isLoading) {
    return (
      <Loader
        message="এনআইডি যাচাই ড্যাশবোর্ড লোড হচ্ছে..."
        messageEn="Loading NID Verification Dashboard..."
      />
    );
  }

  if (error) {
    return (
      <AlertBanner
        type="danger"
        message={t('ড্যাশবোর্ড ডেটা লোড করতে ব্যর্থ হয়েছে: ', 'Failed to load NID dashboard: ')}
        details={error?.message}
      />
    );
  }

  const summary = data?.summary || {};
  const provider = data?.provider_health || null;
  const telemetry = data?.telemetry || {};
  const recentErrors = telemetry?.recent_errors || [];

  return (
    <div className="container-fluid py-2">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-person-vcard-fill text-primary me-2"></i>
            {t('জাতীয় পরিচয়পত্র (NID) যাচাই ড্যাশবোর্ড', 'National ID (NID) Verification Dashboard')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'উপকারভোগী পরিচয় যাচাই, গেটওয়ে প্রাপ্যতা ও প্রচেষ্টা পর্যবেক্ষণ • দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয়',
              'Beneficiary identity verification, gateway availability & attempt telemetry • MoDMR'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <button
            type="button"
            className="btn btn-outline-secondary btn-sm"
            onClick={() => refetch()}
            disabled={isFetching}
          >
            <i className="bi bi-arrow-clockwise me-1"></i>
            {t('রিফ্রেশ', 'Refresh')}
          </button>
          <Link to="/nid/verifications" className="btn btn-outline-primary btn-sm">
            <i className="bi bi-clock-history me-1"></i>
            {t('যাচাই ইতিহাস', 'Verification History')}
          </Link>
          {canInitiateNid(user) && (
            <Link to="/nid/verify" className="btn btn-primary btn-sm shadow-sm">
              <i className="bi bi-patch-check me-1"></i>
              {t('নতুন এনআইডি যাচাই', 'Verify New NID')}
            </Link>
          )}
        </div>
      </div>

      <NidProviderBanner provider={provider} />

      {/* KPI Cards */}
      <div className="row g-3 mb-4">
        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-primary h-100">
            <div className="card-body p-3 d-flex justify-content-between align-items-center">
              <div>
                <small className="text-muted fw-bold text-uppercase">{t('মোট অনুরোধ', 'Total Requests')}</small>
                <h3 className="fw-bold mb-0">{summary.total_requests ?? 0}</h3>
                <small className="text-muted">
                  {t(`সফলতার হার: ${telemetry.success_rate_percent ?? 0}%`, `Success rate: ${telemetry.success_rate_percent ?? 0}%`)}
                </small>
              </div>
              <div className="rounded-circle bg-primary-subtle text-primary p-3">
                <i className="bi bi-card-checklist fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-success h-100">
            <div className="card-body p-3 d-flex justify-content-between align-items-center">
              <div>
                <small className="text-muted fw-bold text-uppercase">{t('সফল যাচাই', 'Verified')}</small>
                <h3 className="fw-bold mb-0 text-success">{summary.verified_count ?? 0}</h3>
                <small className="text-muted">
                  {t(`ওভাররাইডসহ: ${summary.manual_override_count ?? 0}`, `Manual overrides: ${summary.manual_override_count ?? 0}`)}
                </small>
              </div>
              <div className="rounded-circle bg-success-subtle text-success p-3">
                <i className="bi bi-patch-check-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-danger h-100">
            <div className="card-body p-3 d-flex justify-content-between align-items-center">
              <div>
                <small className="text-muted fw-bold text-uppercase">{t('ব্যর্থ যাচাই', 'Failed')}</small>
                <h3 className="fw-bold mb-0 text-danger">{summary.failed_count ?? 0}</h3>
                <small className="text-muted">
                  {t(`ত্রুটি প্রচেষ্টা: ${telemetry.error_attempts ?? 0}`, `Error attempts: ${telemetry.error_attempts ?? 0}`)}
                </small>
              </div>
              <div className="rounded-circle bg-danger-subtle text-danger p-3">
                <i className="bi bi-x-octagon-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-warning h-100">
            <div className="card-body p-3 d-flex justify-content-between align-items-center">
              <div>
                <small className="text-muted fw-bold text-uppercase">{t('অপেক্ষমাণ ও রিভিউ', 'Pending & Review')}</small>
                <h3 className="fw-bold mb-0 text-warning-emphasis">
                  {(summary.pending_count ?? 0) + (summary.manual_review_count ?? 0)}
                </h3>
                <small className="text-muted">
                  {t(
                    `অপেক্ষমাণ: ${summary.pending_count ?? 0} • রিভিউ: ${summary.manual_review_count ?? 0}`,
                    `Pending: ${summary.pending_count ?? 0} • Review: ${summary.manual_review_count ?? 0}`
                  )}
                </small>
              </div>
              <div className="rounded-circle bg-warning-subtle text-warning-emphasis p-3">
                <i className="bi bi-hourglass-split fs-3"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Provider Availability & Telemetry Row */}
      <div className="row g-3 mb-4">
        <div className="col-lg-5">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              <i className="bi bi-hdd-network me-2 text-primary"></i>
              {t('API সার্ভিস প্রাপ্যতা ও কর্মক্ষমতা', 'API Service Availability & Telemetry')}
            </div>
            <div className="card-body">
              <div className="d-flex justify-content-between py-2 border-bottom">
                <span className="text-muted small">{t('সক্রিয় প্রোভাইডার', 'Active Provider')}</span>
                <span className="fw-bold font-monospace">{provider?.provider_code || '—'}</span>
              </div>
              <div className="d-flex justify-content-between py-2 border-bottom">
                <span className="text-muted small">{t('অপারেশন মোড', 'Operation Mode')}</span>
                <span className={`badge ${provider?.is_mock ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success'}`}>
                  {provider?.is_mock ? t('ডেভেলপমেন্ট মক (Mock)', 'Development Mock') : t('সরকারি গেটওয়ে', 'Official Gateway')}
                </span>
              </div>
              <div className="d-flex justify-content-between py-2 border-bottom">
                <span className="text-muted small">{t('সার্ভিস স্ট্যাটাস', 'Availability Status')}</span>
                <span className={`badge ${provider?.available ? 'bg-success' : 'bg-danger'}`}>
                  {provider?.available ? t('সচল (Available)', 'Available') : t('অনুপলব্ধ (Unavailable)', 'Unavailable')}
                </span>
              </div>
              <div className="d-flex justify-content-between py-2 border-bottom">
                <span className="text-muted small">{t('মোট প্রোভাইডার প্রচেষ্টা', 'Total Attempts')}</span>
                <span className="fw-bold">{telemetry.total_attempts ?? 0}</span>
              </div>
              <div className="d-flex justify-content-between py-2">
                <span className="text-muted small">{t('গড় রেসপন্স সময়', 'Average Latency')}</span>
                <span className="fw-bold">{telemetry.avg_response_time_ms ?? 0} ms</span>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-7">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
              <span>
                <i className="bi bi-exclamation-circle me-2 text-danger"></i>
                {t('সাম্প্রতিক ব্যর্থতা ও পর্যালোচনা প্রয়োজন', 'Recent Errors & Manual Review Flags')}
              </span>
              <Link to="/nid/verifications?verification_status=MANUAL_REVIEW_REQUIRED" className="btn btn-sm btn-outline-warning">
                {t('রিভিউ কিউ দেখুন', 'View Review Queue')}
              </Link>
            </div>
            <div className="card-body p-0">
              {recentErrors.length === 0 ? (
                <div className="p-3">
                  <EmptyState
                    icon="bi-check2-circle"
                    title={t('কোনো সাম্প্রতিক ত্রুটি নেই', 'No recent provider errors')}
                    titleEn="All recent verification attempts succeeded"
                    description={t(
                      'এই মুহূর্তে কোনো ব্যর্থ বা টাইমআউট প্রচেষ্টা রেকর্ড করা হয়নি।',
                      'No failed or timed-out attempts recorded in your jurisdiction.'
                    )}
                  />
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover table-sm align-middle mb-0">
                    <thead className="table-light small">
                      <tr>
                        <th>{t('রেফারেন্স', 'Reference')}</th>
                        <th>{t('মাস্কড এনআইডি', 'Masked NID')}</th>
                        <th>{t('অবস্থা', 'Status')}</th>
                        <th>{t('ত্রুটি কোড', 'Error Code')}</th>
                        <th>{t('সময়', 'Latency')}</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody className="small">
                      {recentErrors.map((errItem) => (
                        <tr key={errItem.id}>
                          <td className="font-monospace fw-semibold">{errItem.verification_reference}</td>
                          <td className="font-monospace">{errItem.nid_masked}</td>
                          <td>
                            <NidStatusBadge status={errItem.status} isMock={errItem.is_mock} />
                          </td>
                          <td className="font-monospace text-danger">{errItem.error_code || '—'}</td>
                          <td>{errItem.response_time_ms ? `${errItem.response_time_ms} ms` : '—'}</td>
                          <td className="text-end">
                            <Link
                              to={`/nid/verifications/${errItem.nid_verification_id}`}
                              className="btn btn-outline-primary btn-sm py-0 px-2"
                            >
                              {t('বিস্তারিত', 'Inspect')}
                            </Link>
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
    </div>
  );
};

export default NidDashboardPage;
