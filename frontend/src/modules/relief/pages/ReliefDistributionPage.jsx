import React, { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { reliefApi } from '../../../api/reliefApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  canApproveAllocation,
  canOverrideDuplicate,
  canRecordDistribution,
  canVerifyDistribution,
  formatReliefApiError,
} from '../utils/reliefPermissions';

const newIdempotencyKey = () =>
  typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `idem-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;

export const ReliefDistributionPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();

  const [selectedAllocationId, setSelectedAllocationId] = useState(searchParams.get('allocation_id') || '');
  const [selectedAllocItemId, setSelectedAllocItemId] = useState('');
  const [distributionPointId, setDistributionPointId] = useState('');
  const [householdId, setHouseholdId] = useState('');
  const [hhSearch, setHhSearch] = useState('');
  const [qtyDisbursed, setQtyDisbursed] = useState('');
  const [verificationMethod, setVerificationMethod] = useState('SIGNATURE_MANUAL');
  const [verificationProof, setVerificationProof] = useState('');
  const [ackSignature, setAckSignature] = useState('');
  const [lat, setLat] = useState('');
  const [lng, setLng] = useState('');
  const [photoFile, setPhotoFile] = useState(null);
  const [allowDupOverride, setAllowDupOverride] = useState(false);
  const [exceptionReason, setExceptionReason] = useState('');
  const [remarks, setRemarks] = useState('');
  const [idempotencyKey, setIdempotencyKey] = useState(newIdempotencyKey);
  const [feedback, setFeedback] = useState(null);
  const [voucherSearch, setVoucherSearch] = useState('');
  const [voidTarget, setVoidTarget] = useState(null);
  const [voidReason, setVoidReason] = useState('');

  const { data: lookupsRes } = useQuery({
    queryKey: ['relief', 'lookups'],
    queryFn: () => reliefApi.getLookups(),
  });

  const { data: allocsRes } = useQuery({
    queryKey: ['relief', 'allocations', 'active-for-dist'],
    queryFn: () => reliefApi.getAllocations({ per_page: 50 }),
  });

  const { data: hhRes } = useQuery({
    queryKey: ['relief', 'eligible-households', hhSearch],
    queryFn: () => reliefApi.getEligibleHouseholds({ search: hhSearch || undefined }),
  });

  const { data: dupCheckRes } = useQuery({
    queryKey: ['relief', 'dup-check', selectedAllocItemId, householdId],
    queryFn: () =>
      reliefApi.checkDuplicate({
        relief_allocation_item_id: selectedAllocItemId,
        household_id: householdId,
      }),
    enabled: Boolean(selectedAllocItemId && householdId),
  });

  const { data: vouchersRes, isLoading: loadingVouchers } = useQuery({
    queryKey: ['relief', 'distributions', voucherSearch],
    queryFn: () => reliefApi.getDistributions({ search: voucherSearch || undefined, per_page: 25 }),
  });

  const allocations = (allocsRes?.data || []).filter((a) =>
    ['SANCTIONED', 'DISPATCHED', 'PARTIALLY_DISTRIBUTED'].includes(a.status)
  );
  const selectedAlloc = allocations.find((a) => a.id === selectedAllocationId);
  const allocItems = selectedAlloc?.items || [];
  const points = lookupsRes?.data?.distribution_points || [];
  const households = hhRes?.data || [];
  const dupCheck = dupCheckRes?.data;
  const vouchers = vouchersRes?.data || [];

  useEffect(() => {
    if (selectedAlloc?.distribution_point_id && !distributionPointId) {
      setDistributionPointId(selectedAlloc.distribution_point_id);
    }
  }, [selectedAlloc, distributionPointId]);

  const recordMutation = useMutation({
    mutationFn: (fd) => reliefApi.recordDistribution(fd),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['relief'] });
      setIdempotencyKey(newIdempotencyKey());
      setHouseholdId('');
      setQtyDisbursed('');
      setVerificationProof('');
      setAckSignature('');
      setPhotoFile(null);
      setAllowDupOverride(false);
      setExceptionReason('');
      setRemarks('');
      setFeedback({
        variant: 'success',
        message: res.idempotent_replay
          ? t(`ভাউচার ${res.data.voucher_no} ইতোমধ্যেই রেকর্ডকৃত (ডুপ্লিকেট সাবমিশন প্রতিরোধ হয়েছে)।`, `Voucher ${res.data.voucher_no} idempotently replayed without double stock deduction.`)
          : t(`ত্রাণ বিতরণ ভাউচার ${res.data.voucher_no} সফলভাবে সংরক্ষিত ও গুদাম মজুদ সমন্বয় হয়েছে।`, `Distribution voucher ${res.data.voucher_no} recorded & warehouse stock deducted atomically.`),
      });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatReliefApiError(err, t('ত্রাণ বিতরণ রেকর্ড ব্যর্থ হয়েছে।', 'Failed to record relief distribution.')) });
    },
  });

  const verifyMutation = useMutation({
    mutationFn: (id) => reliefApi.verifyVoucher(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relief'] });
      setFeedback({ variant: 'success', message: t('ট্যাগ অফিসার সাক্ষী যাচাই সম্পন্ন।', 'Witness verification completed.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatReliefApiError(err, t('যাচাই ব্যর্থ।', 'Verification failed.')) }),
  });

  const voidMutation = useMutation({
    mutationFn: ({ id, reason }) => reliefApi.voidVoucher(id, reason),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relief'] });
      setVoidTarget(null);
      setVoidReason('');
      setFeedback({ variant: 'warning', message: t('ভাউচার বাতিল করে গুদাম মজুদ ফেরত আনা হয়েছে।', 'Voucher voided and warehouse stock reversed.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatReliefApiError(err, t('বাতিল ব্যর্থ।', 'Void failed.')) }),
  });

  const captureGps = () => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLat(pos.coords.latitude.toFixed(6));
        setLng(pos.coords.longitude.toFixed(6));
      },
      () => setFeedback({ variant: 'warning', message: t('জিপিএস লোকেশন সংগ্রহ করা যায়নি।', 'Unable to obtain browser GPS coordinates.') })
    );
  };

  const handleRecordSubmit = (e) => {
    e.preventDefault();
    setFeedback(null);
    const fd = new FormData();
    fd.append('relief_allocation_item_id', selectedAllocItemId);
    fd.append('household_id', householdId);
    fd.append('distribution_point_id', distributionPointId);
    fd.append('idempotency_key', idempotencyKey);
    fd.append('quantity_disbursed', String(qtyDisbursed));
    fd.append('verification_method', verificationMethod);
    if (verificationProof) fd.append('verification_proof', verificationProof);
    if (ackSignature) fd.append('acknowledgment_signature', ackSignature);
    if (lat) fd.append('distribution_lat', lat);
    if (lng) fd.append('distribution_lng', lng);
    if (photoFile) fd.append('photo_evidence', photoFile);
    fd.append('allow_duplicate_exception', allowDupOverride ? '1' : '0');
    if (allowDupOverride && exceptionReason) fd.append('exception_reason', exceptionReason);
    if (remarks) fd.append('remarks', remarks);

    recordMutation.mutate(fd);
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-hand-index-thumb text-warning me-2"></i>
            {t('মাঠ পর্যায়ে ত্রাণ বিতরণ ও ভাউচার লেজার', 'Field Relief Handover & Voucher Ledger')}
          </h4>
          <p className="text-muted small mb-0">
            {t('স্বয়ংক্রিয় মজুদ সমন্বয়, ডুপ্লিকেট সুরক্ষা, জিপিএস ও প্রমাণকসহ উপকারভোগী বিতরণ', 'Atomic FEFO stock deduction, duplicate protection, GPS & evidence capture')}
          </p>
        </div>
        <Link to="/relief/dashboard" className="btn btn-sm btn-outline-secondary">
          {t('ড্যাশবোর্ড', 'Dashboard')}
        </Link>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {canRecordDistribution(user) && (
        <form className="card border-0 shadow-sm mb-4" onSubmit={handleRecordSubmit}>
          <div className="card-header bg-white border-bottom fw-bold d-flex justify-content-between align-items-center">
            <span>{t('নতুন ত্রাণ বিতরণ রেকর্ড করুন', 'Record Physical Relief Handover')}</span>
            <small className="text-muted">Idempotency: <code>{idempotencyKey.slice(0, 8)}...</code></small>
          </div>
          <div className="card-body">
            <div className="row g-3">
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('সক্রিয় বরাদ্দ আদেশ *', 'Active Allocation Order *')}</label>
                <select
                  className="form-select form-select-sm"
                  value={selectedAllocationId}
                  onChange={(e) => {
                    setSelectedAllocationId(e.target.value);
                    setSelectedAllocItemId('');
                  }}
                  required
                >
                  <option value="">{t('বরাদ্দ আদেশ নির্বাচন...', 'Select allocation...')}</option>
                  {allocations.map((a) => (
                    <option key={a.id} value={a.id}>
                      {a.allocation_order_no} ({t(a.administrative_unit?.name_bn || a.administrative_unit?.name_en, a.administrative_unit?.name_en)})
                    </option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('বরাদ্দকৃত পণ্য *', 'Allocated Commodity Item *')}</label>
                <select
                  className="form-select form-select-sm"
                  value={selectedAllocItemId}
                  onChange={(e) => setSelectedAllocItemId(e.target.value)}
                  required
                >
                  <option value="">{t('পণ্য নির্বাচন...', 'Select commodity item...')}</option>
                  {allocItems.map((it) => (
                    <option key={it.id} value={it.id} disabled={it.remaining_quantity <= 0}>
                      {t(it.commodity?.name_bn, it.commodity?.name_en)} — {t('অবশিষ্ট', 'Rem')}: {it.remaining_quantity} {it.commodity?.unit_of_measure}
                    </option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('বিতরণ কেন্দ্র *', 'Distribution Center *')}</label>
                <select
                  className="form-select form-select-sm"
                  value={distributionPointId}
                  onChange={(e) => setDistributionPointId(e.target.value)}
                  required
                >
                  <option value="">{t('কেন্দ্র নির্বাচন...', 'Select center...')}</option>
                  {points.map((p) => (
                    <option key={p.id} value={p.id}>{t(p.point_name_bn || p.point_name, p.point_name)}</option>
                  ))}
                </select>
              </div>

              <div className="col-md-5">
                <label className="form-label small fw-semibold">{t('উপকারভোগী পরিবার *', 'Recipient Household *')}</label>
                <div className="input-group input-group-sm mb-1">
                  <input
                    type="text"
                    className="form-control"
                    placeholder={t('পরিবার কোড/নাম খুঁজুন...', 'Search household code/name...')}
                    value={hhSearch}
                    onChange={(e) => setHhSearch(e.target.value)}
                  />
                </div>
                <select
                  className="form-select form-select-sm"
                  value={householdId}
                  onChange={(e) => setHouseholdId(e.target.value)}
                  required
                >
                  <option value="">{t('পরিবার নির্বাচন করুন...', 'Select household...')}</option>
                  {households.map((hh) => (
                    <option key={hh.id} value={hh.id}>
                      {hh.household_code} — {t(hh.hoh_name_bn || hh.hoh_name_en, hh.hoh_name_en)} ({hh.hoh_nid_masked})
                    </option>
                  ))}
                </select>
              </div>

              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('বিতরণের পরিমাণ *', 'Quantity *')}</label>
                <input
                  type="number"
                  min="0.01"
                  step="0.01"
                  className="form-control form-control-sm"
                  value={qtyDisbursed}
                  onChange={(e) => setQtyDisbursed(e.target.value)}
                  required
                />
              </div>

              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('যাচাই পদ্ধতি *', 'Verification *')}</label>
                <select
                  className="form-select form-select-sm"
                  value={verificationMethod}
                  onChange={(e) => setVerificationMethod(e.target.value)}
                >
                  <option value="SIGNATURE_MANUAL">{t('স্বাক্ষর / টিপসই', 'Signature / Thumbprint')}</option>
                  <option value="QR_SCAN">{t('কিউআর স্ক্যান', 'QR Scan')}</option>
                  <option value="SMS_OTP">{t('এসএমএস ওটিপি', 'SMS OTP')}</option>
                  <option value="NID_BIOMETRIC">{t('এনআইডি / বায়োমেট্রিক', 'NID / Biometric')}</option>
                </select>
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('স্বাক্ষর / ওটিপি রেফারেন্স', 'Acknowledgment / Proof')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  placeholder={t('স্বাক্ষরকারী বা ওটিপি রেফারেন্স', 'Receiver acknowledgment or proof')}
                  value={ackSignature}
                  onChange={(e) => setAckSignature(e.target.value)}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('ছবি প্রমাণক (ঐচ্ছিক)', 'Handoff Photo Evidence')}</label>
                <input
                  type="file"
                  accept="image/*"
                  className="form-control form-control-sm"
                  onChange={(e) => setPhotoFile(e.target.files?.[0] || null)}
                />
              </div>

              <div className="col-md-5">
                <label className="form-label small fw-semibold">{t('বিতরণ জিপিএস স্থানাঙ্ক', 'Distribution GPS Coordinates')}</label>
                <div className="input-group input-group-sm">
                  <input type="text" className="form-control" placeholder="Lat" value={lat} onChange={(e) => setLat(e.target.value)} />
                  <input type="text" className="form-control" placeholder="Lng" value={lng} onChange={(e) => setLng(e.target.value)} />
                  <button type="button" className="btn btn-outline-secondary" onClick={captureGps}>
                    <i className="bi bi-crosshair me-1"></i>GPS
                  </button>
                </div>
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('মন্তব্য', 'Remarks')}</label>
                <input type="text" className="form-control form-control-sm" value={remarks} onChange={(e) => setRemarks(e.target.value)} />
              </div>
            </div>

            {dupCheck?.has_duplicate && (
              <div className="alert alert-danger mt-3 mb-0">
                <div className="fw-bold">
                  <i className="bi bi-exclamation-octagon-fill me-2"></i>
                  {t('ডুপ্লিকেট ত্রাণ সহায়তা সতর্কতা!', 'Duplicate Relief Assistance Detected!')}
                </div>
                <ul className="small mb-2">
                  {dupCheck.reasons.map((r, i) => <li key={i}>{r}</li>)}
                </ul>
                {canOverrideDuplicate(user) && (
                  <div className="border-top pt-2">
                    <div className="form-check mb-2">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="dupOverrideChk"
                        checked={allowDupOverride}
                        onChange={(e) => setAllowDupOverride(e.target.checked)}
                      />
                      <label className="form-check-label small fw-semibold" htmlFor="dupOverrideChk">
                        {t('বিশেষ ক্ষমতায় ডুপ্লিকেট ব্যতিক্রম অনুমোদন করুন (লিখিত কারণ আবশ্যক)', 'Authorize Duplicate Exception Override (documented reason required)')}
                      </label>
                    </div>
                    {allowDupOverride && (
                      <textarea
                        className="form-control form-control-sm"
                        rows={2}
                        required
                        placeholder={t('কমপক্ষে ১০ অক্ষরের ব্যতিক্রম কারণ লিখুন...', 'Enter justification (min 10 chars)...')}
                        value={exceptionReason}
                        onChange={(e) => setExceptionReason(e.target.value)}
                      />
                    )}
                  </div>
                )}
              </div>
            )}

            <div className="d-flex justify-content-end mt-3">
              <button
                type="submit"
                className="btn btn-warning text-dark fw-semibold"
                disabled={recordMutation.isPending || (dupCheck?.has_duplicate && !allowDupOverride)}
              >
                <i className="bi bi-check2-circle me-1"></i>
                {t('বিতরণ নিশ্চিত ও মজুদ সমন্বয় করুন', 'Confirm Handover & Deduct Stock')}
              </button>
            </div>
          </div>
        </form>
      )}

      {/* Voucher History Table */}
      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h6 className="fw-bold mb-0">{t('বিতরণ ভাউচার লেজার', 'Distribution Voucher Ledger')}</h6>
          <input
            type="text"
            className="form-control form-control-sm"
            style={{ width: '240px' }}
            placeholder={t('ভাউচার বা পরিবার কোড...', 'Search voucher or HH code...')}
            value={voucherSearch}
            onChange={(e) => setVoucherSearch(e.target.value)}
          />
        </div>
        {loadingVouchers ? (
          <div className="p-4"><Loader /></div>
        ) : (
          <div className="table-responsive">
            <table className="table table-sm table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('ভাউচার নং', 'Voucher No')}</th>
                  <th>{t('পরিবার', 'Household')}</th>
                  <th>{t('পণ্য ও পরিমাণ', 'Commodity & Qty')}</th>
                  <th>{t('কেন্দ্র', 'Center')}</th>
                  <th>{t('বিতরণকারী ও সাক্ষী', 'Distributor & Witness')}</th>
                  <th>{t('অবস্থা', 'Flags')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {vouchers.map((v) => (
                  <tr key={v.id} className={v.is_voided ? 'table-secondary opacity-75' : ''}>
                    <td className="fw-semibold">{v.voucher_no}</td>
                    <td>
                      {v.household ? (
                        <div>
                          <div>{t(v.household.hoh_name_bn || v.household.hoh_name_en, v.household.hoh_name_en)}</div>
                          <small className="text-muted">{v.household.household_code}</small>
                        </div>
                      ) : '—'}
                    </td>
                    <td>
                      {t(v.allocation_item?.commodity?.name_bn, v.allocation_item?.commodity?.name_en)}:{' '}
                      <strong>{v.quantity_disbursed}</strong> {v.allocation_item?.commodity?.unit_of_measure}
                    </td>
                    <td>{t(v.distribution_point?.point_name_bn || v.distribution_point?.point_name, v.distribution_point?.point_name)}</td>
                    <td className="small">
                      <div>{v.distributor?.name || '—'}</div>
                      {v.verifying_tag_officer ? (
                        <span className="text-success"><i className="bi bi-patch-check-fill me-1"></i>{v.verifying_tag_officer.name}</span>
                      ) : (
                        <span className="text-muted">{t('সাক্ষী অপেক্ষমাণ', 'Pending witness')}</span>
                      )}
                    </td>
                    <td>
                      {v.is_voided && <span className="badge bg-danger me-1">{t('বাতিল', 'Voided')}</span>}
                      {v.duplicate_override && <span className="badge bg-warning text-dark me-1">{t('ব্যতিক্রম', 'Dup Override')}</span>}
                    </td>
                    <td className="text-end">
                      {!v.is_voided && !v.verified_by_tag_officer && canVerifyDistribution(user) && v.distributed_by !== user?.id && (
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-success me-1"
                          onClick={() => verifyMutation.mutate(v.id)}
                        >
                          {t('সাক্ষী যাচাই', 'Verify')}
                        </button>
                      )}
                      {!v.is_voided && canApproveAllocation(user) && (
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-danger"
                          onClick={() => setVoidTarget(v)}
                        >
                          {t('বাতিল', 'Void')}
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <ConfirmDialog
        isOpen={Boolean(voidTarget)}
        title={t('বিতরণ ভাউচার বাতিল করুন', 'Void Distribution Voucher')}
        titleEn="Void Voucher & Reverse Warehouse Stock"
        message={
          <div>
            <p className="small">{t('ভাউচার বাতিলের কারণ লিখুন (কমপক্ষে ১০ অক্ষর):', 'Provide void justification (min 10 chars):')}</p>
            <textarea
              className="form-control"
              rows={2}
              value={voidReason}
              onChange={(e) => setVoidReason(e.target.value)}
            />
          </div>
        }
        confirmText={t('বাতিল নিশ্চিত করুন', 'Confirm Void')}
        onCancel={() => {
          setVoidTarget(null);
          setVoidReason('');
        }}
        onConfirm={() => {
          if (voidTarget && voidReason.trim().length >= 10) {
            voidMutation.mutate({ id: voidTarget.id, reason: voidReason });
          }
        }}
        isLoading={voidMutation.isPending}
      />
    </div>
  );
};

export default ReliefDistributionPage;
