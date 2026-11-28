import React, { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationApi } from '../../../api/notificationApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  CATEGORY_META,
  CHANNEL_META,
  formatNotificationApiError,
} from '../utils/notificationPermissions';

export const NotificationSendPage = () => {
  const { t, lang } = useLanguage();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data: lookupRes, isLoading } = useQuery({
    queryKey: ['notifications', 'lookups'],
    queryFn: () => notificationApi.getLookups(),
  });

  const lookups = lookupRes?.data || {};
  const templates = lookups.templates || [];
  const roles = lookups.roles || [];
  const organizations = lookups.organizations || [];
  const adminUnits = lookups.administrative_units || [];
  const users = lookups.users || [];

  const [form, setForm] = useState({
    template_id: '',
    category: 'DISASTER_WARNING',
    severity: 'HIGH',
    priority: 'HIGH',
    channels: ['IN_APP', 'SMS'],
    title_bn: '',
    title_en: '',
    message_bn: '',
    message_en: '',
    target_type: 'GEOGRAPHY',
    user_ids: [],
    role_code: '',
    organization_id: '',
    administrative_unit_id: '',
    scheduled_at: '',
    template_variables: {},
  });

  const [feedback, setFeedback] = useState(null);

  const selectedTemplate = useMemo(
    () => templates.find((tpl) => tpl.id === form.template_id) || null,
    [templates, form.template_id]
  );

  const handleTemplateSelect = (templateId) => {
    const tpl = templates.find((item) => item.id === templateId);
    if (!tpl) {
      setForm((p) => ({ ...p, template_id: '', template_variables: {} }));
      return;
    }

    const nextVars = {};
    (tpl.variables || []).forEach((v) => {
      nextVars[v] = '';
    });

    setForm((p) => ({
      ...p,
      template_id: tpl.id,
      category: tpl.category,
      severity: tpl.default_severity || p.severity,
      channels: tpl.channel === 'MULTI' ? ['IN_APP', 'EMAIL', 'SMS'] : [tpl.channel],
      title_bn: tpl.title_bn,
      title_en: tpl.title_en,
      message_bn: tpl.body_bn,
      message_en: tpl.body_en,
      template_variables: nextVars,
    }));
  };

  const toggleChannel = (ch) => {
    setForm((p) => {
      const exists = p.channels.includes(ch);
      const next = exists ? p.channels.filter((c) => c !== ch) : [...p.channels, ch];
      return { ...p, channels: next };
    });
  };

  const sendMutation = useMutation({
    mutationFn: (payload) => notificationApi.sendNotification(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      setFeedback({
        variant: 'success',
        message: res?.message || t('বিজ্ঞপ্তি সফলভাবে প্রেরণ/তালিকাভুক্ত হয়েছে।', 'Notification queued successfully.'),
      });
      setTimeout(() => navigate('/notifications/logs'), 900);
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatNotificationApiError(err) });
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    if (form.channels.length === 0) {
      setFeedback({ variant: 'warning', message: t('অন্তত একটি চ্যানেল নির্বাচন করুন।', 'Select at least one delivery channel.') });
      return;
    }

    const targetCriteria = {};
    if (form.target_type === 'USER') {
      targetCriteria.user_ids = form.user_ids;
    } else if (form.target_type === 'ROLE') {
      targetCriteria.roles = form.role_code ? [form.role_code] : [];
    } else if (form.target_type === 'ORGANIZATION') {
      targetCriteria.organization_id = form.organization_id || null;
    } else if (form.target_type === 'GEOGRAPHY') {
      targetCriteria.administrative_unit_id = form.administrative_unit_id || null;
    }

    const interpolate = (text) => {
      let out = text || '';
      Object.entries(form.template_variables || {}).forEach(([k, v]) => {
        if (v !== undefined && v !== null && String(v).trim() !== '') {
          out = out.replaceAll(`{{${k}}}`, String(v));
        }
      });
      return out;
    };

    sendMutation.mutate({
      template_id: form.template_id || null,
      template_variables: form.template_variables,
      category: form.category,
      severity: form.severity,
      priority: form.priority,
      channels: form.channels,
      title_bn: interpolate(form.title_bn),
      title_en: interpolate(form.title_en),
      message_bn: interpolate(form.message_bn),
      message_en: interpolate(form.message_en),
      target_type: form.target_type,
      target_criteria: targetCriteria,
      administrative_unit_id: form.administrative_unit_id || null,
      scheduled_at: form.scheduled_at || null,
      idempotency_key: `ui-${Date.now()}`,
    });
  };

  if (isLoading) {
    return <Loader message="বিজ্ঞপ্তি ফর্ম লোড হচ্ছে..." messageEn="Loading notification composer..." />;
  }

  return (
    <div className="container-fluid py-3">
      <div className="mb-3">
        <h4 className="fw-bold mb-1">
          <i className="bi bi-megaphone-fill text-danger me-2"></i>
          {t('বিজ্ঞপ্তি ও জরুরি সতর্কবার্তা প্রেরণ', 'Compose & Schedule Notification')}
        </h4>
        <p className="text-muted small mb-0">
          {t(
            'বাংলা ও ইংরেজি দ্বিভাষিক বার্তা ব্যবহারকারী, ভূমিকা, সংগঠন বা ভৌগোলিক অধিক্ষেত্রে প্রেরণ করুন',
            'Dispatch bilingual notifications across users, roles, organizations, or scoped administrative units'
          )}
        </p>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      <form onSubmit={handleSubmit}>
        <div className="row g-3">
          <div className="col-lg-8">
            <div className="card shadow-sm border-0">
              <div className="card-body">
                <div className="row g-3">
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('টেমপ্লেট (ঐচ্ছিক)', 'Template (Optional)')}</label>
                    <select
                      className="form-select"
                      value={form.template_id}
                      onChange={(e) => handleTemplateSelect(e.target.value)}
                    >
                      <option value="">{t('কাস্টম বার্তা লিখুন', '-- Custom Message --')}</option>
                      {templates.map((tpl) => (
                        <option key={tpl.id} value={tpl.id}>
                          [{tpl.template_code}] {lang === 'en' ? tpl.title_en : tpl.title_bn}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('ক্যাটাগরি', 'Category')}</label>
                    <select
                      className="form-select"
                      value={form.category}
                      onChange={(e) => setForm((p) => ({ ...p, category: e.target.value }))}
                      required
                    >
                      {Object.entries(CATEGORY_META).map(([key, metaItem]) => (
                        <option key={key} value={key}>{lang === 'en' ? metaItem.en : metaItem.bn}</option>
                      ))}
                    </select>
                  </div>

                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('তীব্রতা', 'Severity')}</label>
                    <select
                      className="form-select"
                      value={form.severity}
                      onChange={(e) => setForm((p) => ({ ...p, severity: e.target.value }))}
                    >
                      <option value="INFO">INFO</option>
                      <option value="WARNING">WARNING</option>
                      <option value="HIGH">HIGH</option>
                      <option value="CRITICAL">CRITICAL</option>
                    </select>
                  </div>

                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('অগ্রাধিকার', 'Priority')}</label>
                    <select
                      className="form-select"
                      value={form.priority}
                      onChange={(e) => setForm((p) => ({ ...p, priority: e.target.value }))}
                    >
                      <option value="LOW">LOW</option>
                      <option value="NORMAL">NORMAL</option>
                      <option value="HIGH">HIGH</option>
                      <option value="URGENT">URGENT</option>
                    </select>
                  </div>

                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('সময়সূচী (ঐচ্ছিক)', 'Schedule At (Optional)')}</label>
                    <input
                      type="datetime-local"
                      className="form-control"
                      value={form.scheduled_at}
                      onChange={(e) => setForm((p) => ({ ...p, scheduled_at: e.target.value }))}
                    />
                  </div>

                  <div className="col-12">
                    <label className="form-label small fw-semibold">{t('বিতরণ চ্যানেল', 'Delivery Channels')}</label>
                    <div className="d-flex flex-wrap gap-3">
                      {Object.entries(CHANNEL_META).map(([chKey, chMeta]) => (
                        <div className="form-check" key={chKey}>
                          <input
                            id={`ch-${chKey}`}
                            type="checkbox"
                            className="form-check-input"
                            checked={form.channels.includes(chKey)}
                            onChange={() => toggleChannel(chKey)}
                          />
                          <label className="form-check-label" htmlFor={`ch-${chKey}`}>
                            <i className={`bi ${chMeta.icon} me-1`}></i>
                            {lang === 'en' ? chMeta.en : chMeta.bn}
                          </label>
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('শিরোনাম (বাংলা) *', 'Title (Bangla) *')}</label>
                    <input
                      type="text"
                      className="form-control"
                      value={form.title_bn}
                      onChange={(e) => setForm((p) => ({ ...p, title_bn: e.target.value }))}
                      required
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('শিরোনাম (ইংরেজি) *', 'Title (English) *')}</label>
                    <input
                      type="text"
                      className="form-control"
                      value={form.title_en}
                      onChange={(e) => setForm((p) => ({ ...p, title_en: e.target.value }))}
                      required
                    />
                  </div>

                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('বার্তা (বাংলা) *', 'Message Body (Bangla) *')}</label>
                    <textarea
                      rows={4}
                      className="form-control"
                      value={form.message_bn}
                      onChange={(e) => setForm((p) => ({ ...p, message_bn: e.target.value }))}
                      required
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('বার্তা (ইংরেজি) *', 'Message Body (English) *')}</label>
                    <textarea
                      rows={4}
                      className="form-control"
                      value={form.message_en}
                      onChange={(e) => setForm((p) => ({ ...p, message_en: e.target.value }))}
                      required
                    />
                  </div>

                  {selectedTemplate?.variables?.length > 0 && (
                    <div className="col-12">
                      <div className="border rounded p-3 bg-light">
                        <div className="small fw-semibold mb-2">
                          <i className="bi bi-braces me-1 text-primary"></i>
                          {t('টেমপ্লেট চলকের মান (Variables)', 'Template Variable Values')}
                        </div>
                        <div className="row g-2">
                          {selectedTemplate.variables.map((v) => (
                            <div className="col-md-6" key={v}>
                              <label className="form-label small mb-1">{`{{${v}}}`}</label>
                              <input
                                type="text"
                                className="form-control form-control-sm"
                                value={form.template_variables?.[v] || ''}
                                placeholder={v}
                                onChange={(e) =>
                                  setForm((p) => ({
                                    ...p,
                                    template_variables: {
                                      ...(p.template_variables || {}),
                                      [v]: e.target.value,
                                    },
                                  }))
                                }
                              />
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </div>

          <div className="col-lg-4">
            <div className="card shadow-sm border-0 mb-3">
              <div className="card-header bg-white fw-semibold">
                {t('প্রাপক নির্বাচন (অধিক্ষেত্র নিয়ন্ত্রিত)', 'Recipient Targeting (Scoped)')}
              </div>
              <div className="card-body">
                <div className="mb-3">
                  <label className="form-label small fw-semibold">{t('লক্ষ্যবস্তুর ধরন', 'Target Type')}</label>
                  <select
                    className="form-select"
                    value={form.target_type}
                    onChange={(e) => setForm((p) => ({ ...p, target_type: e.target.value }))}
                  >
                    <option value="GEOGRAPHY">{t('ভৌগোলিক অধিক্ষেত্র (Geography)', 'Geography (Admin Unit)')}</option>
                    <option value="ROLE">{t('ভূমিকা (Role)', 'Role')}</option>
                    <option value="ORGANIZATION">{t('সংগঠন (Organization)', 'Organization')}</option>
                    <option value="USER">{t('নির্দিষ্ট ব্যবহারকারী (User)', 'Specific Users')}</option>
                    <option value="ALL">{t('সকল অনুমোদিত ব্যবহারকারী (Broadcast)', 'All Scoped Users')}</option>
                  </select>
                </div>

                {form.target_type === 'USER' && (
                  <div className="mb-3">
                    <label className="form-label small fw-semibold">{t('ব্যবহারকারী নির্বাচন করুন', 'Select Users')}</label>
                    <select
                      multiple
                      className="form-select"
                      size={5}
                      value={form.user_ids}
                      onChange={(e) => {
                        const selected = Array.from(e.target.selectedOptions, (opt) => opt.value);
                        setForm((p) => ({ ...p, user_ids: selected }));
                      }}
                    >
                      {users.map((u) => (
                        <option key={u.id} value={u.id}>
                          {lang === 'en' ? u.full_name_en : (u.full_name_bn || u.full_name_en)} ({u.role_code})
                        </option>
                      ))}
                    </select>
                  </div>
                )}

                {form.target_type === 'ROLE' && (
                  <div className="mb-3">
                    <label className="form-label small fw-semibold">{t('ভূমিকা নির্বাচন করুন', 'Select Role')}</label>
                    <select
                      className="form-select"
                      value={form.role_code}
                      onChange={(e) => setForm((p) => ({ ...p, role_code: e.target.value }))}
                      required
                    >
                      <option value="">{t('-- ভূমিকা নির্বাচন --', '-- Select Role --')}</option>
                      {roles.map((r) => (
                        <option key={r.code} value={r.code}>
                          {lang === 'en' ? r.name_en : r.name_bn} ({r.code})
                        </option>
                      ))}
                    </select>
                  </div>
                )}

                {form.target_type === 'ORGANIZATION' && (
                  <div className="mb-3">
                    <label className="form-label small fw-semibold">{t('সংগঠন নির্বাচন করুন', 'Select Organization')}</label>
                    <select
                      className="form-select"
                      value={form.organization_id}
                      onChange={(e) => setForm((p) => ({ ...p, organization_id: e.target.value }))}
                      required
                    >
                      <option value="">{t('-- সংগঠন নির্বাচন --', '-- Select Organization --')}</option>
                      {organizations.map((org) => (
                        <option key={org.id} value={org.id}>
                          {lang === 'en' ? org.name_en : (org.name_bn || org.name_en)}
                        </option>
                      ))}
                    </select>
                  </div>
                )}

                <div className="mb-3">
                  <label className="form-label small fw-semibold">{t('প্রশাসনিক ইউনিট (অধিক্ষেত্র)', 'Administrative Unit Scope')}</label>
                  <select
                    className="form-select"
                    value={form.administrative_unit_id}
                    onChange={(e) => setForm((p) => ({ ...p, administrative_unit_id: e.target.value }))}
                  >
                    <option value="">{t('ব্যবহারকারীর নিজস্ব অধিক্ষেত্র', '-- Default Actor Scope --')}</option>
                    {adminUnits.map((unit) => (
                      <option key={unit.id} value={unit.id}>
                        [{unit.tier_level}] {lang === 'en' ? unit.name_en : unit.name_bn}
                      </option>
                    ))}
                  </select>
                </div>

                <button
                  type="submit"
                  className="btn btn-danger w-100"
                  disabled={sendMutation.isPending}
                >
                  <i className="bi bi-send-fill me-2"></i>
                  {sendMutation.isPending
                    ? t('প্রেরণ করা হচ্ছে...', 'Dispatching...')
                    : form.scheduled_at
                      ? t('বিজ্ঞপ্তি সময়সূচী করুন', 'Schedule Notification')
                      : t('বিজ্ঞপ্তি প্রেরণ করুন', 'Send Notification Now')}
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  );
};

export default NotificationSendPage;
