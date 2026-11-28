import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { workflowApi } from '../../../api/workflowApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { canManageDelegations, formatWorkflowApiError } from '../utils/workflowPermissions';

export const WorkflowDelegationPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [delegateUserId, setDelegateUserId] = useState('');
  const [workflowDefId, setWorkflowDefId] = useState('');
  const [adminUnitId, setAdminUnitId] = useState('');
  const [validFrom, setValidFrom] = useState('');
  const [validUntil, setValidUntil] = useState('');
  const [reason, setReason] = useState('');
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['workflow', 'lookups'],
    queryFn: () => workflowApi.getLookups(),
  });

  const { data: delRes, isLoading, error } = useQuery({
    queryKey: ['workflow', 'delegations'],
    queryFn: () => workflowApi.getDelegations(),
  });

  const createMutation = useMutation({
    mutationFn: (payload) => workflowApi.createDelegation(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow', 'delegations'] });
      setDelegateUserId('');
      setReason('');
      setFeedback({ variant: 'success', message: t('প্রতিনিধিত্ব সফলভাবে সংরক্ষিত হয়েছে।', 'Delegation rule created.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWorkflowApiError(err, t('প্রতিনিধিত্ব তৈরি ব্যর্থ।', 'Failed to create delegation.')) });
    },
  });

  const revokeMutation = useMutation({
    mutationFn: (id) => workflowApi.revokeDelegation(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow', 'delegations'] });
      setFeedback({ variant: 'success', message: t('প্রতিনিধিত্ব বাতিল করা হয়েছে।', 'Delegation revoked.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWorkflowApiError(err, t('বাতিল ব্যর্থ।', 'Revoke failed.')) });
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    createMutation.mutate({
      delegate_user_id: delegateUserId,
      workflow_definition_id: workflowDefId || undefined,
      administrative_unit_id: adminUnitId || undefined,
      valid_from: validFrom,
      valid_until: validUntil,
      reason,
    });
  };

  const lookups = lookupsRes?.data || {};
  const delegations = delRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="mb-3">
        <h4 className="fw-bold mb-1">
          <i className="bi bi-person-check-fill text-primary me-2"></i>
          {t('অনুমোদন ক্ষমতা প্রতিনিধিত্ব (Delegation)', 'Authorized Workflow Delegations')}
        </h4>
        <p className="text-muted small mb-0">
          {t('অনুমোদিত নিয়মাবলী ও সময়সীমার অধীনে অস্থায়ী অনুমোদন ক্ষমতা হস্তান্তর', 'Assign temporary approval delegation under authorized organizational rules')}
        </p>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <div className="row g-3">
        {canManageDelegations(user) && (
          <div className="col-12 col-lg-5">
            <form className="idms-card p-3" onSubmit={handleSubmit}>
              <h6 className="fw-bold mb-3">{t('নতুন প্রতিনিধিত্ব নির্ধারণ', 'Create Delegation Rule')}</h6>
              <div className="row g-2">
                <div className="col-12">
                  <label className="form-label small fw-semibold">{t('প্রতিনিধি কর্মকর্তা', 'Delegate Officer')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={delegateUserId}
                    required
                    onChange={(e) => setDelegateUserId(e.target.value)}
                  >
                    <option value="">{t('কর্মকর্তা নির্বাচন করুন...', 'Select officer...')}</option>
                    {(lookups.users || []).map((u) => (
                      <option key={u.id} value={u.id}>
                        {u.full_name_en} ({u.role_code})
                      </option>
                    ))}
                  </select>
                </div>

                <div className="col-12">
                  <label className="form-label small fw-semibold">{t('কর্মপ্রবাহ (ঐচ্ছিক)', 'Workflow Scope (Optional)')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={workflowDefId}
                    onChange={(e) => setWorkflowDefId(e.target.value)}
                  >
                    <option value="">{t('সকল অনুমোদিত কর্মপ্রবাহ', 'All Eligible Workflows')}</option>
                    {(lookups.definitions || []).map((d) => (
                      <option key={d.id} value={d.id}>{t(d.name_bn, d.name_en)}</option>
                    ))}
                  </select>
                </div>

                <div className="col-12">
                  <label className="form-label small fw-semibold">{t('প্রশাসনিক এলাকা (ঐচ্ছিক)', 'Administrative Unit (Optional)')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={adminUnitId}
                    onChange={(e) => setAdminUnitId(e.target.value)}
                  >
                    <option value="">{t('স্বয়ংক্রিয় অধিক্ষেত্র', 'Default Jurisdiction')}</option>
                    {(lookups.administrative_units || []).map((u) => (
                      <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)}</option>
                    ))}
                  </select>
                </div>

                <div className="col-6">
                  <label className="form-label small fw-semibold">{t('শুরুর তারিখ', 'Valid From')}</label>
                  <input
                    type="datetime-local"
                    className="form-control form-control-sm"
                    value={validFrom}
                    required
                    onChange={(e) => setValidFrom(e.target.value)}
                  />
                </div>
                <div className="col-6">
                  <label className="form-label small fw-semibold">{t('শেষ তারিখ', 'Valid Until')}</label>
                  <input
                    type="datetime-local"
                    className="form-control form-control-sm"
                    value={validUntil}
                    required
                    onChange={(e) => setValidUntil(e.target.value)}
                  />
                </div>

                <div className="col-12">
                  <label className="form-label small fw-semibold">{t('কারণ / অফিস আদেশ', 'Reason / Office Order')}</label>
                  <textarea
                    className="form-control form-control-sm"
                    rows="2"
                    value={reason}
                    required
                    onChange={(e) => setReason(e.target.value)}
                  />
                </div>

                <div className="col-12 text-end mt-2">
                  <button type="submit" className="btn btn-primary btn-sm" disabled={createMutation.isPending}>
                    {t('প্রতিনিধিত্ব সংরক্ষণ করুন', 'Save Delegation')}
                  </button>
                </div>
              </div>
            </form>
          </div>
        )}

        <div className="col-12 col-lg-7">
          <div className="idms-card p-3">
            <h6 className="fw-bold mb-3">{t('সক্রিয় ও পূর্ববর্তী প্রতিনিধিত্ব তালিকা', 'Delegation History & Active Rules')}</h6>
            {isLoading ? (
              <Loader message="তালিকা লোড হচ্ছে..." messageEn="Loading delegations..." />
            ) : error ? (
              <AlertBanner variant="danger" message={formatWorkflowApiError(error, t('তালিকা লোড ব্যর্থ।', 'Failed to load delegations.'))} />
            ) : delegations.length === 0 ? (
              <EmptyState message={t('কোনো প্রতিনিধিত্ব রেকর্ড নেই।', 'No delegations configured.')} />
            ) : (
              <div className="table-responsive">
                <table className="table table-sm table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('দায়িত্বদাতা', 'Delegator')}</th>
                      <th>{t('প্রতিনিধি', 'Delegate')}</th>
                      <th>{t('সময়সীমা', 'Validity')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {delegations.map((d) => (
                      <tr key={d.id}>
                        <td className="small">
                          <div className="fw-semibold">{d.delegator?.name_en}</div>
                          <div className="text-muted" style={{ fontSize: '0.72rem' }}>{d.delegated_role_code}</div>
                        </td>
                        <td className="small">
                          <div className="fw-semibold">{d.delegate?.name_en}</div>
                          <div className="text-muted" style={{ fontSize: '0.72rem' }}>{d.delegate?.role_code}</div>
                        </td>
                        <td className="small">
                          <div>{d.valid_from ? new Date(d.valid_from).toLocaleDateString() : '-'} &rarr; {d.valid_until ? new Date(d.valid_until).toLocaleDateString() : '-'}</div>
                          <div className="text-muted" style={{ fontSize: '0.72rem' }}>{d.reason}</div>
                        </td>
                        <td>
                          <span className={`badge ${d.is_currently_valid ? 'bg-success' : d.is_active ? 'bg-info text-dark' : 'bg-secondary'}`}>
                            {d.is_currently_valid ? t('কার্যকর', 'Active') : d.is_active ? t('নির্ধারিত', 'Scheduled') : t('বাতিল', 'Revoked')}
                          </span>
                        </td>
                        <td className="text-end">
                          {d.is_active && (
                            <button
                              type="button"
                              className="btn btn-outline-danger btn-sm"
                              onClick={() => revokeMutation.mutate(d.id)}
                            >
                              {t('বাতিল', 'Revoke')}
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
        </div>
      </div>
    </div>
  );
};

export default WorkflowDelegationPage;
