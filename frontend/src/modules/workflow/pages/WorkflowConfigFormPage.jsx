import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { workflowApi } from '../../../api/workflowApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { formatWorkflowApiError } from '../utils/workflowPermissions';

const emptyStage = (order = 1) => ({
  stage_order: order,
  stage_code: order === 1 ? 'INITIAL_REVIEW' : `STAGE_${order}`,
  name_en: order === 1 ? 'Initial Review' : `Approval Stage ${order}`,
  name_bn: order === 1 ? 'প্রাথমিক পর্যালোচনা' : `অনুমোদন ধাপ ${order}`,
  approval_level: order === 1 ? 'INITIAL_REVIEW' : 'SUPERVISOR_APPROVAL',
  execution_mode: 'SEQUENTIAL',
  required_approvals: 1,
  sla_hours: 24,
  escalation_role_code: 'ROLE_DRRO',
  allow_delegation: true,
  allow_return_for_correction: true,
  allow_info_request: true,
  allow_forward: true,
  is_terminal: false,
  assignments: [
    {
      assignee_type: 'ROLE',
      role_code: 'ROLE_PIO',
      user_id: '',
      administrative_unit_id: '',
      authority_type: 'APPROVER',
      can_delegate: true,
      is_mandatory: true,
    },
  ],
});

const WorkflowConfigFormInner = ({ id, existingDefinition, lookups }) => {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const isEdit = Boolean(id && existingDefinition);

  const [form, setForm] = useState(() => ({
    code: existingDefinition?.code || '',
    name_en: existingDefinition?.name_en || '',
    name_bn: existingDefinition?.name_bn || '',
    module_code: existingDefinition?.module_code || 'RELIEF',
    entity_type: existingDefinition?.entity_type || 'RELIEF_REQUISITION',
    description: existingDefinition?.description || '',
    is_active: existingDefinition?.is_active ?? true,
    is_default: existingDefinition?.is_default ?? false,
    allow_self_approval: existingDefinition?.allow_self_approval ?? false,
    allow_delegation: existingDefinition?.allow_delegation ?? true,
    sla_hours: existingDefinition?.sla_hours ?? 48,
    escalation_enabled: existingDefinition?.escalation_enabled ?? true,
  }));

  const [stages, setStages] = useState(() =>
    existingDefinition?.stages?.length
      ? existingDefinition.stages.map((s) => ({
          ...s,
          escalation_role_code: s.escalation_role_code || '',
          assignments: (s.assignments || []).map((a) => ({
            ...a,
            role_code: a.role_code || '',
            user_id: a.user_id || '',
            administrative_unit_id: a.administrative_unit_id || '',
          })),
        }))
      : [emptyStage(1)]
  );

  const [errorMsg, setErrorMsg] = useState('');

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      isEdit ? workflowApi.updateDefinition(id, payload) : workflowApi.createDefinition(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow'] });
      navigate('/workflow/configurations');
    },
    onError: (err) => {
      setErrorMsg(formatWorkflowApiError(err, t('সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save workflow definition.')));
    },
  });

  const handleStageChange = (idx, field, value) => {
    setStages((prev) => prev.map((st, i) => (i === idx ? { ...st, [field]: value } : st)));
  };

  const handleAssignChange = (stageIdx, assignIdx, field, value) => {
    setStages((prev) =>
      prev.map((st, i) => {
        if (i !== stageIdx) return st;
        const nextAssigns = st.assignments.map((a, ai) => (ai === assignIdx ? { ...a, [field]: value } : a));
        return { ...st, assignments: nextAssigns };
      })
    );
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setErrorMsg('');

    const payload = {
      ...form,
      sla_hours: Number(form.sla_hours) || 48,
      stages: stages.map((st, idx) => ({
        stage_order: Number(st.stage_order) || idx + 1,
        stage_code: st.stage_code,
        name_en: st.name_en,
        name_bn: st.name_bn || st.name_en,
        approval_level: st.approval_level,
        execution_mode: st.execution_mode,
        required_approvals: Number(st.required_approvals) || 1,
        sla_hours: Number(st.sla_hours) || 24,
        escalation_role_code: st.escalation_role_code || null,
        allow_delegation: Boolean(st.allow_delegation),
        allow_return_for_correction: Boolean(st.allow_return_for_correction),
        allow_info_request: Boolean(st.allow_info_request),
        allow_forward: Boolean(st.allow_forward),
        is_terminal: Boolean(st.is_terminal) || idx === stages.length - 1,
        assignments: (st.assignments || []).map((a) => ({
          assignee_type: a.assignee_type,
          role_code: a.assignee_type === 'ROLE' ? a.role_code || null : null,
          user_id: a.assignee_type === 'USER' ? a.user_id || null : null,
          administrative_unit_id: a.administrative_unit_id || null,
          authority_type: a.authority_type || 'APPROVER',
          can_delegate: Boolean(a.can_delegate ?? true),
          is_mandatory: Boolean(a.is_mandatory ?? true),
        })),
      })),
    };

    saveMutation.mutate(payload);
  };

  return (
    <form onSubmit={handleSubmit}>
      {errorMsg && <AlertBanner variant="danger" message={errorMsg} onClose={() => setErrorMsg('')} />}

      <div className="idms-card p-3 mb-3">
        <h6 className="fw-bold mb-3">{t('মৌলিক তথ্য ও নীতিমালা', 'General Settings & Policy')}</h6>
        <div className="row g-3">
          <div className="col-md-4">
            <label className="form-label small fw-semibold">{t('কোড', 'Workflow Code')}</label>
            <input
              type="text"
              className="form-control form-control-sm"
              value={form.code}
              disabled={isEdit}
              required
              onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })}
              placeholder="WF_CUSTOM_RELIEF"
            />
          </div>
          <div className="col-md-4">
            <label className="form-label small fw-semibold">{t('নাম (ইংরেজি)', 'Name (English)')}</label>
            <input
              type="text"
              className="form-control form-control-sm"
              value={form.name_en}
              required
              onChange={(e) => setForm({ ...form, name_en: e.target.value })}
            />
          </div>
          <div className="col-md-4">
            <label className="form-label small fw-semibold">{t('নাম (বাংলা)', 'Name (Bangla)')}</label>
            <input
              type="text"
              className="form-control form-control-sm"
              value={form.name_bn}
              required
              onChange={(e) => setForm({ ...form, name_bn: e.target.value })}
            />
          </div>

          <div className="col-md-3">
            <label className="form-label small fw-semibold">{t('মডিউল', 'Module')}</label>
            <select
              className="form-select form-select-sm"
              value={form.module_code}
              onChange={(e) => setForm({ ...form, module_code: e.target.value })}
            >
              {(lookups.modules || []).map((m) => (
                <option key={m} value={m}>{m}</option>
              ))}
            </select>
          </div>

          <div className="col-md-3">
            <label className="form-label small fw-semibold">{t('এনটিটি ধরন', 'Entity Type')}</label>
            <select
              className="form-select form-select-sm"
              value={form.entity_type}
              onChange={(e) => setForm({ ...form, entity_type: e.target.value })}
            >
              {(lookups.entity_types || []).map((et) => (
                <option key={et} value={et}>{et}</option>
              ))}
            </select>
          </div>

          <div className="col-md-2">
            <label className="form-label small fw-semibold">{t('মোট এসএলএ (ঘণ্টা)', 'SLA (Hours)')}</label>
            <input
              type="number"
              className="form-control form-control-sm"
              min="1"
              value={form.sla_hours}
              onChange={(e) => setForm({ ...form, sla_hours: e.target.value })}
            />
          </div>

          <div className="col-md-4 d-flex flex-wrap align-items-end gap-3 pb-1">
            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="wf_active"
                checked={form.is_active}
                onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
              />
              <label className="form-check-label small" htmlFor="wf_active">{t('সক্রিয়', 'Active')}</label>
            </div>
            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="wf_default"
                checked={form.is_default}
                onChange={(e) => setForm({ ...form, is_default: e.target.checked })}
              />
              <label className="form-check-label small" htmlFor="wf_default">{t('ডিফল্ট', 'Default')}</label>
            </div>
            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="wf_delegation"
                checked={form.allow_delegation}
                onChange={(e) => setForm({ ...form, allow_delegation: e.target.checked })}
              />
              <label className="form-check-label small" htmlFor="wf_delegation">{t('প্রতিনিধিত্ব', 'Allow Delegation')}</label>
            </div>
            <div className="form-check">
              <input
                type="checkbox"
                className="form-check-input"
                id="wf_self"
                checked={form.allow_self_approval}
                onChange={(e) => setForm({ ...form, allow_self_approval: e.target.checked })}
              />
              <label className="form-check-label small" htmlFor="wf_self">{t('স্ব-অনুমোদন', 'Allow Self-Approval')}</label>
            </div>
          </div>

          <div className="col-12">
            <label className="form-label small fw-semibold">{t('বিবরণ', 'Description')}</label>
            <textarea
              className="form-control form-control-sm"
              rows="2"
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
            />
          </div>
        </div>
      </div>

      {/* Stages Builder */}
      <div className="idms-card p-3 mb-3">
        <div className="d-flex justify-content-between align-items-center mb-3">
          <h6 className="fw-bold mb-0">{t('অনুমোদন ধাপসমূহ (Stages)', 'Configurable Approval Stages')}</h6>
          <button
            type="button"
            className="btn btn-outline-primary btn-sm"
            onClick={() => setStages((prev) => [...prev, emptyStage(prev.length + 1)])}
          >
            <i className="bi bi-plus-lg me-1"></i>
            {t('ধাপ যোগ করুন', 'Add Stage')}
          </button>
        </div>

        <div className="d-flex flex-column gap-3">
          {stages.map((st, sIdx) => (
            <div key={sIdx} className="border rounded p-3 bg-light">
              <div className="d-flex justify-content-between align-items-center mb-2">
                <strong className="small">
                  {t('ধাপ', 'Stage')} #{sIdx + 1}
                </strong>
                {stages.length > 1 && (
                  <button
                    type="button"
                    className="btn btn-outline-danger btn-sm"
                    onClick={() => setStages((prev) => prev.filter((_, i) => i !== sIdx))}
                  >
                    <i className="bi bi-trash"></i>
                  </button>
                )}
              </div>

              <div className="row g-2 mb-2">
                <div className="col-md-2">
                  <label className="form-label small">{t('ক্রম', 'Order')}</label>
                  <input
                    type="number"
                    className="form-control form-control-sm"
                    min="1"
                    value={st.stage_order}
                    onChange={(e) => handleStageChange(sIdx, 'stage_order', e.target.value)}
                  />
                </div>
                <div className="col-md-3">
                  <label className="form-label small">{t('ধাপ কোড', 'Stage Code')}</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    required
                    value={st.stage_code}
                    onChange={(e) => handleStageChange(sIdx, 'stage_code', e.target.value.toUpperCase())}
                  />
                </div>
                <div className="col-md-4">
                  <label className="form-label small">{t('নাম (ইংরেজি)', 'Stage Name (EN)')}</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    required
                    value={st.name_en}
                    onChange={(e) => handleStageChange(sIdx, 'name_en', e.target.value)}
                  />
                </div>
                <div className="col-md-3">
                  <label className="form-label small">{t('নাম (বাংলা)', 'Stage Name (BN)')}</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    value={st.name_bn}
                    onChange={(e) => handleStageChange(sIdx, 'name_bn', e.target.value)}
                  />
                </div>

                <div className="col-md-3">
                  <label className="form-label small">{t('অনুমোদন স্তর', 'Approval Level')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={st.approval_level}
                    onChange={(e) => handleStageChange(sIdx, 'approval_level', e.target.value)}
                  >
                    {(lookups.approval_levels || []).map((lvl) => (
                      <option key={lvl} value={lvl}>{lvl}</option>
                    ))}
                  </select>
                </div>

                <div className="col-md-3">
                  <label className="form-label small">{t('কার্যপদ্ধতি', 'Execution Mode')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={st.execution_mode}
                    onChange={(e) => handleStageChange(sIdx, 'execution_mode', e.target.value)}
                  >
                    {(lookups.execution_modes || ['SEQUENTIAL', 'PARALLEL_ALL', 'PARALLEL_ANY']).map((em) => (
                      <option key={em} value={em}>{em}</option>
                    ))}
                  </select>
                </div>

                <div className="col-md-2">
                  <label className="form-label small">{t('প্রয়োজনীয় অনুমোদন', 'Req. Approvals')}</label>
                  <input
                    type="number"
                    className="form-control form-control-sm"
                    min="1"
                    value={st.required_approvals}
                    onChange={(e) => handleStageChange(sIdx, 'required_approvals', e.target.value)}
                  />
                </div>

                <div className="col-md-2">
                  <label className="form-label small">SLA (h)</label>
                  <input
                    type="number"
                    className="form-control form-control-sm"
                    min="1"
                    value={st.sla_hours}
                    onChange={(e) => handleStageChange(sIdx, 'sla_hours', e.target.value)}
                  />
                </div>

                <div className="col-md-2">
                  <label className="form-label small">{t('এস্কেলেশন রোল', 'Escalation Role')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={st.escalation_role_code}
                    onChange={(e) => handleStageChange(sIdx, 'escalation_role_code', e.target.value)}
                  >
                    <option value="">None</option>
                    {(lookups.roles || []).map((r) => (
                      <option key={r} value={r}>{r}</option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Stage Assignments */}
              <div className="bg-white p-2 rounded border">
                <div className="d-flex justify-content-between align-items-center mb-2">
                  <span className="small fw-semibold">{t('দায়িত্বপ্রাপ্ত অনুমোদনকারী', 'Stage Assignments')}</span>
                  <button
                    type="button"
                    className="btn btn-outline-secondary btn-sm"
                    onClick={() =>
                      setStages((prev) =>
                        prev.map((item, i) =>
                          i === sIdx
                            ? {
                                ...item,
                                assignments: [
                                  ...item.assignments,
                                  {
                                    assignee_type: 'ROLE',
                                    role_code: 'ROLE_DRRO',
                                    user_id: '',
                                    administrative_unit_id: '',
                                    authority_type: 'APPROVER',
                                  },
                                ],
                              }
                            : item
                        )
                      )
                    }
                  >
                    + {t('দায়িত্ব যোগ', 'Add Assignee')}
                  </button>
                </div>

                {(st.assignments || []).map((a, aIdx) => (
                  <div key={aIdx} className="row g-2 align-items-center mb-1">
                    <div className="col-md-3">
                      <select
                        className="form-select form-select-sm"
                        value={a.assignee_type}
                        onChange={(e) => handleAssignChange(sIdx, aIdx, 'assignee_type', e.target.value)}
                      >
                        {(lookups.assignee_types || ['ROLE', 'USER', 'ADMINISTRATIVE_UNIT']).map((at) => (
                          <option key={at} value={at}>{at}</option>
                        ))}
                      </select>
                    </div>

                    <div className="col-md-4">
                      {a.assignee_type === 'ROLE' && (
                        <select
                          className="form-select form-select-sm"
                          value={a.role_code}
                          onChange={(e) => handleAssignChange(sIdx, aIdx, 'role_code', e.target.value)}
                        >
                          <option value="">{t('রোল নির্বাচন...', 'Select role...')}</option>
                          {(lookups.roles || []).map((r) => (
                            <option key={r} value={r}>{r}</option>
                          ))}
                        </select>
                      )}
                      {a.assignee_type === 'USER' && (
                        <select
                          className="form-select form-select-sm"
                          value={a.user_id}
                          onChange={(e) => handleAssignChange(sIdx, aIdx, 'user_id', e.target.value)}
                        >
                          <option value="">{t('কর্মকর্তা নির্বাচন...', 'Select user...')}</option>
                          {(lookups.users || []).map((u) => (
                            <option key={u.id} value={u.id}>{u.full_name_en} ({u.role_code})</option>
                          ))}
                        </select>
                      )}
                      {a.assignee_type === 'ADMINISTRATIVE_UNIT' && (
                        <select
                          className="form-select form-select-sm"
                          value={a.administrative_unit_id}
                          onChange={(e) => handleAssignChange(sIdx, aIdx, 'administrative_unit_id', e.target.value)}
                        >
                          <option value="">{t('এলাকা নির্বাচন...', 'Select unit...')}</option>
                          {(lookups.administrative_units || []).map((u) => (
                            <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)}</option>
                          ))}
                        </select>
                      )}
                    </div>

                    <div className="col-md-3">
                      <select
                        className="form-select form-select-sm"
                        value={a.authority_type}
                        onChange={(e) => handleAssignChange(sIdx, aIdx, 'authority_type', e.target.value)}
                      >
                        {(lookups.authority_types || ['REVIEWER', 'APPROVER', 'ENDORSE', 'OBSERVER']).map((auth) => (
                          <option key={auth} value={auth}>{auth}</option>
                        ))}
                      </select>
                    </div>

                    <div className="col-md-2 text-end">
                      {st.assignments.length > 1 && (
                        <button
                          type="button"
                          className="btn btn-outline-danger btn-sm"
                          onClick={() =>
                            setStages((prev) =>
                              prev.map((item, i) =>
                                i === sIdx
                                  ? { ...item, assignments: item.assignments.filter((_, ai) => ai !== aIdx) }
                                  : item
                              )
                            )
                          }
                        >
                          <i className="bi bi-x"></i>
                        </button>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>

      <div className="d-flex justify-content-end gap-2">
        <Link to="/workflow/configurations" className="btn btn-outline-secondary btn-sm">
          {t('বাতিল', 'Cancel')}
        </Link>
        <button type="submit" className="btn btn-primary btn-sm" disabled={saveMutation.isPending}>
          {saveMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('কনফিগারেশন সংরক্ষণ করুন', 'Save Configuration')}
        </button>
      </div>
    </form>
  );
};

export const WorkflowConfigFormPage = () => {
  const { id } = useParams();
  const { t } = useLanguage();
  const isEdit = Boolean(id);

  const { data: lookupsRes, isLoading: loadingLookups } = useQuery({
    queryKey: ['workflow', 'lookups'],
    queryFn: () => workflowApi.getLookups(),
  });

  const { data: defRes, isLoading: loadingDef } = useQuery({
    queryKey: ['workflow', 'definition', id],
    queryFn: () => workflowApi.getDefinition(id),
    enabled: isEdit,
  });

  if (loadingLookups || (isEdit && loadingDef)) {
    return <Loader message="কনফিগারেশন ফর্ম লোড হচ্ছে..." messageEn="Loading workflow form..." />;
  }

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-sliders text-primary me-2"></i>
            {isEdit
              ? t('কর্মপ্রবাহ কনফিগারেশন সম্পাদনা', 'Edit Workflow Configuration')
              : t('নতুন কর্মপ্রবাহ কনফিগারেশন', 'Create Workflow Configuration')}
          </h4>
        </div>
        <Link to="/workflow/configurations" className="btn btn-outline-secondary btn-sm">
          {t('ফিরে যান', 'Back')}
        </Link>
      </div>

      <WorkflowConfigFormInner
        key={id || 'new'}
        id={id}
        existingDefinition={defRes?.data}
        lookups={lookupsRes?.data || {}}
      />
    </div>
  );
};

export default WorkflowConfigFormPage;
