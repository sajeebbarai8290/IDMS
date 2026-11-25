import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { reliefApi } from '../../../api/reliefApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import { canApproveAllocation, canDispatchAllocation, canRecordDistribution, formatReliefApiError } from '../utils/reliefPermissions';
import { AllocationStatusBadge } from '../components/ReliefStatusBadges';

export const ReliefAllocationDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [feedback, setFeedback] = useState(null);
  const [cancelReason, setCancelReason] = useState('');
  const [showCancelModal, setShowCancelModal] = useState(false);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['relief', 'allocations', id],
    queryFn: () => reliefApi.getAllocation(id),
  });

  const actionMutation = useMutation({
    mutationFn: ({ action }) => {
      if (action === 'approve') return reliefApi.approveAllocation(id);
      if (action === 'dispatch') return reliefApi.dispatchAllocation(id);
      return reliefApi.cancelAllocation(id, cancelReason);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['relief'] });
      setShowCancelModal(false);
      setFeedback({ variant: 'success', message: t('বরাদ্দ অবস্থা সফলভাবে হালনাগাদ হয়েছে।', 'Allocation updated successfully.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatReliefApiError(err, t('অনুরোধ সম্পন্ন করতে ব্যর্থ।', 'Action failed.')) });
    },
  });

  if (isLoading) return <Loader />;
  if (error) return <AlertBanner variant="danger" message={error?.message || t('বরাদ্দ লোড ব্যর্থ', 'Failed to load allocation')} />;

  const alloc = res?.data;
  if (!alloc) return null;

  const isCreator = alloc.created_by === user?.id;
  const canSanction = canApproveAllocation(user) && !isCreator && alloc.status === 'DRAFT';
  const canDispatch = canDispatchAllocation(user) && alloc.status === 'SANCTIONED';
  const canDistribute =
    canRecordDistribution(user) &&
    ['SANCTIONED', 'DISPATCHED', 'PARTIALLY_DISTRIBUTED'].includes(alloc.status);

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            {alloc.allocation_order_no} <AllocationStatusBadge status={alloc.status} />
          </h4>
          <div className="small text-muted">
            {t(alloc.disaster_event?.name_bn || alloc.disaster_event?.name_en, alloc.disaster_event?.name_en)} •{' '}
            {t(alloc.administrative_unit?.name_bn || alloc.administrative_unit?.name_en, alloc.administrative_unit?.name_en)} •{' '}
            {t(alloc.warehouse?.warehouse_name_bn || alloc.warehouse?.warehouse_name, alloc.warehouse?.warehouse_name)}
          </div>
        </div>
        <div className="d-flex flex-wrap gap-2">
          <Link to="/relief/allocations" className="btn btn-sm btn-outline-secondary">
            {t('তালিকায় ফিরুন', 'Back to List')}
          </Link>
          {canSanction && (
            <button
              type="button"
              className="btn btn-sm btn-success"
              disabled={actionMutation.isPending}
              onClick={() => actionMutation.mutate({ action: 'approve' })}
            >
              <i className="bi bi-shield-check me-1"></i>
              {t('মঞ্জুর ও মজুদ সংরক্ষণ করুন', 'Sanction & Reserve Stock')}
            </button>
          )}
          {canDispatch && (
            <button
              type="button"
              className="btn btn-sm btn-primary"
              disabled={actionMutation.isPending}
              onClick={() => actionMutation.mutate({ action: 'dispatch' })}
            >
              <i className="bi bi-truck me-1"></i>
              {t('কেন্দ্রে প্রেরণ নিশ্চিত করুন', 'Confirm Dispatch')}
            </button>
          )}
          {canDistribute && (
            <Link to={`/relief/distributions?allocation_id=${alloc.id}`} className="btn btn-sm btn-warning text-dark fw-semibold">
              <i className="bi bi-hand-index-thumb me-1"></i>
              {t('বিতরণ শুরু করুন', 'Record Handover')}
            </Link>
          )}
          {canApproveAllocation(user) && !['COMPLETED', 'CANCELLED'].includes(alloc.status) && (
            <button
              type="button"
              className="btn btn-sm btn-outline-danger"
              onClick={() => setShowCancelModal(true)}
            >
              {t('অবশিষ্ট বাতিল', 'Cancel Remainder')}
            </button>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {isCreator && alloc.status === 'DRAFT' && canApproveAllocation(user) && (
        <AlertBanner
          variant="warning"
          message={t(
            'ফোর-আইজ সেপারেশন অব ডিউটিস: আপনি নিজে এই বরাদ্দ তৈরি করায় মঞ্জুর করতে পারবেন না।',
            'Four-eyes Separation of Duties: Another authorizing authority must sanction your allocation order.'
          )}
        />
      )}

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white border-bottom fw-bold">
          {t('বরাদ্দকৃত সামগ্রী ও বিতরণ অগ্রগতি', 'Allocated Commodities & Progress')}
        </div>
        <div className="table-responsive">
          <table className="table align-middle mb-0">
            <thead className="table-light">
              <tr>
                <th>{t('পণ্য', 'Commodity')}</th>
                <th>{t('একক', 'Unit')}</th>
                <th className="text-end">{t('বরাদ্দ', 'Allocated')}</th>
                <th className="text-end">{t('বিতরণকৃত', 'Distributed')}</th>
                <th className="text-end">{t('বাতিল', 'Cancelled')}</th>
                <th className="text-end">{t('অবশিষ্ট', 'Remaining')}</th>
              </tr>
            </thead>
            <tbody>
              {(alloc.items || []).map((item) => (
                <tr key={item.id}>
                  <td className="fw-semibold">{t(item.commodity?.name_bn, item.commodity?.name_en)}</td>
                  <td>{item.commodity?.unit_of_measure}</td>
                  <td className="text-end">{item.quantity_allocated}</td>
                  <td className="text-end text-success fw-semibold">{item.quantity_distributed}</td>
                  <td className="text-end text-muted">{item.quantity_cancelled}</td>
                  <td className="text-end fw-bold">{item.remaining_quantity}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white border-bottom fw-bold">
          {t('নির্বাচিত উপকারভোগী তালিকা', 'Assigned Eligible Beneficiaries')} ({(alloc.beneficiaries || []).length})
        </div>
        <div className="card-body">
          {(alloc.beneficiaries || []).length === 0 ? (
            <p className="text-muted small mb-0">
              {t('এখনও নির্দিষ্ট পরিবার তালিকা সংযুক্ত হয়নি (এলাকার সকল যাচাইকৃত পরিবার বিতরণযোগ্য)।', 'No restricted roster attached (any verified household in jurisdiction may receive relief).')}
            </p>
          ) : (
            <div className="table-responsive">
              <table className="table table-sm align-middle mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('পরিবার কোড', 'Household Code')}</th>
                    <th>{t('পরিবার প্রধান', 'Head of Household')}</th>
                    <th>{t('এনআইডি', 'NID Masked')}</th>
                    <th>{t('অবস্থা', 'Roster Status')}</th>
                  </tr>
                </thead>
                <tbody>
                  {alloc.beneficiaries.map((b) => (
                    <tr key={b.id}>
                      <td>{b.household?.household_code}</td>
                      <td>{t(b.household?.hoh_name_bn || b.household?.hoh_name_en, b.household?.hoh_name_en)}</td>
                      <td><code>{b.household?.hoh_nid_masked}</code></td>
                      <td><span className="badge bg-secondary">{b.selection_status}</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      <ConfirmDialog
        isOpen={showCancelModal}
        title={t('অবশিষ্ট বরাদ্দ বাতিল করুন', 'Cancel Remaining Allocation')}
        titleEn="Confirm Cancellation & Stock Release"
        message={
          <div>
            <p className="small">{t('বাতিলের কারণ লিখুন (কমপক্ষে ১০ অক্ষর):', 'Provide cancellation reason (minimum 10 characters):')}</p>
            <textarea
              className="form-control"
              rows={2}
              value={cancelReason}
              onChange={(e) => setCancelReason(e.target.value)}
            />
          </div>
        }
        confirmText={t('বাতিল নিশ্চিত করুন', 'Confirm Cancel')}
        onCancel={() => setShowCancelModal(false)}
        onConfirm={() => {
          if (cancelReason.trim().length >= 10) {
            actionMutation.mutate({ action: 'cancel' });
          }
        }}
        isLoading={actionMutation.isPending}
      />
    </div>
  );
};

export default ReliefAllocationDetailPage;
