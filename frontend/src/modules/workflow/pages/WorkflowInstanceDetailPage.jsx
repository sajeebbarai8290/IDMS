import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { workflowApi } from '../../../api/workflowApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canExecuteWorkflow,
  canRunEscalations,
  formatWorkflowApiError,
  triggerBlobDownload,
} from '../utils/workflowPermissions';
import {
  OverdueBadge,
  WorkflowActionBadge,
  WorkflowLevelLabel,
  WorkflowPriorityBadge,
  WorkflowStateBadge,
} from '../components/WorkflowStatusBadges';

export const WorkflowInstanceDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();
  const canOperate = canExecuteWorkflow(user);
  const canRemind = canOperate || canRunEscalations(user);

  const [comments, setComments] = useState('');
  const [targetStageId, setTargetStageId] = useState('');
  const [forwardUserId, setForwardUserId] = useState('');
  const [forwardRoleCode, setForwardRoleCode] = useState('');
  const [showForwardPanel, setShowForwardPanel] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const [uploadFile, setUploadFile] = useState(null);
  const [uploadTitle, setUploadTitle] = useState('');

  const { data: detailRes, isLoading, error } = useQuery({
    queryKey: ['workflow', 'instance', id],
    queryFn: () => workflowApi.getInstance(id),
  });

  const { data: lookupsRes } = useQuery({
    queryKey: ['workflow', 'lookups'],
    queryFn: () => workflowApi.getLookups(),
  });

  const actionMutation = useMutation({
    mutationFn: ({ action }) => {
      if (action === 'APPROVE') return workflowApi.approveInstance(id, { comments });
      if (action === 'REJECT') return workflowApi.rejectInstance(id, { comments });
      if (action === 'RETURN_FOR_CORRECTION') return workflowApi.returnForCorrection(id, { comments });
      if (action === 'REQUEST_INFO') return workflowApi.requestInfo(id, { comments });
      if (action === 'PROVIDE_INFO') return workflowApi.provideInfo(id, { comments });
      if (action === 'RESUBMIT') return workflowApi.resubmitInstance(id, { comments });
      if (action === 'CANCEL') return workflowApi.cancelInstance(id, { comments });
      if (action === 'FORWARD') {
        return workflowApi.forwardInstance(id, {
          comments,
          target_stage_id: targetStageId || undefined,
          forward_to_user_id: forwardUserId || undefined,
          forward_to_role_code: forwardRoleCode || undefined,
        });
      }
      throw new Error('Unsupported workflow action');
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow'] });
      setComments('');
      setShowForwardPanel(false);
      setFeedback({
        variant: 'success',
        message: t('কর্মপ্রবাহের সিদ্ধান্ত সফলভাবে সংরক্ষিত হয়েছে।', 'Workflow decision recorded successfully.'),
      });
    },
    onError: (err) => {
      setFeedback({
        variant: 'danger',
        message: formatWorkflowApiError(err, t('কার্যক্রম সম্পন্ন করা যায়নি।', 'Action failed.')),
      });
    },
  });

  const remindMutation = useMutation({
    mutationFn: () => workflowApi.sendReminder(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow', 'instance', id] });
      setFeedback({ variant: 'success', message: t('রিমাইন্ডার পাঠানো হয়েছে।', 'Pending approval reminder sent.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWorkflowApiError(err, t('রিমাইন্ডার ব্যর্থ।', 'Reminder failed.')) });
    },
  });

  const uploadMutation = useMutation({
    mutationFn: async () => {
      const fd = new FormData();
      fd.append('file', uploadFile);
      if (uploadTitle) fd.append('title', uploadTitle);
      return workflowApi.uploadAttachment(id, fd);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow', 'instance', id] });
      setUploadFile(null);
      setUploadTitle('');
      setFeedback({ variant: 'success', message: t('সংযুক্তি আপলোড হয়েছে।', 'Attachment uploaded securely.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWorkflowApiError(err, t('আপলোড ব্যর্থ।', 'Upload failed.')) });
    },
  });

  const handleDownload = async (att) => {
    try {
      const res = await workflowApi.downloadAttachment(id, att.id);
      const blobData = res instanceof Blob ? res : (res?.data ?? res);
      triggerBlobDownload(blobData, att.original_name || 'attachment');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatWorkflowApiError(err, t('ডাউনলোড ব্যর্থ।', 'Download failed.')) });
    }
  };

  if (isLoading) {
    return <Loader message="কর্মপ্রবাহ বিবরণ লোড হচ্ছে..." messageEn="Loading workflow detail..." />;
  }

  if (error) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner variant="danger" message={formatWorkflowApiError(error, t('তথ্য লোড ব্যর্থ হয়েছে।', 'Failed to load workflow instance.'))} />
      </div>
    );
  }

  const instance = detailRes?.data;
  const availableActions = detailRes?.available_actions || [];
  const liveEntity = detailRes?.live_entity || null;
  const lookups = lookupsRes?.data || {};

  if (!instance) return null;

  const stages = instance.definition?.stages || [];
  const tasks = instance.tasks || [];
  const transitions = instance.transitions || [];
  const attachments = instance.attachments || [];

  return (
    <div className="container-fluid py-3">
      {/* Header */}
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h4 className="fw-bold mb-0">{instance.instance_no}</h4>
            <WorkflowStateBadge state={instance.current_state} />
            <WorkflowPriorityBadge priority={instance.priority} />
            <OverdueBadge isOverdue={instance.is_overdue} />
          </div>
          <div className="text-muted small">
            {t(instance.title_bn || instance.title_en, instance.title_en)} • {instance.module_code} ({instance.entity_reference})
          </div>
        </div>

        <div className="d-flex gap-2">
          {!instance.is_terminal && canRemind && (
            <button
              type="button"
              className="btn btn-outline-warning btn-sm"
              disabled={remindMutation.isPending}
              onClick={() => remindMutation.mutate()}
            >
              <i className="bi bi-bell me-1"></i>
              {t('রিমাইন্ডার পাঠান', 'Send Reminder')}
            </button>
          )}
          <Link to="/workflow/instances" className="btn btn-outline-secondary btn-sm">
            <i className="bi bi-arrow-left me-1"></i>
            {t('তালিকায় ফিরুন', 'Back to Queue')}
          </Link>
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {/* Visual Stage Progress Stepper */}
      <div className="idms-card p-3 mb-3">
        <h6 className="fw-bold mb-3">
          <i className="bi bi-diagram-3 text-primary me-2"></i>
          {t('অনুমোদন ধাপসমূহ', 'Configured Workflow Stages')} — {t(instance.definition?.name_bn, instance.definition?.name_en)}
        </h6>
        <div className="row g-2">
          {stages.map((st) => {
            const isCurrent = instance.current_stage_id === st.id && !instance.is_terminal;
            const stageTasks = tasks.filter((tk) => tk.workflow_stage_id === st.id);
            const allApproved = stageTasks.length > 0 && stageTasks.every((tk) => tk.status === 'APPROVED' || tk.status === 'SKIPPED');

            return (
              <div key={st.id} className="col-12 col-md-4 col-xl-3">
                <div
                  className={`border rounded p-2 h-100 ${
                    isCurrent
                      ? 'border-primary bg-primary bg-opacity-10'
                      : allApproved
                      ? 'border-success bg-success bg-opacity-10'
                      : 'bg-light'
                  }`}
                >
                  <div className="d-flex justify-content-between align-items-center mb-1">
                    <span className="fw-bold small">
                      {st.stage_order}. {t(st.name_bn, st.name_en)}
                    </span>
                    <span className="badge bg-secondary" style={{ fontSize: '0.68rem' }}>
                      {st.execution_mode}
                    </span>
                  </div>
                  <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                    <WorkflowLevelLabel level={st.approval_level} /> • SLA {st.sla_hours}h
                  </div>
                  <div className="mt-2 d-flex flex-column gap-1">
                    {stageTasks.map((tk) => (
                      <div key={tk.id} className="d-flex justify-content-between align-items-center small bg-white rounded px-2 py-1 border">
                        <span className="text-truncate">
                          {tk.assignee_user_name || tk.assignee_role_code}
                          {tk.delegated_from_name ? ` (Delegate for ${tk.delegated_from_name})` : ''}
                        </span>
                        <span
                          className={`badge ${
                            tk.status === 'APPROVED'
                              ? 'bg-success'
                              : tk.status === 'REJECTED'
                              ? 'bg-danger'
                              : tk.status === 'PENDING'
                              ? 'bg-warning text-dark'
                              : 'bg-secondary'
                          }`}
                        >
                          {tk.status}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      <div className="row g-3">
        {/* Left Column: Summary + Action Workbench */}
        <div className="col-12 col-lg-6">
          <div className="idms-card p-3 mb-3">
            <h6 className="fw-bold mb-2">{t('আবেদনের সারসংক্ষেপ', 'Request Summary')}</h6>
            <div className="row g-2 small">
              <div className="col-6">
                <span className="text-muted">{t('দাখিলকারী', 'Submitted By')}:</span>
                <div className="fw-semibold">{instance.submitter?.name_en} ({instance.submitter?.role_code})</div>
              </div>
              <div className="col-6">
                <span className="text-muted">{t('দাখিলের তারিখ', 'Submitted At')}:</span>
                <div className="fw-semibold">{instance.submitted_at ? new Date(instance.submitted_at).toLocaleString() : '-'}</div>
              </div>
              <div className="col-6">
                <span className="text-muted">{t('প্রশাসনিক এলাকা', 'Jurisdiction')}:</span>
                <div className="fw-semibold">
                  {t(instance.administrative_unit?.name_bn, instance.administrative_unit?.name_en)} ({instance.administrative_unit?.tier_level})
                </div>
              </div>
              <div className="col-6">
                <span className="text-muted">{t('এসএলএ সময়সীমা', 'SLA Due At')}:</span>
                <div className="fw-semibold">{instance.due_at ? new Date(instance.due_at).toLocaleString() : '-'}</div>
              </div>
              {instance.submission_notes && (
                <div className="col-12 mt-2">
                  <span className="text-muted">{t('দাখিল নোট', 'Submission Notes')}:</span>
                  <div className="p-2 bg-light rounded border mt-1">{instance.submission_notes}</div>
                </div>
              )}
              {liveEntity && (
                <div className="col-12 mt-2">
                  <div className="p-2 rounded border bg-light d-flex justify-content-between align-items-center">
                    <div>
                      <div className="fw-semibold">{t('সংযুক্ত মডিউল রেকর্ড', 'Linked Module Record')}: {liveEntity.code}</div>
                      <div className="text-muted">Status: {liveEntity.status}</div>
                    </div>
                    {liveEntity.route && (
                      <Link to={liveEntity.route} className="btn btn-outline-primary btn-sm">
                        {t('রেকর্ড খুলুন', 'Open Record')}
                      </Link>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Action Workbench */}
          {availableActions.length > 0 && (
            <div className="idms-card p-3 mb-3">
              <h6 className="fw-bold mb-2">
                <i className="bi bi-check2-square text-success me-2"></i>
                {t('অনুমোদন ও সিদ্ধান্ত কার্যক্রম', 'Workflow Decision Workbench')}
              </h6>

              <div className="mb-2">
                <label className="form-label small fw-semibold">{t('মন্তব্য / নির্দেশনা', 'Comments / Remarks')}</label>
                <textarea
                  className="form-control form-control-sm"
                  rows="3"
                  value={comments}
                  onChange={(e) => setComments(e.target.value)}
                  placeholder={t('সিদ্ধান্তের কারণ বা মন্তব্য লিখুন (প্রত্যাখ্যান/সংশোধন/তথ্য অনুরোধে আবশ্যক)...', 'Enter remarks (mandatory for reject, return, or info request)...')}
                />
              </div>

              {showForwardPanel && (
                <div className="p-2 bg-light border rounded mb-2">
                  <div className="row g-2">
                    <div className="col-md-4">
                      <label className="form-label small">{t('লক্ষ্য ধাপ', 'Target Stage')}</label>
                      <select className="form-select form-select-sm" value={targetStageId} onChange={(e) => setTargetStageId(e.target.value)}>
                        <option value="">{t('স্বয়ংক্রিয় পরবর্তী ধাপ', 'Next stage')}</option>
                        {stages.map((st) => (
                          <option key={st.id} value={st.id}>
                            {st.stage_order}. {t(st.name_bn, st.name_en)}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small">{t('দায়িত্বপ্রাপ্ত রোল', 'Forward to Role')}</label>
                      <select className="form-select form-select-sm" value={forwardRoleCode} onChange={(e) => setForwardRoleCode(e.target.value)}>
                        <option value="">{t('ধাপ অনুযায়ী', 'Use stage default')}</option>
                        {(lookups.roles || []).map((r) => (
                          <option key={r} value={r}>{r}</option>
                        ))}
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small">{t('নির্দিষ্ট কর্মকর্তা', 'Forward to User')}</label>
                      <select className="form-select form-select-sm" value={forwardUserId} onChange={(e) => setForwardUserId(e.target.value)}>
                        <option value="">{t('ঐচ্ছিক', 'Optional')}</option>
                        {(lookups.users || []).map((u) => (
                          <option key={u.id} value={u.id}>{u.full_name_en} ({u.role_code})</option>
                        ))}
                      </select>
                    </div>
                  </div>
                </div>
              )}

              <div className="d-flex flex-wrap gap-2">
                {availableActions.includes('APPROVE') && (
                  <button
                    type="button"
                    className="btn btn-success btn-sm"
                    disabled={actionMutation.isPending}
                    onClick={() => actionMutation.mutate({ action: 'APPROVE' })}
                  >
                    <i className="bi bi-check-circle me-1"></i>
                    {t('অনুমোদন করুন', 'Approve')}
                  </button>
                )}
                {availableActions.includes('RETURN_FOR_CORRECTION') && (
                  <button
                    type="button"
                    className="btn btn-warning btn-sm"
                    disabled={actionMutation.isPending}
                    onClick={() => actionMutation.mutate({ action: 'RETURN_FOR_CORRECTION' })}
                  >
                    <i className="bi bi-arrow-counterclockwise me-1"></i>
                    {t('সংশোধনের জন্য ফেরত', 'Return for Correction')}
                  </button>
                )}
                {availableActions.includes('REQUEST_INFO') && (
                  <button
                    type="button"
                    className="btn btn-info btn-sm text-dark"
                    disabled={actionMutation.isPending}
                    onClick={() => actionMutation.mutate({ action: 'REQUEST_INFO' })}
                  >
                    <i className="bi bi-question-circle me-1"></i>
                    {t('অতিরিক্ত তথ্য চান', 'Request Info')}
                  </button>
                )}
                {availableActions.includes('FORWARD') && (
                  <button
                    type="button"
                    className="btn btn-secondary btn-sm"
                    disabled={actionMutation.isPending}
                    onClick={() => {
                      if (!showForwardPanel) {
                        setShowForwardPanel(true);
                      } else {
                        actionMutation.mutate({ action: 'FORWARD' });
                      }
                    }}
                  >
                    <i className="bi bi-forward me-1"></i>
                    {showForwardPanel ? t('ফরোয়ার্ড নিশ্চিত করুন', 'Confirm Forward') : t('ফরোয়ার্ড করুন', 'Forward')}
                  </button>
                )}
                {availableActions.includes('REJECT') && (
                  <button
                    type="button"
                    className="btn btn-danger btn-sm"
                    disabled={actionMutation.isPending}
                    onClick={() => actionMutation.mutate({ action: 'REJECT' })}
                  >
                    <i className="bi bi-x-circle me-1"></i>
                    {t('প্রত্যাখ্যান করুন', 'Reject')}
                  </button>
                )}
                {availableActions.includes('PROVIDE_INFO') && (
                  <button
                    type="button"
                    className="btn btn-primary btn-sm"
                    disabled={actionMutation.isPending}
                    onClick={() => actionMutation.mutate({ action: 'PROVIDE_INFO' })}
                  >
                    <i className="bi bi-reply me-1"></i>
                    {t('তথ্য প্রদান করুন', 'Provide Info')}
                  </button>
                )}
                {availableActions.includes('RESUBMIT') && (
                  <button
                    type="button"
                    className="btn btn-primary btn-sm"
                    disabled={actionMutation.isPending}
                    onClick={() => actionMutation.mutate({ action: 'RESUBMIT' })}
                  >
                    <i className="bi bi-arrow-repeat me-1"></i>
                    {t('পুনরায় দাখিল করুন', 'Resubmit')}
                  </button>
                )}
                {availableActions.includes('CANCEL') && (
                  <button
                    type="button"
                    className="btn btn-outline-dark btn-sm"
                    disabled={actionMutation.isPending}
                    onClick={() => actionMutation.mutate({ action: 'CANCEL' })}
                  >
                    {t('বাতিল করুন', 'Cancel Request')}
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Attachments */}
          <div className="idms-card p-3">
            <h6 className="fw-bold mb-2">
              <i className="bi bi-paperclip text-secondary me-2"></i>
              {t('সহায়ক নথিপত্র ও সংযুক্তি', 'Supporting Documents & Attachments')}
            </h6>
            {canOperate && (
              <div className="row g-2 mb-3">
                <div className="col-md-5">
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder={t('নথির শিরোনাম...', 'Document title...')}
                    value={uploadTitle}
                    onChange={(e) => setUploadTitle(e.target.value)}
                  />
                </div>
                <div className="col-md-5">
                  <input
                    type="file"
                    className="form-control form-control-sm"
                    onChange={(e) => setUploadFile(e.target.files?.[0] || null)}
                  />
                </div>
                <div className="col-md-2">
                  <button
                    type="button"
                    className="btn btn-outline-primary btn-sm w-100"
                    disabled={!uploadFile || uploadMutation.isPending}
                    onClick={() => uploadMutation.mutate()}
                  >
                    {t('আপলোড', 'Upload')}
                  </button>
                </div>
              </div>
            )}

            {attachments.length === 0 ? (
              <div className="small text-muted">{t('কোনো সংযুক্তি নেই।', 'No attachments uploaded.')}</div>
            ) : (
              <div className="list-group list-group-flush">
                {attachments.map((att) => (
                  <div key={att.id} className="list-group-item px-0 d-flex justify-content-between align-items-center">
                    <div className="small">
                      <div className="fw-semibold">{att.title || att.original_name}</div>
                      <div className="text-muted" style={{ fontSize: '0.72rem' }}>
                        {att.original_name} • {Math.round((att.file_size_bytes || 0) / 1024)} KB • {att.uploaded_by_name}
                      </div>
                    </div>
                    <button
                      type="button"
                      className="btn btn-outline-secondary btn-sm"
                      onClick={() => handleDownload(att)}
                    >
                      <i className="bi bi-download"></i>
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Right Column: Complete Immutable Decision History */}
        <div className="col-12 col-lg-6">
          <div className="idms-card p-3 h-100">
            <h6 className="fw-bold mb-3">
              <i className="bi bi-clock-history text-info me-2"></i>
              {t('অপরিবর্তনীয় সিদ্ধান্তের ইতিহাস', 'Complete Decision Audit History')}
            </h6>

            {transitions.length === 0 ? (
              <div className="text-muted small">{t('কোনো ইতিহাস নেই।', 'No transition history found.')}</div>
            ) : (
              <div className="d-flex flex-column gap-2">
                {transitions.map((tr) => (
                  <div key={tr.id} className="border rounded p-2 bg-light">
                    <div className="d-flex justify-content-between align-items-center mb-1">
                      <div className="d-flex align-items-center gap-2">
                        <WorkflowActionBadge action={tr.action} />
                        <span className="small fw-semibold">{tr.actor_name}</span>
                        {tr.actor_role && <span className="badge bg-white text-dark border">{tr.actor_role}</span>}
                      </div>
                      <span className="text-muted" style={{ fontSize: '0.75rem' }}>
                        {tr.transitioned_at ? new Date(tr.transitioned_at).toLocaleString() : '-'}
                      </span>
                    </div>

                    <div className="small text-muted mb-1">
                      {t('অবস্থা', 'State')}: <code>{tr.from_state}</code> &rarr; <code>{tr.to_state}</code>
                      {tr.from_stage && tr.to_stage && (
                        <>
                          {' '} | {t('ধাপ', 'Stage')}: {t(tr.from_stage.name_bn, tr.from_stage.name_en)} &rarr; {t(tr.to_stage.name_bn, tr.to_stage.name_en)}
                        </>
                      )}
                    </div>

                    {tr.acted_on_behalf_of_name && (
                      <div className="small text-primary mb-1">
                        <i className="bi bi-person-badge me-1"></i>
                        {t('প্রতিনিধিত্ব করেছেন', 'Acted on behalf of')}: {tr.acted_on_behalf_of_name}
                      </div>
                    )}

                    {tr.remarks && <div className="small bg-white p-2 rounded border">{tr.remarks}</div>}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default WorkflowInstanceDetailPage;
