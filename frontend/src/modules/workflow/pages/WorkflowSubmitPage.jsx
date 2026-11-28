import React, { useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useMutation, useQuery } from '@tanstack/react-query';
import { workflowApi } from '../../../api/workflowApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { canSubmitWorkflow, formatWorkflowApiError } from '../utils/workflowPermissions';

export const WorkflowSubmitPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const navigate = useNavigate();

  const [entityType, setEntityType] = useState('RELIEF_REQUISITION');
  const [entitySearch, setEntitySearch] = useState('');
  const [selectedEntityId, setSelectedEntityId] = useState('');
  const [workflowDefId, setWorkflowDefId] = useState('');
  const [adminUnitId, setAdminUnitId] = useState('');
  const [titleEn, setTitleEn] = useState('');
  const [titleBn, setTitleBn] = useState('');
  const [entityReference, setEntityReference] = useState('');
  const [priority, setPriority] = useState('NORMAL');
  const [comments, setComments] = useState('');
  const [errorMsg, setErrorMsg] = useState('');

  const { data: lookupsRes, isLoading } = useQuery({
    queryKey: ['workflow', 'lookups'],
    queryFn: () => workflowApi.getLookups(),
  });

  const { data: targetsRes, isFetching: loadingTargets } = useQuery({
    queryKey: ['workflow', 'targets', entityType, entitySearch],
    queryFn: () => workflowApi.getTargetEntities({ entity_type: entityType, search: entitySearch }),
    enabled: entityType !== 'GENERAL_REQUEST',
  });

  const submitMutation = useMutation({
    mutationFn: (payload) => workflowApi.createInstance(payload),
    onSuccess: (res) => {
      const id = res?.data?.id;
      navigate(id ? `/workflow/instances/${id}` : '/workflow/instances');
    },
    onError: (err) => {
      setErrorMsg(formatWorkflowApiError(err, t('কর্মপ্রবাহ দাখিল ব্যর্থ হয়েছে।', 'Failed to submit workflow.')));
    },
  });

  const lookups = lookupsRes?.data || {};
  const definitions = useMemo(() => {
    return (lookups.definitions || []).filter((d) => d.entity_type === entityType || d.module_code === 'GENERAL');
  }, [lookups.definitions, entityType]);

  const activeDefinition = useMemo(() => {
    if (workflowDefId) {
      return (lookups.definitions || []).find((d) => d.id === workflowDefId) || null;
    }
    return definitions.find((d) => d.is_default && d.entity_type === entityType) || definitions[0] || null;
  }, [lookups.definitions, definitions, workflowDefId, entityType]);

  const targets = targetsRes?.data || [];

  const handleSelectTarget = (target) => {
    setSelectedEntityId(target.entity_id);
    setTitleEn(target.title_en || '');
    setTitleBn(target.title_bn || '');
    setEntityReference(target.entity_reference || '');
    setAdminUnitId(target.administrative_unit_id || '');
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setErrorMsg('');

    if (entityType !== 'GENERAL_REQUEST' && !selectedEntityId) {
      setErrorMsg(t('অনুগ্রহ করে একটি লক্ষ্য রেকর্ড নির্বাচন করুন।', 'Please select a target record from the module list.'));
      return;
    }

    submitMutation.mutate({
      workflow_definition_id: activeDefinition?.id || undefined,
      entity_type: entityType,
      entity_id: entityType === 'GENERAL_REQUEST' ? undefined : selectedEntityId,
      administrative_unit_id: adminUnitId || undefined,
      title_en: titleEn || undefined,
      title_bn: titleBn || undefined,
      entity_reference: entityReference || undefined,
      priority,
      comments,
    });
  };

  if (isLoading) {
    return <Loader message="তথ্য লোড হচ্ছে..." messageEn="Loading workflow options..." />;
  }

  if (!canSubmitWorkflow(user)) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner
          variant="warning"
          message={t(
            'আপনার বর্তমান ভূমিকা নতুন অপারেশনাল কর্মপ্রবাহ আবেদন দাখিলের জন্য অনুমোদিত নয়।',
            'Your current role is not authorized to submit operational workflow requests.'
          )}
        />
      </div>
    );
  }

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-send-check-fill text-primary me-2"></i>
            {t('নতুন কর্মপ্রবাহ অনুমোদন আবেদন', 'Initiate Workflow Approval')}
          </h4>
          <p className="text-muted small mb-0">
            {t('যেকোনো আইডিএমএস মডিউল রেকর্ড কনফিগারযোগ্য অনুমোদন চেনে প্রেরণ করুন', 'Route an IDMS module record through a configurable multi-tier approval workflow')}
          </p>
        </div>
        <Link to="/workflow/instances" className="btn btn-outline-secondary btn-sm">
          <i className="bi bi-arrow-left me-1"></i>
          {t('ফিরে যান', 'Back')}
        </Link>
      </div>

      {errorMsg && <AlertBanner variant="danger" message={errorMsg} onClose={() => setErrorMsg('')} />}

      <div className="row g-3">
        <div className="col-12 col-lg-7">
          <form className="idms-card p-3" onSubmit={handleSubmit}>
            <div className="row g-3">
              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('মডিউল / রেকর্ডের ধরন', 'Target Entity Type')}</label>
                <select
                  className="form-select form-select-sm"
                  value={entityType}
                  onChange={(e) => {
                    setEntityType(e.target.value);
                    setSelectedEntityId('');
                    setWorkflowDefId('');
                  }}
                >
                  {(lookups.entity_types || []).map((et) => (
                    <option key={et} value={et}>{et}</option>
                  ))}
                </select>
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('কর্মপ্রবাহ কনফিগারেশন', 'Workflow Definition')}</label>
                <select
                  className="form-select form-select-sm"
                  value={activeDefinition?.id || ''}
                  onChange={(e) => setWorkflowDefId(e.target.value)}
                >
                  {definitions.map((d) => (
                    <option key={d.id} value={d.id}>
                      {t(d.name_bn, d.name_en)} ({d.code})
                    </option>
                  ))}
                </select>
              </div>

              {entityType !== 'GENERAL_REQUEST' ? (
                <div className="col-12">
                  <label className="form-label small fw-semibold">{t('টার্গেট রেকর্ড খুঁজুন ও নির্বাচন করুন', 'Search & Select Target Record')}</label>
                  <input
                    type="text"
                    className="form-control form-control-sm mb-2"
                    placeholder={t('কোড বা নাম দিয়ে খুঁজুন...', 'Search by code or title...')}
                    value={entitySearch}
                    onChange={(e) => setEntitySearch(e.target.value)}
                  />
                  <div className="border rounded p-2" style={{ maxHeight: 200, overflowY: 'auto' }}>
                    {loadingTargets ? (
                      <div className="small text-muted p-2">{t('খোঁজা হচ্ছে...', 'Searching records...')}</div>
                    ) : targets.length === 0 ? (
                      <div className="small text-muted p-2">{t('আপনার অধিক্ষেত্রে কোনো রেকর্ড পাওয়া যায়নি।', 'No matching scoped records found.')}</div>
                    ) : (
                      targets.map((item) => (
                        <div
                          key={item.entity_id}
                          className={`p-2 rounded mb-1 cursor-pointer border ${selectedEntityId === item.entity_id ? 'border-primary bg-primary bg-opacity-10' : 'bg-light'}`}
                          style={{ cursor: 'pointer' }}
                          onClick={() => handleSelectTarget(item)}
                        >
                          <div className="d-flex justify-content-between align-items-center">
                            <strong className="small">{item.entity_reference}</strong>
                            <span className="badge bg-secondary">{item.status}</span>
                          </div>
                          <div className="small text-muted">{t(item.title_bn, item.title_en)}</div>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              ) : (
                <>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('প্রশাসনিক এলাকা', 'Administrative Unit')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={adminUnitId}
                      onChange={(e) => setAdminUnitId(e.target.value)}
                      required
                    >
                      <option value="">{t('নির্বাচন করুন...', 'Select unit...')}</option>
                      {(lookups.administrative_units || []).map((u) => (
                        <option key={u.id} value={u.id}>
                          {t(u.name_bn, u.name_en)} ({u.tier_level})
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('রেফারেন্স কোড', 'Reference Code')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={entityReference}
                      onChange={(e) => setEntityReference(e.target.value)}
                    />
                  </div>
                </>
              )}

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('শিরোনাম (ইংরেজি)', 'Title (English)')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  value={titleEn}
                  onChange={(e) => setTitleEn(e.target.value)}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('শিরোনাম (বাংলা)', 'Title (Bangla)')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  value={titleBn}
                  onChange={(e) => setTitleBn(e.target.value)}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('অগ্রাধিকার', 'Priority')}</label>
                <select
                  className="form-select form-select-sm"
                  value={priority}
                  onChange={(e) => setPriority(e.target.value)}
                >
                  {(lookups.priorities || []).map((p) => (
                    <option key={p} value={p}>{p}</option>
                  ))}
                </select>
              </div>

              <div className="col-12">
                <label className="form-label small fw-semibold">{t('দাখিল মন্তব্য / নোট', 'Submission Comments')}</label>
                <textarea
                  className="form-control form-control-sm"
                  rows="3"
                  value={comments}
                  onChange={(e) => setComments(e.target.value)}
                  placeholder={t('অনুমোদনের যৌক্তিকতা বা মন্তব্য লিখুন...', 'Enter justification or remarks...')}
                />
              </div>

              <div className="col-12 d-flex justify-content-end gap-2">
                <Link to="/workflow/instances" className="btn btn-outline-secondary btn-sm">
                  {t('বাতিল', 'Cancel')}
                </Link>
                <button type="submit" className="btn btn-primary btn-sm" disabled={submitMutation.isPending}>
                  {submitMutation.isPending ? t('দাখিল হচ্ছে...', 'Submitting...') : t('কর্মপ্রবাহ দাখিল করুন', 'Submit Workflow')}
                </button>
              </div>
            </div>
          </form>
        </div>

        {/* Visual Pipeline Preview */}
        <div className="col-12 col-lg-5">
          <div className="idms-card p-3">
            <h6 className="fw-bold mb-3">
              <i className="bi bi-diagram-2-fill text-success me-2"></i>
              {t('অনুমোদন ধাপের প্রাকদর্শন', 'Approval Chain Preview')}
            </h6>
            {!activeDefinition ? (
              <div className="text-muted small">{t('কোনো সক্রিয় কর্মপ্রবাহ নির্বাচিত হয়নি।', 'No active workflow selected.')}</div>
            ) : (
              <div>
                <div className="mb-2 small">
                  <strong>{t(activeDefinition.name_bn, activeDefinition.name_en)}</strong>
                  <div className="text-muted">{activeDefinition.description}</div>
                  <div className="mt-1">
                    <span className="badge bg-light text-dark border me-1">SLA: {activeDefinition.sla_hours}h</span>
                    <span className="badge bg-light text-dark border">
                      {activeDefinition.allow_self_approval ? t('স্ব-অনুমোদন অনুমোদিত', 'Self-Approval Allowed') : t('৪-চোখ নীতি প্রযোজ্য', '4-Eye SoD Enforced')}
                    </span>
                  </div>
                </div>
                <div className="list-group list-group-flush">
                  {(activeDefinition.stages || []).map((st) => (
                    <div key={st.id} className="list-group-item px-0">
                      <div className="d-flex justify-content-between align-items-center">
                        <span className="fw-semibold small">
                          {st.stage_order}. {t(st.name_bn, st.name_en)}
                        </span>
                        <span className="badge bg-info text-dark">{st.execution_mode}</span>
                      </div>
                      <div className="small text-muted">
                        {st.approval_level} • SLA {st.sla_hours}h
                      </div>
                      <div className="mt-1 d-flex flex-wrap gap-1">
                        {(st.assignments || []).map((a) => (
                          <span key={a.id} className="badge bg-secondary">
                            {a.role_code || a.user_name || a.administrative_unit_name || a.assignee_type} ({a.authority_type})
                          </span>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default WorkflowSubmitPage;
