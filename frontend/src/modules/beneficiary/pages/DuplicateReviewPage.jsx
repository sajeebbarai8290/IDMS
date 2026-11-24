import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import beneficiaryApi from '../../../api/beneficiaryApi';
import Loader from '../../../components/common/Loader';
import EmptyState from '../../../components/common/EmptyState';

export const DuplicateReviewPage = () => {
  const queryClient = useQueryClient();
  const [statusFilter, setStatusFilter] = useState('PENDING_REVIEW');
  const [inspectSuspect, setInspectSuspect] = useState(null);
  const [resolutionAction, setResolutionAction] = useState('CONFIRMED_DUPLICATE');
  const [resolutionNotes, setResolutionNotes] = useState('');

  const { data, isLoading, error } = useQuery({
    queryKey: ['duplicateSuspects', statusFilter],
    queryFn: async () => {
      const res = await beneficiaryApi.getDuplicates({ status: statusFilter });
      return res?.data || res || [];
    },
  });

  const resolveMutation = useMutation({
    mutationFn: ({ id, payload }) => beneficiaryApi.resolveDuplicate(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['duplicateSuspects']);
      setInspectSuspect(null);
      setResolutionNotes('');
    },
  });

  const handleResolveSubmit = (e) => {
    e.preventDefault();
    if (!inspectSuspect) return;
    resolveMutation.mutate({
      id: inspectSuspect.id,
      payload: {
        status: resolutionAction,
        resolution_notes: resolutionNotes,
      },
    });
  };

  const suspects = Array.isArray(data) ? data : data?.data || [];

  return (
    <div className="container-fluid py-2">
      {/* Title */}
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-person-exclamation text-danger me-2"></i>
            ডুপ্লিকেট নাগরিক পর্যালোচনা সারি (Duplicate Review Queue)
          </h4>
          <p className="text-muted small mb-0">
            জাতীয় পরিচয়পত্র, মোবাইল নম্বর ও তথ্যের মিলের ভিত্তিতে স্বয়ংক্রিয়ভাবে চিহ্নিত সন্দেহভাজন রেকর্ড
          </p>
        </div>
        <div className="d-flex gap-2">
          <select
            className="form-select form-select-sm"
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="PENDING_REVIEW">পর্যালোচনা অপেক্ষমান (Pending Review)</option>
            <option value="CONFIRMED_DUPLICATE">নিশ্চিত ডুপ্লিকেট (Confirmed Duplicate)</option>
            <option value="FALSE_POSITIVE_RESOLVED">ভুল অ্যালার্ম সমাধানকৃত (False Positive)</option>
          </select>
          <Link to="/beneficiary/households" className="btn btn-outline-secondary btn-sm">
            মাস্টার রেজিস্ট্রি
          </Link>
        </div>
      </div>

      {/* Main Table */}
      <div className="card shadow-sm border-0">
        <div className="card-body p-0">
          {isLoading ? (
            <div className="py-5">
              <Loader message="ডুপ্লিকেট সন্দেহভাজন তালিকা লোড হচ্ছে..." />
            </div>
          ) : error ? (
            <div className="p-4 text-center text-danger">তালিকা লোড করতে সমস্যা হয়েছে: {error.message}</div>
          ) : suspects.length === 0 ? (
            <EmptyState
              title="কোনো ডুপ্লিকেট রেকর্ড নেই"
              description="বর্তমানে কোনো সন্দেহভাজন ডুপ্লিকেট নাগরিক রেকর্ড অপেক্ষমান নেই।"
            />
          ) : (
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead className="table-light small">
                  <tr>
                    <th>শনাক্তকরণ ধরন (Match Type)</th>
                    <th>সন্দেহভাজন পরিবার (Record A)</th>
                    <th>সাদৃশ্যপূর্ণ পরিবার (Record B)</th>
                    <th className="text-center">আত্মবিশ্বাস (Score)</th>
                    <th className="text-center">বর্তমান অবস্থা</th>
                    <th className="text-end">পর্যালোচনা</th>
                  </tr>
                </thead>
                <tbody>
                  {suspects.map((s) => (
                    <tr key={s.id}>
                      {/* Match Type */}
                      <td>
                        <span
                          className={`badge ${
                            s.match_type === 'NID_EXACT'
                              ? 'bg-danger'
                              : s.match_type === 'MOBILE_MATCH'
                              ? 'bg-warning text-dark'
                              : 'bg-info text-dark'
                          }`}
                        >
                          {s.match_type}
                        </span>
                      </td>

                      {/* Record A */}
                      <td>
                        <div className="fw-bold">{s.household?.name_bn || s.household?.name_en}</div>
                        <small className="text-muted font-monospace">{s.household?.code}</small>
                        <div className="small text-secondary">এনআইডি: {s.household?.nid_masked}</div>
                      </td>

                      {/* Record B */}
                      <td>
                        <div className="fw-bold">{s.matched_household?.name_bn || s.matched_household?.name_en}</div>
                        <small className="text-muted font-monospace">{s.matched_household?.code}</small>
                        <div className="small text-secondary">এনআইডি: {s.matched_household?.nid_masked}</div>
                      </td>

                      {/* Confidence Score */}
                      <td className="text-center">
                        <span className="badge bg-light text-dark border fs-6">
                          {s.confidence_score}%
                        </span>
                      </td>

                      {/* Status */}
                      <td className="text-center">
                        <span
                          className={`badge ${
                            s.status === 'PENDING_REVIEW'
                              ? 'bg-warning-subtle text-warning-emphasis border border-warning'
                              : s.status === 'CONFIRMED_DUPLICATE'
                              ? 'bg-danger-subtle text-danger border border-danger'
                              : 'bg-success-subtle text-success border border-success'
                          }`}
                        >
                          {s.status}
                        </span>
                      </td>

                      {/* Inspect Action */}
                      <td className="text-end">
                        <button
                          type="button"
                          className="btn btn-primary btn-sm"
                          onClick={() => setInspectSuspect(s)}
                        >
                          <i className="bi bi-layout-split me-1"></i>
                          তুলনামূলক বিশ্লেষণ
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {/* Side-by-Side Comparison Modal */}
      {inspectSuspect && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-xl modal-dialog-centered">
            <div className="modal-content shadow-lg">
              <div className="modal-header bg-danger-subtle text-danger">
                <h5 className="modal-title fw-bold">
                  <i className="bi bi-person-exclamation me-2"></i>
                  ডুপ্লিকেট রেকর্ড পাশাপাশি তুলনা (Side-by-Side Comparison)
                </h5>
                <button type="button" className="btn-close" onClick={() => setInspectSuspect(null)}></button>
              </div>
              <div className="modal-body p-4">
                <div className="row g-3 mb-4">
                  {/* Record A */}
                  <div className="col-md-6">
                    <div className="card h-100 border-primary">
                      <div className="card-header bg-primary-subtle text-primary fw-bold">
                        রেকর্ড ১: {inspectSuspect.household?.code}
                      </div>
                      <div className="card-body">
                        <p className="mb-1">
                          <strong>নাম:</strong> {inspectSuspect.household?.name_bn} ({inspectSuspect.household?.name_en})
                        </p>
                        <p className="mb-1">
                          <strong>এনআইডি (মাস্কড):</strong>{' '}
                          <code className="fs-6">{inspectSuspect.household?.nid_masked}</code>
                        </p>
                        <p className="mb-1">
                          <strong>মোবাইল:</strong> {inspectSuspect.household?.mobile_masked || '—'}
                        </p>
                        <p className="mb-1">
                          <strong>অধিক্ষেত্র:</strong> {inspectSuspect.household?.jurisdiction}
                        </p>
                        <p className="mb-1">
                          <strong>গ্রাম / ওয়ার্ড:</strong> {inspectSuspect.household?.village}
                        </p>
                      </div>
                    </div>
                  </div>

                  {/* Record B */}
                  <div className="col-md-6">
                    <div className="card h-100 border-warning">
                      <div className="card-header bg-warning-subtle text-warning-emphasis fw-bold">
                        রেকর্ড ২: {inspectSuspect.matched_household?.code}
                      </div>
                      <div className="card-body">
                        <p className="mb-1">
                          <strong>নাম:</strong> {inspectSuspect.matched_household?.name_bn} ({inspectSuspect.matched_household?.name_en})
                        </p>
                        <p className="mb-1">
                          <strong>এনআইডি (মাস্কড):</strong>{' '}
                          <code className="fs-6">{inspectSuspect.matched_household?.nid_masked}</code>
                        </p>
                        <p className="mb-1">
                          <strong>মোবাইল:</strong> {inspectSuspect.matched_household?.mobile_masked || '—'}
                        </p>
                        <p className="mb-1">
                          <strong>অধিক্ষেত্র:</strong> {inspectSuspect.matched_household?.jurisdiction}
                        </p>
                        <p className="mb-1">
                          <strong>গ্রাম / ওয়ার্ড:</strong> {inspectSuspect.matched_household?.village}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Resolution Decision Form */}
                <form onSubmit={handleResolveSubmit} className="bg-light p-3 rounded border">
                  <h6 className="fw-bold mb-3">কর্তৃপক্ষের সিদ্ধান্ত (Resolution Decision)</h6>
                  <div className="row g-3">
                    <div className="col-md-5">
                      <label className="form-label small fw-bold">সিদ্ধান্ত নির্বাচন করুন *</label>
                      <select
                        className="form-select"
                        value={resolutionAction}
                        onChange={(e) => setResolutionAction(e.target.value)}
                        required
                      >
                        <option value="CONFIRMED_DUPLICATE">ডুপ্লিকেট নিশ্চিত (Confirm Duplicate)</option>
                        <option value="FALSE_POSITIVE_RESOLVED">ভুল অ্যালার্ম সমাধান (False Positive - Separate Persons)</option>
                      </select>
                      <small className="text-muted">
                        ভুল অ্যালার্ম হলে দুটি পরিবারই বৈধ থাকবে; ডুপ্লিকেট হলে ত্রাণের অযোগ্য চিহ্নিত হবে।
                      </small>
                    </div>

                    <div className="col-md-7">
                      <label className="form-label small fw-bold">সিদ্ধান্তের যৌক্তিকতা ও নোট (Resolution Notes) *</label>
                      <textarea
                        className="form-control"
                        rows="2"
                        placeholder="মাঠ যাচাইয়ে দেখা গেছে দুইজন ভিন্ন ব্যক্তি / একই ব্যক্তি ভিন্ন ইউনিয়নে আবেদন করেছে..."
                        value={resolutionNotes}
                        onChange={(e) => setResolutionNotes(e.target.value)}
                        required
                      ></textarea>
                    </div>
                  </div>

                  <div className="text-end mt-3">
                    <button type="button" className="btn btn-secondary me-2" onClick={() => setInspectSuspect(null)}>
                      বাতিল
                    </button>
                    <button type="submit" className="btn btn-danger" disabled={resolveMutation.isLoading}>
                      {resolveMutation.isLoading ? 'সংরক্ষণ হচ্ছে...' : 'সিদ্ধান্ত রেকর্ড করুন'}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default DuplicateReviewPage;
