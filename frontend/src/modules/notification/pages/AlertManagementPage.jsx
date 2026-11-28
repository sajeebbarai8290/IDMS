import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationApi } from '../../../api/notificationApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  AlertStatusBadge,
  CategoryLabel,
  SeverityBadge,
} from '../components/NotificationBadges';
import {
  canAcknowledgeAlerts,
  canManageAlertRules,
  canTriggerAlerts,
  CATEGORY_META,
  formatNotificationApiError,
} from '../utils/notificationPermissions';

const emptyRuleForm = {
  rule_code: '',
  name_bn: '',
  name_en: '',
  category: 'INVENTORY_ALERT',
  event_type: 'LOW_STOCK_THRESHOLD',
  severity: 'HIGH',
  threshold: '20',
  operator: '<=',
  escalation_delay_minutes: 30,
  escalation_role_code: 'ROLE_DDM_DIRECTOR_GENERAL',
  cooldown_minutes: 15,
};

export const AlertManagementPage = () => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const canManageRules = canManageAlertRules(user);
  const canAck = canAcknowledgeAlerts(user);
  const canTrigger = canTriggerAlerts(user);

  const [activeTab, setActiveTab] = useState('alerts');
  const [alertFilters, setAlertFilters] = useState({ status: '', severity: '', unacknowledged_only: false, page: 1 });
  const [editingRuleId, setEditingRuleId] = useState(null);
  const [deleteRuleTarget, setDeleteRuleTarget] = useState(null);
  const [actionModal, setActionModal] = useState(null);
  const [actionNotes, setActionNotes] = useState('');
  const [ruleForm, setRuleForm] = useState(emptyRuleForm);
  const [triggerForm, setTriggerForm] = useState({
    rule_code: '',
    category: 'EMERGENCY_ALERT',
    severity: 'HIGH',
    title_bn: '',
    title_en: '',
    message_bn: '',
    message_en: '',
    metric_value: '',
  });
  const [feedback, setFeedback] = useState(null);

  const { data: lookupRes } = useQuery({
    queryKey: ['notifications', 'lookups'],
    queryFn: () => notificationApi.getLookups(),
  });

  const { data: alertsRes, isLoading: alertsLoading, error: alertsError } = useQuery({
    queryKey: ['notifications', 'alerts', alertFilters],
    queryFn: () => notificationApi.getAlerts({
      status: alertFilters.status || undefined,
      severity: alertFilters.severity || undefined,
      unacknowledged_only: alertFilters.unacknowledged_only ? 'true' : undefined,
      page: alertFilters.page,
      per_page: 15,
    }),
    enabled: activeTab === 'alerts',
  });

  const { data: rulesRes, isLoading: rulesLoading, error: rulesError } = useQuery({
    queryKey: ['notifications', 'alert-rules'],
    queryFn: () => notificationApi.getAlertRules({ per_page: 50 }),
    enabled: activeTab === 'rules',
  });

  const ackMutation = useMutation({
    mutationFn: ({ id, notes }) => notificationApi.acknowledgeAlert(id, { notes }),
    onSuccess: () => {
      setFeedback({ variant: 'success', message: t('সতর্কতা গৃহীত হয়েছে।', 'Alert acknowledged.') });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const resolveMutation = useMutation({
    mutationFn: ({ id, notes }) => notificationApi.resolveAlert(id, { notes }),
    onSuccess: () => {
      setFeedback({ variant: 'success', message: t('সতর্কতা সমাধানকৃত হয়েছে।', 'Alert resolved.') });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const escalateMutation = useMutation({
    mutationFn: () => notificationApi.runEscalations(),
    onSuccess: (res) => {
      const escalated = res?.data?.escalated ?? 0;
      setFeedback({ variant: 'info', message: t(`এস্কেলেশন সম্পন্ন হয়েছে (${escalated} টি)।`, `Escalation sweep completed (${escalated} escalated).`) });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const saveRuleMutation = useMutation({
    mutationFn: (payload) =>
      editingRuleId
        ? notificationApi.updateAlertRule(editingRuleId, payload)
        : notificationApi.createAlertRule(payload),
    onSuccess: () => {
      setFeedback({
        variant: 'success',
        message: editingRuleId
          ? t('সতর্কতা নিয়ম হালনাগাদ হয়েছে।', 'Alert rule updated.')
          : t('সতর্কতা নিয়ম তৈরি হয়েছে।', 'Alert rule created.'),
      });
      setEditingRuleId(null);
      setRuleForm(emptyRuleForm);
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const deleteRuleMutation = useMutation({
    mutationFn: (id) => notificationApi.deleteAlertRule(id),
    onSuccess: () => {
      setFeedback({ variant: 'success', message: t('সতর্কতা নিয়ম আর্কাইভ হয়েছে।', 'Alert rule archived.') });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const triggerMutation = useMutation({
    mutationFn: (payload) => notificationApi.triggerAlert(payload),
    onSuccess: (res) => {
      const triggered = res?.data?.triggered;
      setFeedback({
        variant: triggered ? 'success' : 'warning',
        message: triggered
          ? t('সতর্কতা সফলভাবে ট্রিগার হয়েছে।', 'Alert triggered successfully.')
          : (res?.data?.reason || t('থ্রেশহোল্ড শর্ত পূর্ণ হয়নি।', 'Threshold condition not breached.')),
      });
      if (triggered) {
        setTriggerForm({
          rule_code: '',
          category: 'EMERGENCY_ALERT',
          severity: 'HIGH',
          title_bn: '',
          title_en: '',
          message_bn: '',
          message_en: '',
          metric_value: '',
        });
      }
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const lookups = lookupRes?.data || {};
  const alerts = alertsRes?.data || [];
  const rules = rulesRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-exclamation-triangle-fill text-warning me-2"></i>
            {t('সতর্কতা ও এসকেলেশন ব্যবস্থাপনা', 'Alert & Escalation Management')}
          </h4>
          <p className="text-muted small mb-0">
            {t('সতর্কতা নিয়ম, থ্রেশহোল্ড, গ্রহণ/সমাধান এবং স্বয়ংক্রিয় এসকেলেশন', 'Threshold rules, active operational alerts, acknowledgement tracking, and SLA escalation')}
          </p>
        </div>

        <div className="d-flex gap-2">
          {canManageRules && (
            <button
              type="button"
              className="btn btn-outline-danger btn-sm"
              disabled={escalateMutation.isPending}
              onClick={() => escalateMutation.mutate()}
            >
              <i className="bi bi-arrow-up-right-circle me-1"></i>
              {t('এসকেলেশন চালান', 'Run Escalations')}
            </button>
          )}
          <ul className="nav nav-pills">
            <li className="nav-item">
              <button
                type="button"
                className={`nav-link py-1 px-3 ${activeTab === 'alerts' ? 'active' : ''}`}
                onClick={() => setActiveTab('alerts')}
              >
                {t('সক্রিয় সতর্কতা', 'Alerts')}
              </button>
            </li>
            <li className="nav-item">
              <button
                type="button"
                className={`nav-link py-1 px-3 ${activeTab === 'rules' ? 'active' : ''}`}
                onClick={() => setActiveTab('rules')}
              >
                {t('সতর্কতা নিয়মাবলী', 'Alert Rules')}
              </button>
            </li>
          </ul>
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {activeTab === 'alerts' ? (
        <div className="row g-3">
          <div className={canTrigger ? 'col-lg-8' : 'col-12'}>
            <div className="card shadow-sm border-0 mb-3">
              <div className="card-body">
                <div className="row g-2 align-items-center">
                  <div className="col-md-4">
                    <select
                      className="form-select form-select-sm"
                      value={alertFilters.status}
                      onChange={(e) => setAlertFilters((p) => ({ ...p, status: e.target.value, page: 1 }))}
                    >
                      <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
                      <option value="ACTIVE">ACTIVE</option>
                      <option value="ACKNOWLEDGED">ACKNOWLEDGED</option>
                      <option value="ESCALATED">ESCALATED</option>
                      <option value="RESOLVED">RESOLVED</option>
                    </select>
                  </div>
                  <div className="col-md-4">
                    <select
                      className="form-select form-select-sm"
                      value={alertFilters.severity}
                      onChange={(e) => setAlertFilters((p) => ({ ...p, severity: e.target.value, page: 1 }))}
                    >
                      <option value="">{t('সকল তীব্রতা', 'All Severities')}</option>
                      <option value="CRITICAL">CRITICAL</option>
                      <option value="HIGH">HIGH</option>
                      <option value="WARNING">WARNING</option>
                      <option value="INFO">INFO</option>
                    </select>
                  </div>
                  <div className="col-md-4">
                    <div className="form-check">
                      <input
                        id="unackOnlyCheck"
                        type="checkbox"
                        className="form-check-input"
                        checked={alertFilters.unacknowledged_only}
                        onChange={(e) => setAlertFilters((p) => ({ ...p, unacknowledged_only: e.target.checked, page: 1 }))}
                      />
                      <label htmlFor="unackOnlyCheck" className="form-check-label small fw-semibold">
                        {t('শুধু অগৃহীত সতর্কতা', 'Unacknowledged Only')}
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {alertsLoading ? (
              <Loader message="সতর্কতা লোড হচ্ছে..." messageEn="Loading alerts..." />
            ) : alertsError ? (
              <AlertBanner variant="danger" message={formatNotificationApiError(alertsError)} />
            ) : alerts.length === 0 ? (
              <EmptyState title={t('কোনো সতর্কতা নেই', 'No alerts matching filter')} />
            ) : (
              <div className="card shadow-sm border-0">
                <div className="list-group list-group-flush">
                  {alerts.map((alert) => (
                    <div key={alert.id} className="list-group-item">
                      <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div className="flex-grow-1">
                          <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span className="fw-bold small text-muted">{alert.alert_no}</span>
                            <SeverityBadge severity={alert.severity} />
                            <AlertStatusBadge status={alert.status} />
                            <CategoryLabel category={alert.category} />
                          </div>
                          <h6 className="fw-bold mb-1">{lang === 'en' ? alert.title_en : alert.title_bn}</h6>
                          <p className="small mb-1">{lang === 'en' ? alert.message_en : alert.message_bn}</p>
                          <div className="small text-muted">
                            {alert.administrative_unit && (
                              <span className="me-3">
                                <i className="bi bi-geo-alt me-1"></i>
                                {lang === 'en' ? alert.administrative_unit.name_en : alert.administrative_unit.name_bn}
                              </span>
                            )}
                            {alert.acknowledged_by_user && (
                              <span className="text-info me-3">
                                {t('গৃহীত:', 'Ack by:')} {alert.acknowledged_by_user.full_name_en}
                              </span>
                            )}
                          </div>
                        </div>

                        {canAck && alert.status !== 'RESOLVED' && (
                          <div className="d-flex gap-1">
                            {!alert.is_acknowledged && (
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-primary"
                                disabled={ackMutation.isPending}
                                onClick={() => {
                                  setActionNotes('');
                                  setActionModal({ type: 'acknowledge', alert });
                                }}
                              >
                                <i className="bi bi-check-circle me-1"></i>
                                {t('গ্রহণ করুন', 'Acknowledge')}
                              </button>
                            )}
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-success"
                              disabled={resolveMutation.isPending}
                              onClick={() => {
                                setActionNotes('');
                                setActionModal({ type: 'resolve', alert });
                              }}
                            >
                              <i className="bi bi-check2-square me-1"></i>
                              {t('সমাধান', 'Resolve')}
                            </button>
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>

          {canTrigger && (
            <div className="col-lg-4">
              <div className="card shadow-sm border-0">
                <div className="card-header bg-white fw-semibold">
                  {t('ম্যানুয়াল / ইভেন্ট সতর্কতা ট্রিগার', 'Trigger Event / Threshold Alert')}
                </div>
                <div className="card-body">
                  <form
                    className="row g-2"
                    onSubmit={(e) => {
                      e.preventDefault();
                      triggerMutation.mutate({
                        ...triggerForm,
                        metric_value: triggerForm.metric_value !== '' ? Number(triggerForm.metric_value) : undefined,
                      });
                    }}
                  >
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('নিয়ম কোড (ঐচ্ছিক)', 'Alert Rule (Optional)')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={triggerForm.rule_code}
                        onChange={(e) => setTriggerForm((p) => ({ ...p, rule_code: e.target.value }))}
                      >
                        <option value="">{t('-- সরাসরি সতর্কতা --', '-- Direct Alert --')}</option>
                        {(lookups.alert_rules || []).map((r) => (
                          <option key={r.id} value={r.rule_code}>
                            [{r.rule_code}] {lang === 'en' ? r.name_en : r.name_bn}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('ক্যাটাগরি', 'Category')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={triggerForm.category}
                        onChange={(e) => setTriggerForm((p) => ({ ...p, category: e.target.value }))}
                      >
                        {Object.entries(CATEGORY_META).map(([k, m]) => (
                          <option key={k} value={k}>{lang === 'en' ? m.en : m.bn}</option>
                        ))}
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('তীব্রতা', 'Severity')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={triggerForm.severity}
                        onChange={(e) => setTriggerForm((p) => ({ ...p, severity: e.target.value }))}
                      >
                        <option value="INFO">INFO</option>
                        <option value="WARNING">WARNING</option>
                        <option value="HIGH">HIGH</option>
                        <option value="CRITICAL">CRITICAL</option>
                      </select>
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('শিরোনাম (বাংলা)', 'Title (Bangla)')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={triggerForm.title_bn}
                        onChange={(e) => setTriggerForm((p) => ({ ...p, title_bn: e.target.value }))}
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('শিরোনাম (ইংরেজি)', 'Title (English)')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={triggerForm.title_en}
                        onChange={(e) => setTriggerForm((p) => ({ ...p, title_en: e.target.value }))}
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('বার্তা (বাংলা) *', 'Message (Bangla) *')}</label>
                      <textarea
                        rows={2}
                        className="form-control form-control-sm"
                        value={triggerForm.message_bn}
                        onChange={(e) => setTriggerForm((p) => ({ ...p, message_bn: e.target.value }))}
                        required
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('বার্তা (ইংরেজি) *', 'Message (English) *')}</label>
                      <textarea
                        rows={2}
                        className="form-control form-control-sm"
                        value={triggerForm.message_en}
                        onChange={(e) => setTriggerForm((p) => ({ ...p, message_en: e.target.value }))}
                        required
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('মেট্রিক মান (থ্রেশহোল্ড যাচাইয়ের জন্য)', 'Metric Value (Optional)')}</label>
                      <input
                        type="number"
                        step="any"
                        className="form-control form-control-sm"
                        value={triggerForm.metric_value}
                        onChange={(e) => setTriggerForm((p) => ({ ...p, metric_value: e.target.value }))}
                      />
                    </div>
                    <div className="col-12">
                      <button type="submit" className="btn btn-warning btn-sm w-100 fw-semibold" disabled={triggerMutation.isPending}>
                        <i className="bi bi-lightning-fill me-1"></i>
                        {t('সতর্কতা ট্রিগার করুন', 'Trigger Alert')}
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          )}
        </div>
      ) : (
        <div className="row g-3">
          {canManageRules && (
            <div className="col-lg-5">
              <div className="card shadow-sm border-0">
                <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                  <span>{editingRuleId ? t('সতর্কতা নিয়ম সম্পাদনা', 'Edit Alert Rule') : t('নতুন সতর্কতা নিয়ম কনফিগার করুন', 'Configure New Alert Rule')}</span>
                  {editingRuleId && (
                    <button
                      type="button"
                      className="btn btn-link btn-sm p-0"
                      onClick={() => {
                        setEditingRuleId(null);
                        setRuleForm(emptyRuleForm);
                      }}
                    >
                      {t('বাতিল', 'Cancel')}
                    </button>
                  )}
                </div>
                <div className="card-body">
                  <form
                    className="row g-2"
                    onSubmit={(e) => {
                      e.preventDefault();
                      saveRuleMutation.mutate({
                        rule_code: ruleForm.rule_code,
                        name_bn: ruleForm.name_bn,
                        name_en: ruleForm.name_en,
                        category: ruleForm.category,
                        event_type: ruleForm.event_type,
                        severity: ruleForm.severity,
                        condition_config: {
                          operator: ruleForm.operator,
                          threshold: Number(ruleForm.threshold),
                        },
                        channels: ['IN_APP', 'EMAIL', 'SMS'],
                        target_roles: ['ROLE_NEOC_DIRECTOR', 'ROLE_DRRO', 'ROLE_UNO'],
                        escalation_delay_minutes: Number(ruleForm.escalation_delay_minutes),
                        escalation_role_code: ruleForm.escalation_role_code,
                        cooldown_minutes: Number(ruleForm.cooldown_minutes),
                      });
                    }}
                  >
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('নিয়ম কোড *', 'Rule Code *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        placeholder="RULE-FLOOD-LEVEL"
                        value={ruleForm.rule_code}
                        disabled={Boolean(editingRuleId)}
                        onChange={(e) => setRuleForm((p) => ({ ...p, rule_code: e.target.value.toUpperCase() }))}
                        required
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('তীব্রতা', 'Severity')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={ruleForm.severity}
                        onChange={(e) => setRuleForm((p) => ({ ...p, severity: e.target.value }))}
                      >
                        <option value="INFO">INFO</option>
                        <option value="WARNING">WARNING</option>
                        <option value="HIGH">HIGH</option>
                        <option value="CRITICAL">CRITICAL</option>
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('ক্যাটাগরি', 'Category')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={ruleForm.category}
                        onChange={(e) => setRuleForm((p) => ({ ...p, category: e.target.value }))}
                      >
                        {Object.entries(CATEGORY_META).map(([k, m]) => (
                          <option key={k} value={k}>{lang === 'en' ? m.en : m.bn}</option>
                        ))}
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('ইভেন্ট ধরন', 'Event Type')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={ruleForm.event_type}
                        onChange={(e) => setRuleForm((p) => ({ ...p, event_type: e.target.value }))}
                      >
                        {(lookups.event_types || ['LOW_STOCK_THRESHOLD', 'CYCLONE_SIGNAL_THRESHOLD', 'WORKFLOW_SLA_OVERDUE']).map((et) => (
                          <option key={et} value={et}>{et}</option>
                        ))}
                      </select>
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('নাম (বাংলা) *', 'Name (Bangla) *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={ruleForm.name_bn}
                        onChange={(e) => setRuleForm((p) => ({ ...p, name_bn: e.target.value }))}
                        required
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('নাম (ইংরেজি) *', 'Name (English) *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={ruleForm.name_en}
                        onChange={(e) => setRuleForm((p) => ({ ...p, name_en: e.target.value }))}
                        required
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('অপারেটর', 'Operator')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={ruleForm.operator}
                        onChange={(e) => setRuleForm((p) => ({ ...p, operator: e.target.value }))}
                      >
                        <option value=">=">&gt;=</option>
                        <option value="<=">&lt;=</option>
                        <option value=">">&gt;</option>
                        <option value="<">&lt;</option>
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('থ্রেশহোল্ড', 'Threshold')}</label>
                      <input
                        type="number"
                        className="form-control form-control-sm"
                        value={ruleForm.threshold}
                        onChange={(e) => setRuleForm((p) => ({ ...p, threshold: e.target.value }))}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('এস্কেলেশন (মি.)', 'Escalate (min)')}</label>
                      <input
                        type="number"
                        className="form-control form-control-sm"
                        value={ruleForm.escalation_delay_minutes}
                        onChange={(e) => setRuleForm((p) => ({ ...p, escalation_delay_minutes: e.target.value }))}
                      />
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('এস্কেলেশন ভূমিকা', 'Escalation Role')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={ruleForm.escalation_role_code}
                        onChange={(e) => setRuleForm((p) => ({ ...p, escalation_role_code: e.target.value }))}
                      >
                        {(lookups.roles || []).map((r) => (
                          <option key={r.code} value={r.code}>{r.code}</option>
                        ))}
                      </select>
                    </div>
                    <div className="col-12">
                      <button type="submit" className="btn btn-primary btn-sm w-100" disabled={saveRuleMutation.isPending}>
                        {t('নিয়ম সংরক্ষণ করুন', 'Save Alert Rule')}
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          )}

          <div className={canManageRules ? 'col-lg-7' : 'col-12'}>
            {rulesLoading ? (
              <Loader message="নিয়ম লোড হচ্ছে..." messageEn="Loading alert rules..." />
            ) : rulesError ? (
              <AlertBanner variant="danger" message={formatNotificationApiError(rulesError)} />
            ) : rules.length === 0 ? (
              <EmptyState title={t('কোনো সতর্কতা নিয়ম নেই', 'No alert rules configured')} />
            ) : (
              <div className="card shadow-sm border-0">
                <div className="list-group list-group-flush">
                  {rules.map((r) => (
                    <div key={r.id} className="list-group-item">
                      <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                          <div className="d-flex align-items-center gap-2 mb-1">
                            <code className="fw-bold">{r.rule_code}</code>
                            <SeverityBadge severity={r.severity} />
                            <CategoryLabel category={r.category} />
                            <span className="badge bg-light text-dark border">{r.event_type}</span>
                          </div>
                          <div className="fw-semibold">{lang === 'en' ? r.name_en : r.name_bn}</div>
                          <div className="small text-muted mt-1">
                            {t('এস্কেলেশন:', 'Escalation:')} {r.escalation_delay_minutes}m &rarr; {r.escalation_role_code || '-'}
                          </div>
                        </div>
                        <div className="d-flex align-items-center gap-1">
                          <span className={`badge bg-${r.is_active ? 'success' : 'secondary'}`}>
                            {r.is_active ? t('সক্রিয়', 'Active') : t('নিষ্ক্রিয়', 'Inactive')}
                          </span>
                          {canManageRules && (
                            <>
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-primary"
                                onClick={() => {
                                  setEditingRuleId(r.id);
                                  setRuleForm({
                                    rule_code: r.rule_code,
                                    name_bn: r.name_bn,
                                    name_en: r.name_en,
                                    category: r.category,
                                    event_type: r.event_type,
                                    severity: r.severity,
                                    threshold: String(r.condition_config?.threshold ?? '20'),
                                    operator: r.condition_config?.operator ?? '>=',
                                    escalation_delay_minutes: r.escalation_delay_minutes ?? 30,
                                    escalation_role_code: r.escalation_role_code || 'ROLE_DDM_DIRECTOR_GENERAL',
                                    cooldown_minutes: r.cooldown_minutes ?? 15,
                                  });
                                }}
                              >
                                <i className="bi bi-pencil"></i>
                              </button>
                              <button
                                type="button"
                                className="btn btn-sm btn-outline-danger"
                                onClick={() => setDeleteRuleTarget(r)}
                              >
                                <i className="bi bi-trash"></i>
                              </button>
                            </>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {actionModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow border-0">
              <div className="modal-header">
                <h5 className="modal-title fw-bold">
                  {actionModal.type === 'acknowledge'
                    ? t('সতর্কতা গ্রহণ নোট', 'Acknowledge Alert')
                    : t('সতর্কতা সমাধান নোট', 'Resolve Alert')}
                </h5>
                <button type="button" className="btn-close" onClick={() => setActionModal(null)}></button>
              </div>
              <div className="modal-body">
                <p className="small text-muted mb-2">
                  <strong>{actionModal.alert.alert_no}</strong> —{' '}
                  {lang === 'en' ? actionModal.alert.title_en : actionModal.alert.title_bn}
                </p>
                <label className="form-label small fw-semibold">{t('মন্তব্য / পদক্ষেপ (ঐচ্ছিক)', 'Remarks / Action Taken (Optional)')}</label>
                <textarea
                  rows={3}
                  className="form-control"
                  value={actionNotes}
                  onChange={(e) => setActionNotes(e.target.value)}
                  placeholder={t('গৃহীত পদক্ষেপের বিবরণ লিখুন...', 'Enter remarks or action taken...')}
                />
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => setActionModal(null)}>
                  {t('বাতিল', 'Cancel')}
                </button>
                <button
                  type="button"
                  className={`btn btn-${actionModal.type === 'acknowledge' ? 'primary' : 'success'} btn-sm`}
                  disabled={ackMutation.isPending || resolveMutation.isPending}
                  onClick={() => {
                    const mut = actionModal.type === 'acknowledge' ? ackMutation : resolveMutation;
                    mut.mutate(
                      { id: actionModal.alert.id, notes: actionNotes || null },
                      { onSettled: () => setActionModal(null) }
                    );
                  }}
                >
                  {actionModal.type === 'acknowledge' ? t('গ্রহণ নিশ্চিত করুন', 'Confirm Acknowledge') : t('সমাধান নিশ্চিত করুন', 'Confirm Resolve')}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      <ConfirmDialog
        isOpen={Boolean(deleteRuleTarget)}
        title={t('সতর্কতা নিয়ম আর্কাইভ', 'Archive Alert Rule')}
        titleEn="Confirm Alert Rule Archival"
        message={t(
          `আপনি কি "${deleteRuleTarget?.rule_code}" নিয়মটি আর্কাইভ করতে চান?`,
          `Are you sure you want to archive alert rule "${deleteRuleTarget?.rule_code}"?`
        )}
        confirmText={t('হ্যাঁ, আর্কাইভ করুন', 'Yes, Archive')}
        cancelText={t('বাতিল', 'Cancel')}
        isLoading={deleteRuleMutation.isPending}
        onCancel={() => setDeleteRuleTarget(null)}
        onConfirm={() => {
          if (deleteRuleTarget) {
            deleteRuleMutation.mutate(deleteRuleTarget.id, {
              onSettled: () => setDeleteRuleTarget(null),
            });
          }
        }}
      />
    </div>
  );
};

export default AlertManagementPage;
