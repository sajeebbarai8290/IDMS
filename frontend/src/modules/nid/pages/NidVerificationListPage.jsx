import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link, useSearchParams } from 'react-router-dom';
import nidApi from '../../../api/nidApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import { canInitiateNid, canManualReviewNid, hasManualReviewRole, isSelfRequestedNid } from '../utils/nidPermissions';
import NidStatusBadge from '../components/NidStatusBadge';
import NidManualReviewModal from '../components/NidManualReviewModal';
import Loader from '../../../components/common/Loader';
import EmptyState from '../../../components/common/EmptyState';
import AlertBanner from '../../../components/common/AlertBanner';

export const NidVerificationListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();

  const [filters, setFilters] = useState({
    search: '',
    verification_status: searchParams.get('verification_status') || '',
    is_mock: '',
    manual_override: '',
    page: 1,
    per_page: 15,
  });

  const [reviewTarget, setReviewTarget] = useState(null);
  const [reviewError, setReviewError] = useState(null);

  const { data, isLoading, error } = useQuery({
    queryKey: ['nidVerifications', filters],
    queryFn: async () => {
      const cleanParams = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined)
      );
      return await nidApi.getVerifications(cleanParams);
    },
    keepPreviousData: true,
  });

  const reviewMutation = useMutation({
    mutationFn: ({ id, payload }) => nidApi.manualReview(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['nidVerifications']);
      queryClient.invalidateQueries(['nidDashboard']);
      setReviewTarget(null);
      setReviewError(null);
    },
    onError: (err) => {
      setReviewError(err?.message || 'Manual review failed.');
    },
  });

  const items = data?.data || [];
  const pagination = data?.pagination || {};

  const resetFilters = () => {
    setFilters({
      search: '',
      verification_status: '',
      is_mock: '',
      manual_override: '',
      page: 1,
      per_page: 15,
    });
  };

  return (
    <div className="container-fluid py-2">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-clock-history text-primary me-2"></i>
            {t('এনআইডি যাচাইকরণ ইতিহাস (Verification History)', 'NID Verification History')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'আপনার আওতাধীন প্রশাসনিক এলাকার সকল এনআইডি যাচাই প্রচেষ্টা ও ম্যানুয়াল রিভিউ রেকর্ড',
              'Scoped audit history of NID verification attempts and manual officer overrides'
            )}
          </p>
        </div>

        <div className="d-flex gap-2">
          <Link to="/nid/dashboard" className="btn btn-outline-secondary btn-sm">
            {t('ড্যাশবোর্ড', 'Dashboard')}
          </Link>
          {canInitiateNid(user) && (
            <Link to="/nid/verify" className="btn btn-primary btn-sm shadow-sm">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন যাচাই', 'Verify New NID')}
            </Link>
          )}
        </div>
      </div>

      {/* Filter Bar */}
      <div className="card shadow-sm border-0 mb-3">
        <div className="card-body py-3">
          <div className="row g-2 align-items-end">
            <div className="col-md-4">
              <label className="form-label small fw-bold mb-1">{t('অনুসন্ধান', 'Search')}</label>
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('রেফারেন্স, মাস্কড বা পূর্ণ এনআইডি, নাম...', 'Reference, masked or full NID, name...')}
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value, page: 1 })}
              />
            </div>

            <div className="col-md-3">
              <label className="form-label small fw-bold mb-1">{t('অবস্থা', 'Status')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.verification_status}
                onChange={(e) => setFilters({ ...filters, verification_status: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
                <option value="VERIFIED">VERIFIED</option>
                <option value="FAILED">FAILED</option>
                <option value="MANUAL_REVIEW_REQUIRED">MANUAL_REVIEW_REQUIRED</option>
                <option value="PROCESSING">PROCESSING</option>
                <option value="PENDING">PENDING</option>
              </select>
            </div>

            <div className="col-md-2">
              <label className="form-label small fw-bold mb-1">{t('মোড', 'Provider Mode')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.is_mock}
                onChange={(e) => setFilters({ ...filters, is_mock: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল', 'All')}</option>
                <option value="1">{t('মক (Mock)', 'Mock Only')}</option>
                <option value="0">{t('সরকারি (Official)', 'Official Only')}</option>
              </select>
            </div>

            <div className="col-md-2">
              <label className="form-label small fw-bold mb-1">{t('ম্যানুয়াল রিভিউ', 'Manual Override')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.manual_override}
                onChange={(e) => setFilters({ ...filters, manual_override: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল', 'All')}</option>
                <option value="1">{t('হ্যাঁ (Overridden)', 'Yes')}</option>
                <option value="0">{t('না (Automated)', 'No')}</option>
              </select>
            </div>

            <div className="col-md-1 d-grid">
              <button type="button" className="btn btn-outline-secondary btn-sm" onClick={resetFilters}>
                {t('রিসেট', 'Reset')}
              </button>
            </div>
          </div>
        </div>
      </div>

      {isLoading && <Loader message="যাচাই ইতিহাস লোড হচ্ছে..." messageEn="Loading verification records..." />}

      {error && (
        <AlertBanner
          type="danger"
          message={t('ইতিহাস লোড করতে ব্যর্থ হয়েছে: ', 'Failed to load verification history: ')}
          details={error?.message}
        />
      )}

      {!isLoading && !error && items.length === 0 && (
        <EmptyState
          icon="bi-person-vcard"
          title={t('কোনো এনআইডি যাচাই রেকর্ড পাওয়া যায়নি', 'No NID verification records found')}
          titleEn="No records match your current search or filter"
          description={t(
            'ফিল্টার পরিবর্তন করুন অথবা নতুন এনআইডি যাচাই শুরু করুন।',
            'Adjust filters or initiate a new NID verification request.'
          )}
        />
      )}

      {!isLoading && !error && items.length > 0 && (
        <div className="card shadow-sm border-0">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light small">
                <tr>
                  <th>{t('রেফারেন্স', 'Reference')}</th>
                  <th>{t('মাস্কড এনআইডি', 'Masked NID')}</th>
                  <th>{t('পরিবার / এলাকা', 'Household / Unit')}</th>
                  <th>{t('অবস্থা', 'Status')}</th>
                  <th className="text-center">{t('প্রচেষ্টা', 'Attempts')}</th>
                  <th>{t('অনুরোধকারী', 'Requested By')}</th>
                  <th>{t('সময়', 'Timestamp')}</th>
                  <th className="text-end">{t('অ্যাকশন', 'Actions')}</th>
                </tr>
              </thead>
              <tbody className="small">
                {items.map((item) => (
                  <tr key={item.id}>
                    <td className="font-monospace fw-bold">
                      <Link to={`/nid/verifications/${item.id}`} className="text-decoration-none">
                        {item.verification_reference || item.id.slice(0, 8)}
                      </Link>
                    </td>
                    <td className="font-monospace">{item.nid_masked}</td>
                    <td>
                      {item.household ? (
                        <Link to={`/beneficiary/households/${item.household.id}`} className="text-decoration-none">
                          {item.household.household_code}
                        </Link>
                      ) : (
                        <span className="text-muted">
                          {item.administrative_unit?.name_bn || item.administrative_unit?.name_en || '—'}
                        </span>
                      )}
                    </td>
                    <td>
                      <NidStatusBadge
                        status={item.verification_status}
                        isMock={item.is_mock}
                        manualOverride={item.manual_override}
                      />
                    </td>
                    <td className="text-center">{item.attempt_count}</td>
                    <td>{item.requested_by || item.verified_by || '—'}</td>
                    <td>{item.created_at ? new Date(item.created_at).toLocaleString() : '—'}</td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <Link to={`/nid/verifications/${item.id}`} className="btn btn-outline-primary">
                          {t('বিস্তারিত', 'Details')}
                        </Link>
                        {canManualReviewNid(user, item) && item.verification_status !== 'PROCESSING' && (
                          <button
                            type="button"
                            className="btn btn-outline-warning"
                            onClick={() => {
                              setReviewError(null);
                              setReviewTarget(item);
                            }}
                          >
                            {t('রিভিউ', 'Review')}
                          </button>
                        )}
                        {hasManualReviewRole(user) && isSelfRequestedNid(user, item) && (
                          <span
                            className="btn btn-outline-secondary disabled"
                            title={t(
                              '৪-চোখ নীতি: আপনার নিজস্ব অনুরোধ অন্য কর্মকর্তা রিভিউ করবেন',
                              '4-Eye SoD: Another officer must review your own request'
                            )}
                          >
                            {t('৪-চোখ নীতি', '4-Eye SoD')}
                          </span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pagination.last_page > 1 && (
            <div className="card-footer bg-white d-flex justify-content-between align-items-center small">
              <span className="text-muted">
                {t(
                  `পৃষ্ঠা ${pagination.current_page} / ${pagination.last_page} (মোট ${pagination.total})`,
                  `Page ${pagination.current_page} of ${pagination.last_page} (${pagination.total} total)`
                )}
              </span>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={pagination.current_page <= 1}
                  onClick={() => setFilters({ ...filters, page: pagination.current_page - 1 })}
                >
                  {t('পূর্ববর্তী', 'Previous')}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={pagination.current_page >= pagination.last_page}
                  onClick={() => setFilters({ ...filters, page: pagination.current_page + 1 })}
                >
                  {t('পরবর্তী', 'Next')}
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      <NidManualReviewModal
        isOpen={!!reviewTarget}
        verification={reviewTarget}
        onClose={() => setReviewTarget(null)}
        onSubmit={(payload) => reviewMutation.mutate({ id: reviewTarget.id, payload })}
        isSubmitting={reviewMutation.isPending}
        errorMessage={reviewError}
      />
    </div>
  );
};

export default NidVerificationListPage;
