import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import nidApi from '../../../api/nidApi';
import beneficiaryApi from '../../../api/beneficiaryApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import { isValidBangladeshNid } from '../utils/nidPermissions';
import NidProviderBanner from '../components/NidProviderBanner';
import NidStatusBadge from '../components/NidStatusBadge';
import AlertBanner from '../../../components/common/AlertBanner';

export const NidVerifyPage = () => {
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [formData, setFormData] = useState({
    nid_number: '',
    date_of_birth: '',
    submitted_name_en: '',
    household_id: '',
    async: false,
  });

  const [clientError, setClientError] = useState('');
  const [resultRecord, setResultRecord] = useState(null);

  const { data: healthData } = useQuery({
    queryKey: ['nidProviderHealth'],
    queryFn: async () => {
      const res = await nidApi.getProviderHealth();
      return res?.provider || res?.data?.provider || null;
    },
  });

  const { data: householdsData } = useQuery({
    queryKey: ['householdsLookupForNid'],
    queryFn: async () => {
      const res = await beneficiaryApi.getHouseholds({ per_page: 50 });
      return res?.data || [];
    },
  });

  const verifyMutation = useMutation({
    mutationFn: (payload) => nidApi.verifyNid(payload),
    onSuccess: (res) => {
      const verification = res?.verification || res?.data?.verification || null;
      setResultRecord(verification);
      queryClient.invalidateQueries(['nidDashboard']);
      queryClient.invalidateQueries(['nidVerifications']);
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    setClientError('');

    const cleanNid = formData.nid_number.trim();
    if (!isValidBangladeshNid(cleanNid)) {
      setClientError(
        t(
          'জাতীয় পরিচয়পত্র নম্বর অবশ্যই ১০, ১৩ অথবা ১৭ সংখ্যার হতে হবে।',
          'National ID number must be exactly 10, 13, or 17 numeric digits.'
        )
      );
      return;
    }

    const payload = {
      nid_number: cleanNid,
      date_of_birth: formData.date_of_birth || undefined,
      submitted_name_en: formData.submitted_name_en.trim() || undefined,
      household_id: formData.household_id || undefined,
      async: formData.async,
    };

    verifyMutation.mutate(payload);
  };

  const serverError = verifyMutation.error?.message
    || (verifyMutation.error?.details ? Object.values(verifyMutation.error.details).flat().join(' ') : null);

  return (
    <div className="container-fluid py-2" style={{ maxWidth: '960px' }}>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-patch-check-fill text-primary me-2"></i>
            {t('জাতীয় পরিচয়পত্র (NID) যাচাইকরণ', 'National ID (NID) Verification')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              '১০/১৩/১৭ ডিজিট এনআইডি ফরম্যাট যাচাই ও অনুমোদিত গেটওয়েতে প্রেরণ করুন',
              'Validate 10/13/17-digit NID format and verify against configured provider'
            )}
          </p>
        </div>
        <div className="d-flex gap-2">
          <Link to="/nid/dashboard" className="btn btn-outline-secondary btn-sm">
            {t('ড্যাশবোর্ড', 'Dashboard')}
          </Link>
          <Link to="/nid/verifications" className="btn btn-outline-primary btn-sm">
            {t('যাচাই ইতিহাস', 'History')}
          </Link>
        </div>
      </div>

      <NidProviderBanner provider={healthData} />

      <div className="card shadow-sm border-0 mb-4">
        <div className="card-header bg-white fw-bold">
          {t('নাগরিক পরিচয় তথ্য জমা দিন', 'Submit Citizen Identity Credentials')}
        </div>
        <div className="card-body">
          {clientError && <AlertBanner type="warning" message={clientError} />}
          {serverError && <AlertBanner type="danger" message={serverError} />}

          <form onSubmit={handleSubmit}>
            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label small fw-bold">
                  {t('জাতীয় পরিচয়পত্র নম্বর (NID) *', 'National ID Number (NID) *')}
                </label>
                <input
                  type="text"
                  inputMode="numeric"
                  className="form-control font-monospace"
                  placeholder={t('১০, ১৩ অথবা ১৭ সংখ্যার এনআইডি', '10, 13, or 17 digits')}
                  value={formData.nid_number}
                  onChange={(e) => setFormData({ ...formData, nid_number: e.target.value })}
                  required
                />
                <div className="form-text">
                  {t(
                    'ডেভ মক ফিক্সচার: 0000000000 (Not Found), 8888888888 (Manual Review), 7777777777 (Timeout)',
                    'Dev mock fixtures: 0000000000 (Not Found), 8888888888 (Manual Review), 7777777777 (Timeout)'
                  )}
                </div>
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-bold">
                  {t('জন্ম তারিখ (Date of Birth)', 'Date of Birth')}
                </label>
                <input
                  type="date"
                  className="form-control"
                  value={formData.date_of_birth}
                  onChange={(e) => setFormData({ ...formData, date_of_birth: e.target.value })}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-bold">
                  {t('নাগরিকের নাম (ইংরেজিতে - ঐচ্ছিক)', 'Citizen Full Name (English - Optional)')}
                </label>
                <input
                  type="text"
                  className="form-control"
                  placeholder="e.g. Md. Abdul Karim"
                  value={formData.submitted_name_en}
                  onChange={(e) => setFormData({ ...formData, submitted_name_en: e.target.value })}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-bold">
                  {t('উপকারভোগী পরিবারের সাথে সংযুক্তি (ঐচ্ছিক)', 'Link to Beneficiary Household (Optional)')}
                </label>
                <select
                  className="form-select"
                  value={formData.household_id}
                  onChange={(e) => setFormData({ ...formData, household_id: e.target.value })}
                >
                  <option value="">{t('-- একক যাচাই (কোন পরিবার নয়) --', '-- Standalone Verification --')}</option>
                  {householdsData?.map((hh) => (
                    <option key={hh.id} value={hh.id}>
                      {hh.household_code} — {hh.hoh_name_bn || hh.hoh_name_en}
                    </option>
                  ))}
                </select>
              </div>

              <div className="col-12">
                <div className="form-check">
                  <input
                    id="chkAsyncNid"
                    type="checkbox"
                    className="form-check-input"
                    checked={formData.async}
                    onChange={(e) => setFormData({ ...formData, async: e.target.checked })}
                  />
                  <label className="form-check-label small" htmlFor="chkAsyncNid">
                    {t(
                      'ব্যাকগ্রাউন্ড কিউ (Redis Queue) এর মাধ্যমে যাচাই করুন (PENDING অবস্থা রিটার্ন করবে)',
                      'Process asynchronously via Redis background queue (returns PENDING immediately)'
                    )}
                  </label>
                </div>
              </div>
            </div>

            <div className="mt-4 d-flex justify-content-end">
              <button
                type="submit"
                className="btn btn-primary px-4"
                disabled={verifyMutation.isPending}
              >
                {verifyMutation.isPending ? (
                  <>
                    <span className="spinner-border spinner-border-sm me-2"></span>
                    {t('যাচাই করা হচ্ছে...', 'Verifying...')}
                  </>
                ) : (
                  <>
                    <i className="bi bi-shield-check me-2"></i>
                    {t('যাচাই অনুরোধ পাঠান', 'Submit Verification')}
                  </>
                )}
              </button>
            </div>
          </form>
        </div>
      </div>

      {resultRecord && (
        <div className="card shadow-sm border-0 border-start border-4 border-primary">
          <div className="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span>{t('সর্বশেষ যাচাই ফলাফল', 'Verification Outcome')}</span>
            <NidStatusBadge
              status={resultRecord.verification_status}
              isMock={resultRecord.is_mock}
              manualOverride={resultRecord.manual_override}
            />
          </div>
          <div className="card-body">
            <div className="row g-3 small">
              <div className="col-md-4">
                <div className="text-muted">{t('রেফারেন্স নম্বর', 'Verification Reference')}</div>
                <div className="fw-bold font-monospace">{resultRecord.verification_reference}</div>
              </div>
              <div className="col-md-4">
                <div className="text-muted">{t('মাস্কড এনআইডি', 'Masked NID')}</div>
                <div className="fw-bold font-monospace">{resultRecord.nid_masked}</div>
              </div>
              <div className="col-md-4">
                <div className="text-muted">{t('প্রোভাইডার ও প্রচেষ্টা', 'Provider & Attempts')}</div>
                <div className="fw-bold">
                  {resultRecord.provider_code} ({resultRecord.attempt_count || 0} {t('বার', 'attempts')})
                </div>
              </div>

              {resultRecord.verification_token && (
                <div className="col-md-6">
                  <div className="text-muted">{t('ভেরিফিকেশন টোকেন', 'Verification Token')}</div>
                  <div className="font-monospace text-success">{resultRecord.verification_token}</div>
                </div>
              )}

              {resultRecord.last_error_code && (
                <div className="col-md-6">
                  <div className="text-muted">{t('ত্রুটি বিবরণ', 'Error Summary')}</div>
                  <div className="text-danger">
                    <span className="font-monospace fw-bold">{resultRecord.last_error_code}</span> —{' '}
                    {resultRecord.last_error_message}
                  </div>
                </div>
              )}
            </div>

            <div className="mt-3 pt-3 border-top d-flex justify-content-end">
              <Link to={`/nid/verifications/${resultRecord.id}`} className="btn btn-outline-primary btn-sm">
                <i className="bi bi-eye me-1"></i>
                {t('পূর্ণ ইতিহাস ও প্রচেষ্টা দেখুন', 'View Full Details & Attempts')}
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default NidVerifyPage;
