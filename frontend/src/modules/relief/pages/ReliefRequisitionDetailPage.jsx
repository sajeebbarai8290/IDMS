import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { reliefApi } from '../../../api/reliefApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { canApproveRequisition, canCreateAllocation, downloadBlobFile, formatReliefApiError } from '../utils/reliefPermissions';
import { PriorityBadge, RequisitionStatusBadge } from '../components/ReliefStatusBadges';

export const ReliefRequisitionDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [reviewNotes, setReviewNotes] = useState('');
  const [approvedQtys, setApprovedQtys] = useState({});
  const [feedback, setFeedback] = useState(null);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['relief', 'requisitions', id],
    queryFn: () => reliefApi.getRequisition(id),
  });

  const actionMutation = useMutation({
    mutationFn: ({ action, payload }) => {
      if (action === 'submit') return reliefApi.submitRequisition(id);
      if (action === 'review') return reliefApi.reviewRequisition(id, payload);
      if (action === 'approve') return reliefApi.approveRequisition(id, payload);
      return reliefApi.rejectRequisition(id, payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relief'] });
      setFeedback({ variant: 'success', message: t('অবস্থা সফলভাবে হালনাগাদ হয়েছে।', 'Requisition workflow updated successfully.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatReliefApiError(err, t('অনুরোধ সম্পন্ন করতে ব্যর্থ।', 'Action failed.')) });
    },
  });

  const handleDownloadDoc = async (docName) => {
    try {
      const blob = await reliefApi.downloadSupportingDoc(id);
      downloadBlobFile(blob, docName || 'supporting-document');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatReliefApiError(err, t('নথি ডাউনলোড ব্যর্থ।', 'Download failed.')) });
    }
  };

  if (isLoading) return <Loader />;
  if (error) return <AlertBanner variant="danger" message={error?.message || t('তথ্য লোড করা যায়নি', 'Unable to load requisition')} />;

  const req = res?.data;
  if (!req) return null;

  const isCreatorOrSubmitter = req.created_by === user?.id || req.submitted_by === user?.id;
  const canActApprove =
    canApproveRequisition(user) &&
    !isCreatorOrSubmitter &&
    ['SUBMITTED', 'UNDER_REVIEW'].includes(req.status);

  const canAllocate =
    canCreateAllocation(user) &&
    ['APPROVED', 'PARTIALLY_APPROVED'].includes(req.status);

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            {req.requisition_no} <RequisitionStatusBadge status={req.status} /> <PriorityBadge priority={req.priority} />
          </h4>
          <div className="text-muted small">
            {t(req.disaster_event?.name_bn || req.disaster_event?.name_en, req.disaster_event?.name_en)} •{' '}
            {t(req.administrative_unit?.name_bn || req.administrative_unit?.name_en, req.administrative_unit?.name_en)}
          </div>
        </div>
        <div className="d-flex gap-2">
          <Link to="/relief/requisitions" className="btn btn-sm btn-outline-secondary">
            {t('তালিকায় ফিরুন', 'Back to List')}
          </Link>
          {canAllocate && (
            <Link to={`/relief/allocations/new?requisition_id=${req.id}`} className="btn btn-sm btn-primary">
              <i className="bi bi-truck me-1"></i>
              {t('বরাদ্দ আদেশ তৈরি করুন', 'Create Allocation Order')}
            </Link>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {isCreatorOrSubmitter && ['SUBMITTED', 'UNDER_REVIEW'].includes(req.status) && canApproveRequisition(user) && (
        <AlertBanner
          variant="warning"
          message={t(
            'ফোর-আইজ সেপারেশন অব ডিউটিস: আপনি নিজে এই চাহিদাপত্র তৈরি বা জমা দেওয়ায় অনুমোদন/প্রত্যাখ্যান করতে পারবেন না।',
            'Four-eyes Separation of Duties: Because you created or submitted this requisition, another authorized officer must review and approve it.'
          )}
        />
      )}

      <div className="row g-3">
        <div className="col-lg-8">
          <div className="card border-0 shadow-sm mb-3">
            <div className="card-header bg-white border-bottom fw-bold">
              {t('চাহিদাকৃত ত্রাণ সামগ্রী', 'Requested Relief Items')}
            </div>
            <div className="table-responsive">
              <table className="table align-middle mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('পণ্য', 'Commodity')}</th>
                    <th>{t('একক', 'Unit')}</th>
                    <th className="text-end">{t('চাহিদা', 'Requested')}</th>
                    <th className="text-end">{t('অনুমোদিত', 'Approved')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(req.items || []).map((item) => {
                    const inputVal = approvedQtys[item.id] ?? item.quantity_requested;
                    return (
                      <tr key={item.id}>
                        <td className="fw-medium">{t(item.commodity?.name_bn, item.commodity?.name_en)}</td>
                        <td>{item.commodity?.unit_of_measure}</td>
                        <td className="text-end">{item.quantity_requested}</td>
                        <td className="text-end" style={{ width: '160px' }}>
                          {canActApprove ? (
                            <input
                              type="number"
                              min="0"
                              max={item.quantity_requested}
                              step="0.01"
                              className="form-control form-control-sm text-end"
                              value={inputVal}
                              onChange={(e) => setApprovedQtys((p) => ({ ...p, [item.id]: e.target.value }))}
                            />
                          ) : (
                            <span>{item.quantity_approved}</span>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>

          {canActApprove && (
            <div className="card border-0 shadow-sm">
              <div className="card-header bg-white border-bottom fw-bold">
                {t('পর্যালোচনা ও অনুমোদন সিদ্ধান্ত', 'Review & Approval Decision')}
              </div>
              <div className="card-body">
                <div className="mb-3">
                  <label className="form-label small fw-semibold">{t('মন্তব্য / কারণ (প্রত্যাখ্যানের ক্ষেত্রে আবশ্যক)', 'Review Notes (Required on Reject)')}</label>
                  <textarea
                    className="form-control"
                    rows={2}
                    value={reviewNotes}
                    onChange={(e) => setReviewNotes(e.target.value)}
                  />
                </div>
                <div className="d-flex flex-wrap gap-2">
                  {req.status === 'SUBMITTED' && (
                    <button
                      type="button"
                      className="btn btn-info text-white"
                      disabled={actionMutation.isPending}
                      onClick={() => actionMutation.mutate({ action: 'review', payload: { review_notes: reviewNotes } })}
                    >
                      {t('পর্যালোচনায় নিন', 'Mark Under Review')}
                    </button>
                  )}
                  <button
                    type="button"
                    className="btn btn-success"
                    disabled={actionMutation.isPending}
                    onClick={() => {
                      const itemsPayload = (req.items || []).map((it) => ({
                        id: it.id,
                        quantity_approved: Number(approvedQtys[it.id] ?? it.quantity_requested),
                      }));
                      actionMutation.mutate({ action: 'approve', payload: { review_notes: reviewNotes, items: itemsPayload } });
                    }}
                  >
                    <i className="bi bi-check-circle me-1"></i>
                    {t('অনুমোদন / আংশিক অনুমোদন', 'Approve / Partially Approve')}
                  </button>
                  <button
                    type="button"
                    className="btn btn-danger"
                    disabled={actionMutation.isPending || reviewNotes.trim().length < 10}
                    onClick={() => actionMutation.mutate({ action: 'reject', payload: { review_notes: reviewNotes } })}
                  >
                    <i className="bi bi-x-circle me-1"></i>
                    {t('প্রত্যাখ্যান করুন', 'Reject')}
                  </button>
                </div>
              </div>
            </div>
          )}

          {req.status === 'DRAFT' && isCreatorOrSubmitter && (
            <div className="mt-3">
              <button
                type="button"
                className="btn btn-success"
                disabled={actionMutation.isPending}
                onClick={() => actionMutation.mutate({ action: 'submit', payload: {} })}
              >
                <i className="bi bi-send me-1"></i>
                {t('অনুমোদনের জন্য জমা দিন', 'Submit for Approval')}
              </button>
            </div>
          )}
        </div>

        <div className="col-lg-4">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white border-bottom fw-bold">
              {t('চাহিদাপত্র মেটাডেটা', 'Requisition Details')}
            </div>
            <div className="card-body small">
              <div className="mb-2"><strong>{t('সংস্থা:', 'Organization:')}</strong> {req.requesting_organization || '—'}</div>
              <div className="mb-2"><strong>{t('উপকারভোগী:', 'Est. Beneficiaries:')}</strong> {req.estimated_beneficiaries}</div>
              <div className="mb-2"><strong>{t('তারিখ:', 'Request Date:')}</strong> {req.request_date}</div>
              <div className="mb-2"><strong>{t('প্রয়োজনীয় তারিখ:', 'Needed By:')}</strong> {req.needed_by_date || '—'}</div>
              <div className="mb-2"><strong>{t('যৌক্তিকতা:', 'Justification:')}</strong><br />{req.justification || '—'}</div>
              {req.review_notes && (
                <div className="mb-2"><strong>{t('পর্যালোচনা মন্তব্য:', 'Review Notes:')}</strong><br />{req.review_notes}</div>
              )}
              {req.has_supporting_doc && (
                <button
                  type="button"
                  className="btn btn-sm btn-outline-primary mt-2"
                  onClick={() => handleDownloadDoc(req.supporting_doc_name)}
                >
                  <i className="bi bi-file-earmark-arrow-down me-1"></i>
                  {req.supporting_doc_name || t('নথি ডাউনলোড', 'Download Document')}
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReliefRequisitionDetailPage;
