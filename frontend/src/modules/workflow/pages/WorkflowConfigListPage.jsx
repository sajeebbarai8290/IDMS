import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { workflowApi } from '../../../api/workflowApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import { canManageWorkflowConfig, formatWorkflowApiError } from '../utils/workflowPermissions';

export const WorkflowConfigListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [moduleCode, setModuleCode] = useState('');
  const [search, setSearch] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);

  const canManage = canManageWorkflowConfig(user);

  const { data: defRes, isLoading, error } = useQuery({
    queryKey: ['workflow', 'definitions', { moduleCode, search }],
    queryFn: () =>
      workflowApi.getDefinitions({
        module_code: moduleCode || undefined,
        search: search || undefined,
      }),
  });

  const toggleMutation = useMutation({
    mutationFn: ({ id, isActive }) => workflowApi.toggleDefinitionStatus(id, isActive),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow'] });
      setFeedback({ variant: 'success', message: t('কর্মপ্রবাহের অবস্থা হালনাগাদ হয়েছে।', 'Workflow status updated.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWorkflowApiError(err, t('অবস্থা পরিবর্তন ব্যর্থ।', 'Failed to toggle status.')) });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => workflowApi.deleteDefinition(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflow'] });
      setDeleteTarget(null);
      setFeedback({ variant: 'success', message: t('কর্মপ্রবাহ কনফিগারেশন মুছে ফেলা হয়েছে।', 'Workflow definition deleted.') });
    },
    onError: (err) => {
      setDeleteTarget(null);
      setFeedback({ variant: 'danger', message: formatWorkflowApiError(err, t('মুছে ফেলা ব্যর্থ।', 'Delete failed.')) });
    },
  });

  const definitions = defRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-sliders text-primary me-2"></i>
            {t('কর্মপ্রবাহ কনফিগারেশন ব্যবস্থাপনা', 'Workflow Configuration Registry')}
          </h4>
          <p className="text-muted small mb-0">
            {t('মডিউলভিত্তিক অনুমোদন ধাপ, সমান্তরাল/ক্রমিক গেট, এবং দায়িত্ব নির্ধারণ', 'Configure module approval chains, sequential or parallel gates, SLAs, and authority')}
          </p>
        </div>

        {canManage && (
          <Link to="/workflow/configurations/new" className="btn btn-primary btn-sm">
            <i className="bi bi-plus-circle me-1"></i>
            {t('নতুন কর্মপ্রবাহ তৈরি করুন', 'New Workflow Definition')}
          </Link>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <div className="idms-card p-3 mb-3">
        <div className="row g-2">
          <div className="col-md-6">
            <input
              type="text"
              className="form-control form-control-sm"
              placeholder={t('কোড বা নাম দিয়ে খুঁজুন...', 'Search by code or workflow name...')}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <div className="col-md-3">
            <select className="form-select form-select-sm" value={moduleCode} onChange={(e) => setModuleCode(e.target.value)}>
              <option value="">{t('সকল মডিউল', 'All Modules')}</option>
              <option value="RELIEF">RELIEF</option>
              <option value="DAMAGE">DAMAGE</option>
              <option value="PROJECT">PROJECT</option>
              <option value="BENEFICIARY">BENEFICIARY</option>
              <option value="RESOURCE">RESOURCE</option>
              <option value="WAREHOUSE">WAREHOUSE</option>
              <option value="VOLUNTEER">VOLUNTEER</option>
              <option value="GENERAL">GENERAL</option>
            </select>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader message="কর্মপ্রবাহ কনফিগারেশন লোড হচ্ছে..." messageEn="Loading workflow definitions..." />
      ) : error ? (
        <AlertBanner variant="danger" message={formatWorkflowApiError(error, t('লোড ব্যর্থ।', 'Failed to load definitions.'))} />
      ) : definitions.length === 0 ? (
        <EmptyState message={t('কোনো কর্মপ্রবাহ কনফিগারেশন পাওয়া যায়নি।', 'No workflow definitions found.')} />
      ) : (
        <div className="row g-3">
          {definitions.map((def) => (
            <div key={def.id} className="col-12 col-lg-6">
              <div className="idms-card p-3 h-100">
                <div className="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <div>
                    <div className="d-flex flex-wrap align-items-center gap-2">
                      <h6 className="fw-bold mb-0">{t(def.name_bn, def.name_en)}</h6>
                      <span className={`badge ${def.is_active ? 'bg-success' : 'bg-secondary'}`}>
                        {def.is_active ? t('সক্রিয়', 'Active') : t('নিষ্ক্রিয়', 'Inactive')}
                      </span>
                      {def.is_default && <span className="badge bg-info text-dark">Default</span>}
                    </div>
                    <div className="text-muted small">
                      <code>{def.code}</code> • {def.module_code} / {def.entity_type} • v{def.version}
                    </div>
                  </div>

                  {canManage && (
                    <div className="d-flex gap-1">
                      <button
                        type="button"
                        className={`btn btn-sm ${def.is_active ? 'btn-outline-warning' : 'btn-outline-success'}`}
                        onClick={() => toggleMutation.mutate({ id: def.id, isActive: !def.is_active })}
                      >
                        {def.is_active ? t('নিষ্ক্রিয়', 'Deactivate') : t('সক্রিয়', 'Activate')}
                      </button>
                      <Link to={`/workflow/configurations/${def.id}/edit`} className="btn btn-outline-primary btn-sm">
                        <i className="bi bi-pencil"></i>
                      </Link>
                      {!def.is_system && (
                        <button
                          type="button"
                          className="btn btn-outline-danger btn-sm"
                          onClick={() => setDeleteTarget(def)}
                        >
                          <i className="bi bi-trash"></i>
                        </button>
                      )}
                    </div>
                  )}
                </div>

                {def.description && <p className="small text-muted mb-2">{def.description}</p>}

                <div className="d-flex flex-wrap gap-2 small mb-2">
                  <span className="badge bg-light text-dark border">SLA: {def.sla_hours}h</span>
                  <span className="badge bg-light text-dark border">
                    {def.allow_self_approval ? t('স্ব-অনুমোদন চালু', 'Self-Approval: Yes') : t('৪-চোখ নীতি', '4-Eye SoD')}
                  </span>
                  <span className="badge bg-light text-dark border">
                    {def.allow_delegation ? t('প্রতিনিধিত্ব অনুমোদিত', 'Delegation: Yes') : t('প্রতিনিধিত্ব বন্ধ', 'Delegation: No')}
                  </span>
                  <span className="badge bg-light text-dark border">
                    {t('আবেদন', 'Instances')}: {def.instances_count ?? 0}
                  </span>
                </div>

                <div className="d-flex flex-wrap gap-1">
                  {(def.stages || []).map((st) => (
                    <span key={st.id} className="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">
                      {st.stage_order}. {t(st.name_bn, st.name_en)} [{st.execution_mode}]
                    </span>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <ConfirmDialog
        show={!!deleteTarget}
        title={t('কর্মপ্রবাহ মুছে ফেলুন', 'Delete Workflow Definition')}
        message={t(
          `আপনি কি "${deleteTarget?.name_bn || deleteTarget?.name_en}" মুছে ফেলতে চান?`,
          `Are you sure you want to delete "${deleteTarget?.name_en}"?`
        )}
        confirmLabel={t('মুছে ফেলুন', 'Delete')}
        confirmVariant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
};

export default WorkflowConfigListPage;
