import React, { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import nidApi from '../../../api/nidApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import { canInitiateNid, canManualReviewNid, hasManualReviewRole, isSelfRequestedNid, isValidBangladeshNid } from '../utils/nidPermissions';
import NidStatusBadge from '../components/NidStatusBadge';
import NidManualReviewModal from '../components/NidManualReviewModal';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';

export const NidVerificationDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [reviewModalOpen, setReviewModalOpen] = useState(false);
  const [reviewError, setReviewError] = useState(null);
  const [retryNid, setRetryNid] = useState('');
  const [retryError, setRetryError] = useState('');

  const { data: v, isLoading, error } = useQuery({
    queryKey: ['nidVerification', id],
    queryFn: async () => {
      const res = await nidApi.getVerification(id);
      return res?.verification || res?.data?.verification || res || null;
    },
  });

  const reviewMutation = useMutation({
    mutationFn: (payload) => nidApi.manualReview(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['nidVerification', id]);
      queryClient.invalidateQueries(['nidVerifications']);
      queryClient.invalidateQueries(['nidDashboard']);
      setReviewModalOpen(false);
      setReviewError(null);
    },
    onError: (err) => {
      setReviewError(err?.message || 'Failed to record manual review.');
    },
  });

  const retryMutation = useMutation({
    mutationFn: (payload) => nidApi.retryVerification(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['nidVerification', id]);
      queryClient.invalidateQueries(['nidVerifications']);
      queryClient.invalidateQueries(['nidDashboard']);
      setRetryNid('');
      setRetryError('');
    },
    onError: (err) => {
      setRetryError(err?.message || 'Retry failed.');
    },
  });

  if (isLoading) {
    return <Loader message="যাচাই বিবরণ লোড হচ্ছে..." messageEn="Loading verification details..." />;
  }

  if (error || !v) {
    return (
      <div className="container py-3">
        <AlertBanner
          type="danger"
          message={t('তথ্য লোড করা যায়নি: ', 'Failed to load verification record: ')}
          details={error?.message || 'Not found'}
        />
        <Link to="/nid/verifications" className="btn btn-secondary btn-sm mt-2">
          {t('ইতিহাসে ফিরে যান', 'Back to History')}
        </Link>
      </div>
    );
  }

  const canRetry = canInitiateNid(user) && ['FAILED', 'MANUAL_REVIEW_REQUIRED'].includes(v.verification_status);
  const canReview = canManualReviewNid(user, v) && v.verification_status !== 'PROCESSING';
  const blockedBySod = hasManualReviewRole(user) && isSelfRequestedNid(user, v);
  const attempts = v.attempts || [];

  const handleRetrySubmit = (e) => {
    e.preventDefault();
    if (!isValidBangladeshNid(retryNid)) {
      setRetryError(t('সঠিক ১০, ১৩ অথবা ১৭ সংখ্যার এনআইডি দিন।', 'Enter valid 10, 13, or 17 digit NID.'));
      return;
    }
    setRetryError('');
    retryMutation.mutate({ nid_number: retryNid.trim() });
  };

  return (
    <div className="container-fluid py-2" style={{ maxWidth: '1100px' }}>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <nav aria-label="breadcrumb">
            <ol className="breadcrumb mb-1 small">
              <li className="breadcrumb-item">
                <Link to="/nid/dashboard">{t('এনআইডি ড্যাশবোর্ড', 'NID Dashboard')}</Link>
              </li>
              <li className="breadcrumb-item">
                <Link to="/nid/verifications">{t('ইতিহাস', 'History')}</Link>
              </li>
              <li className="breadcrumb-item active font-monospace">{v.verification_reference}</li>
            </ol>
          </nav>
          <h4 className="fw-bold mb-0 font-monospace">{v.verification_reference}</h4>
        </div>

        <div className="d-flex gap-2 align-items-center">
          <NidStatusBadge
            status={v.verification_status}
            isMock={v.is_mock}
            manualOverride={v.manual_override}
          />
          {canReview && (
            <button
              type="button"
              className="btn btn-warning btn-sm fw-semibold"
              onClick={() => {
                setReviewError(null);
                setReviewModalOpen(true);
              }}
            >
              <i className="bi bi-person-check me-1"></i>
              {t('ম্যানুয়াল রিভিউ', 'Manual Review')}
            </button>
          )}
        </div>
      </div>

      {blockedBySod && (
        <div className="alert alert-info py-2 small mb-3">
          <i className="bi bi-shield-lock-fill me-2"></i>
          {t(
            '৪-চোখ পৃথকীকরণ নীতি (Separation of Duties): এই এনআইডি যাচাই অনুরোধটি আপনি নিজেই শুরু করেছেন, তাই নিয়ম অনুযায়ী অন্য একজন অনুমোদিত কর্মকর্তা (PIO/UNO/DRRO/DC/Super Admin) এর ম্যানুয়াল রিভিউ সম্পন্ন করবেন।',
            'Separation of Duties (4-Eye Rule): Because you initiated this verification request, a different authorized officer (PIO/UNO/DRRO/DC/Super Admin) must perform the manual review.'
          )}
        </div>
      )}

      {v.is_mock && (
        <div className="alert alert-warning py-2 small mb-3">
          <i className="bi bi-info-circle-fill me-2"></i>
          {t(
            'নোট: এই রেকর্ডটি ডেভেলপমেন্ট মক অ্যাডাপ্টার দ্বারা প্রক্রিয়াকৃত — এটি সরকারি নির্বাচন কমিশন যাচাই নয়।',
            'Note: This record was processed by the Development Mock Adapter and is NOT a genuine government verification.'
          )}
        </div>
      )}

      <div className="row g-3 mb-4">
        <div className="col-lg-7">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              {t('যাচাই সারসংক্ষেপ (ন্যূনতম সুরক্ষিত তথ্য)', 'Verification Summary (Minimized Protected Fields)')}
            </div>
            <div className="card-body small">
              <div className="row g-3">
                <div className="col-sm-6">
                  <div className="text-muted">{t('মাস্কড এনআইডি', 'Masked NID')}</div>
                  <div className="fw-bold font-monospace fs-6">{v.nid_masked}</div>
                </div>
                <div className="col-sm-6">
                  <div className="text-muted">{t('জন্ম তারিখ', 'Date of Birth')}</div>
                  <div className="fw-bold">{v.date_of_birth || '—'}</div>
                </div>
                <div className="col-sm-6">
                  <div className="text-muted">{t('জমা দেওয়া নাম', 'Submitted Name')}</div>
                  <div className="fw-bold">{v.submitted_name_en || '—'}</div>
                </div>
                <div className="col-sm-6">
                  <div className="text-muted">{t('প্রশাসনিক এলাকা', 'Administrative Unit')}</div>
                  <div className="fw-bold">
                    {v.administrative_unit?.name_bn || v.administrative_unit?.name_en || '—'}
                  </div>
                </div>
                <div className="col-sm-6">
                  <div className="text-muted">{t('ভেরিফিকেশন টোকেন', 'Verification Token')}</div>
                  <div className="font-monospace">{v.verification_token || '—'}</div>
                </div>
                <div className="col-sm-6">
                  <div className="text-muted">{t('প্রোভাইডার', 'Provider')}</div>
                  <div className="font-monospace">{v.provider_code}</div>
                </div>
                <div className="col-sm-6">
                  <div className="text-muted">{t('অনুরোধকারী কর্মকর্তা', 'Requested By')}</div>
                  <div>{v.requested_by || '—'}</div>
                </div>
                <div className="col-sm-6">
                  <div className="text-muted">{t('যাচাই সময়', 'Verified Timestamp')}</div>
                  <div>{v.verified_at ? new Date(v.verified_at).toLocaleString() : '—'}</div>
                </div>
              </div>

              {v.last_error_code && (
                <div className="alert alert-danger py-2 mt-3 mb-0">
                  <strong>{v.last_error_code}:</strong> {v.last_error_message}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-5">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              {t('ম্যানুয়াল ওভাররাইড ও ম্যাচড ফ্ল্যাগ', 'Manual Override & Match Flags')}
            </div>
            <div className="card-body small">
              <div className="mb-3">
                <div className="text-muted mb-1">{t('ম্যাচ ফলাফল (Raw API Payload সংরক্ষিত নয়)', 'Match Flags (No Raw Response Stored)')}</div>
                <div className="d-flex flex-wrap gap-2">
                  {Object.keys(v.matched_fields || {}).length === 0 ? (
                    <span className="text-muted">—</span>
                  ) : (
                    Object.entries(v.matched_fields).map(([key, val]) => (
                      <span
                        key={key}
                        className={`badge ${val ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'}`}
                      >
                        {key}: {String(val)}
                      </span>
                    ))
                  )}
                </div>
              </div>

              <hr />

              <div>
                <div className="fw-bold mb-2">{t('ম্যানুয়াল রিভিউ অবস্থা', 'Manual Review Audit')}</div>
                {v.manual_override ? (
                  <div className="bg-light p-2 rounded border">
                    <div>
                      <strong>{t('পূর্ববর্তী অবস্থা:', 'Previous Status:')}</strong> {v.previous_status}
                    </div>
                    <div>
                      <strong>{t('পর্যালোচনাকারী:', 'Reviewed By:')}</strong> {v.reviewed_by || '—'}
                    </div>
                    <div>
                      <strong>{t('তারিখ:', 'Reviewed At:')}</strong>{' '}
                      {v.reviewed_at ? new Date(v.reviewed_at).toLocaleString() : '—'}
                    </div>
                    <div className="mt-1">
                      <strong>{t('কারণ:', 'Reason:')}</strong> {v.override_reason}
                    </div>
                  </div>
                ) : (
                  <p className="text-muted mb-0">
                    {t('এই রেকর্ডে কোনো ম্যানুয়াল ওভাররাইড করা হয়নি।', 'No manual override has been applied.')}
                  </p>
                )}
              </div>

              {canRetry && (
                <>
                  <hr />
                  <form onSubmit={handleRetrySubmit}>
                    <label className="form-label fw-bold">
                      {t('পুনরায় চেষ্টা করুন (নিরাপত্তার জন্য এনআইডি পুনরায় দিন)', 'Retry Provider Check (Re-enter NID)')}
                    </label>
                    {retryError && <div className="text-danger mb-2">{retryError}</div>}
                    <div className="input-group input-group-sm">
                      <input
                        type="text"
                        inputMode="numeric"
                        className="form-control font-monospace"
                        placeholder={t('মূল এনআইডি নম্বর', 'Original NID number')}
                        value={retryNid}
                        onChange={(e) => setRetryNid(e.target.value)}
                      />
                      <button
                        type="submit"
                        className="btn btn-outline-primary"
                        disabled={retryMutation.isPending}
                      >
                        {retryMutation.isPending ? t('চলছে...', 'Retrying...') : t('পুনরায় যাচাই', 'Retry')}
                      </button>
                    </div>
                  </form>
                </>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Attempts Timeline */}
      <div className="card shadow-sm border-0">
        <div className="card-header bg-white fw-bold">
          <i className="bi bi-list-ol me-2 text-primary"></i>
          {t('প্রোভাইডার প্রচেষ্টার ইতিহাস (Attempt Logs)', 'Provider Execution Attempts')} ({attempts.length})
        </div>
        <div className="table-responsive">
          <table className="table table-sm table-hover align-middle mb-0">
            <thead className="table-light small">
              <tr>
                <th>#</th>
                <th>{t('প্রোভাইডার', 'Provider')}</th>
                <th>{t('অবস্থা', 'Outcome')}</th>
                <th>HTTP</th>
                <th>{t('সময় (ms)', 'Latency')}</th>
                <th>{t('ত্রুটি কোড ও বার্তা', 'Error Details')}</th>
                <th>{t('সময়কাল', 'Attempted At')}</th>
              </tr>
            </thead>
            <tbody className="small">
              {attempts.length === 0 ? (
                <tr>
                  <td colSpan="7" className="text-center py-3 text-muted">
                    {t('কোনো প্রচেষ্টা এখনো রেকর্ড হয়নি।', 'No attempts recorded yet.')}
                  </td>
                </tr>
              ) : (
                attempts.map((a) => (
                  <tr key={a.id}>
                    <td className="fw-bold">{a.attempt_number}</td>
                    <td className="font-monospace">
                      {a.provider_code} {a.is_mock ? '(Mock)' : ''}
                    </td>
                    <td>
                      <span
                        className={`badge ${
                          a.status === 'VERIFIED'
                            ? 'bg-success-subtle text-success'
                            : a.status === 'MANUAL_REVIEW_REQUIRED'
                            ? 'bg-warning-subtle text-warning-emphasis'
                            : 'bg-danger-subtle text-danger'
                        }`}
                      >
                        {a.status}
                      </span>
                    </td>
                    <td>{a.http_status || '—'}</td>
                    <td>{a.response_time_ms ? `${a.response_time_ms} ms` : '—'}</td>
                    <td>
                      {a.error_code ? (
                        <span>
                          <strong className="text-danger font-monospace">{a.error_code}</strong>: {a.error_message}
                        </span>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td>{a.attempted_at ? new Date(a.attempted_at).toLocaleString() : '—'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <NidManualReviewModal
        isOpen={reviewModalOpen}
        verification={v}
        onClose={() => setReviewModalOpen(false)}
        onSubmit={(payload) => reviewMutation.mutate(payload)}
        isSubmitting={reviewMutation.isPending}
        errorMessage={reviewError}
      />
    </div>
  );
};

export default NidVerificationDetailPage;
