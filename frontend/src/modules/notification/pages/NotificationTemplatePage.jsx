import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationApi } from '../../../api/notificationApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import { CategoryLabel, SeverityBadge } from '../components/NotificationBadges';
import {
  canManageNotificationTemplates,
  CATEGORY_META,
  formatNotificationApiError,
} from '../utils/notificationPermissions';

const emptyForm = {
  template_code: '',
  category: 'DISASTER_WARNING',
  channel: 'MULTI',
  default_severity: 'HIGH',
  title_bn: '',
  title_en: '',
  subject_bn: '',
  subject_en: '',
  body_bn: '',
  body_en: '',
  variables_csv: '',
  is_active: true,
};

export const NotificationTemplatePage = () => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const canManage = canManageNotificationTemplates(user);
  const [editingId, setEditingId] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [feedback, setFeedback] = useState(null);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['notifications', 'templates'],
    queryFn: () => notificationApi.getTemplates({ per_page: 50 }),
  });

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      editingId ? notificationApi.updateTemplate(editingId, payload) : notificationApi.createTemplate(payload),
    onSuccess: () => {
      setFeedback({ variant: 'success', message: t('টেমপ্লেট সংরক্ষিত হয়েছে।', 'Template saved successfully.') });
      setEditingId(null);
      setForm(emptyForm);
      queryClient.invalidateQueries({ queryKey: ['notifications', 'templates'] });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'lookups'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => notificationApi.deleteTemplate(id),
    onSuccess: () => {
      setFeedback({ variant: 'success', message: t('টেমপ্লেট আর্কাইভ হয়েছে।', 'Template archived.') });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'templates'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const handleEdit = (tpl) => {
    setEditingId(tpl.id);
    setForm({
      template_code: tpl.template_code,
      category: tpl.category,
      channel: tpl.channel,
      default_severity: tpl.default_severity,
      title_bn: tpl.title_bn,
      title_en: tpl.title_en,
      subject_bn: tpl.subject_bn || '',
      subject_en: tpl.subject_en || '',
      body_bn: tpl.body_bn,
      body_en: tpl.body_en,
      variables_csv: (tpl.variables || []).join(', '),
      is_active: tpl.is_active,
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const variables = form.variables_csv
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);

    saveMutation.mutate({
      template_code: form.template_code,
      category: form.category,
      channel: form.channel,
      default_severity: form.default_severity,
      title_bn: form.title_bn,
      title_en: form.title_en,
      subject_bn: form.subject_bn || form.title_bn,
      subject_en: form.subject_en || form.title_en,
      body_bn: form.body_bn,
      body_en: form.body_en,
      variables,
      is_active: form.is_active,
    });
  };

  const templates = res?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="mb-3">
        <h4 className="fw-bold mb-1">
          <i className="bi bi-file-earmark-richtext text-primary me-2"></i>
          {t('বিজ্ঞপ্তি টেমপ্লেট ব্যবস্থাপনা', 'Bilingual Notification Templates')}
        </h4>
        <p className="text-muted small mb-0">
          {t('এসএমএস, ইমেইল, পুশ ও ইন-অ্যাপ সতর্কতার জন্য বাংলা ও ইংরেজি টেমপ্লেট পরিচালনা করুন', 'Configure reusable Bangla and English templates for SMS, Email, Push, and In-App dispatches')}
        </p>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      <div className="row g-3">
        {canManage && (
          <div className="col-lg-5">
            <div className="card shadow-sm border-0">
              <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>{editingId ? t('টেমপ্লেট সম্পাদনা', 'Edit Template') : t('নতুন টেমপ্লেট তৈরি', 'Create New Template')}</span>
                {editingId && (
                  <button
                    type="button"
                    className="btn btn-link btn-sm p-0"
                    onClick={() => {
                      setEditingId(null);
                      setForm(emptyForm);
                    }}
                  >
                    {t('বাতিল', 'Cancel')}
                  </button>
                )}
              </div>
              <div className="card-body">
                <form onSubmit={handleSubmit} className="row g-2">
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('কোড *', 'Template Code *')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder="CUSTOM_ALERT_CODE"
                      value={form.template_code}
                      disabled={Boolean(editingId)}
                      onChange={(e) => setForm((p) => ({ ...p, template_code: e.target.value.toUpperCase() }))}
                      required
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('চ্যানেল *', 'Channel *')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={form.channel}
                      onChange={(e) => setForm((p) => ({ ...p, channel: e.target.value }))}
                    >
                      <option value="MULTI">MULTI</option>
                      <option value="IN_APP">IN_APP</option>
                      <option value="EMAIL">EMAIL</option>
                      <option value="SMS">SMS</option>
                      <option value="PUSH">PUSH</option>
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('ক্যাটাগরি', 'Category')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={form.category}
                      onChange={(e) => setForm((p) => ({ ...p, category: e.target.value }))}
                    >
                      {Object.entries(CATEGORY_META).map(([key, item]) => (
                        <option key={key} value={key}>{lang === 'en' ? item.en : item.bn}</option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('তীব্রতা', 'Default Severity')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={form.default_severity}
                      onChange={(e) => setForm((p) => ({ ...p, default_severity: e.target.value }))}
                    >
                      <option value="INFO">INFO</option>
                      <option value="WARNING">WARNING</option>
                      <option value="HIGH">HIGH</option>
                      <option value="CRITICAL">CRITICAL</option>
                    </select>
                  </div>
                  <div className="col-12">
                    <label className="form-label small fw-semibold">{t('শিরোনাম (বাংলা) *', 'Title (Bangla) *')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={form.title_bn}
                      onChange={(e) => setForm((p) => ({ ...p, title_bn: e.target.value }))}
                      required
                    />
                  </div>
                  <div className="col-12">
                    <label className="form-label small fw-semibold">{t('শিরোনাম (ইংরেজি) *', 'Title (English) *')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={form.title_en}
                      onChange={(e) => setForm((p) => ({ ...p, title_en: e.target.value }))}
                      required
                    />
                  </div>
                  <div className="col-12">
                    <label className="form-label small fw-semibold">{t('মূল বার্তা (বাংলা) *', 'Body (Bangla) *')}</label>
                    <textarea
                      rows={3}
                      className="form-control form-control-sm"
                      value={form.body_bn}
                      onChange={(e) => setForm((p) => ({ ...p, body_bn: e.target.value }))}
                      required
                    />
                  </div>
                  <div className="col-12">
                    <label className="form-label small fw-semibold">{t('মূল বার্তা (ইংরেজি) *', 'Body (English) *')}</label>
                    <textarea
                      rows={3}
                      className="form-control form-control-sm"
                      value={form.body_en}
                      onChange={(e) => setForm((p) => ({ ...p, body_en: e.target.value }))}
                      required
                    />
                  </div>
                  <div className="col-12">
                    <label className="form-label small fw-semibold">{t('চলক (কমা দ্বারা পৃথক)', 'Variables (comma-separated)')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder="area_bn, area_en, reference_no"
                      value={form.variables_csv}
                      onChange={(e) => setForm((p) => ({ ...p, variables_csv: e.target.value }))}
                    />
                  </div>
                  <div className="col-12">
                    <button type="submit" className="btn btn-primary btn-sm w-100" disabled={saveMutation.isPending}>
                      {saveMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('সংরক্ষণ করুন', 'Save Template')}
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        )}

        <div className={canManage ? 'col-lg-7' : 'col-12'}>
          {isLoading ? (
            <Loader message="টেমপ্লেট লোড হচ্ছে..." messageEn="Loading templates..." />
          ) : error ? (
            <AlertBanner variant="danger" message={formatNotificationApiError(error)} />
          ) : templates.length === 0 ? (
            <EmptyState title={t('কোনো টেমপ্লেট পাওয়া যায়নি', 'No templates found')} />
          ) : (
            <div className="card shadow-sm border-0">
              <div className="list-group list-group-flush">
                {templates.map((tpl) => (
                  <div key={tpl.id} className="list-group-item">
                    <div className="d-flex justify-content-between align-items-start gap-2">
                      <div>
                        <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
                          <code className="fw-bold">{tpl.template_code}</code>
                          <CategoryLabel category={tpl.category} />
                          <SeverityBadge severity={tpl.default_severity} />
                          <span className="badge bg-light text-dark border">{tpl.channel}</span>
                          {tpl.is_system && <span className="badge bg-dark">SYSTEM</span>}
                        </div>
                        <div className="fw-semibold">{lang === 'en' ? tpl.title_en : tpl.title_bn}</div>
                        <p className="small text-muted mb-1">{lang === 'en' ? tpl.body_en : tpl.body_bn}</p>
                      </div>
                      {canManage && (
                        <div className="d-flex gap-1">
                          <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => handleEdit(tpl)}>
                            <i className="bi bi-pencil"></i>
                          </button>
                          {!tpl.is_system && (
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-danger"
                              onClick={() => setDeleteTarget(tpl)}
                            >
                              <i className="bi bi-trash"></i>
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      <ConfirmDialog
        isOpen={Boolean(deleteTarget)}
        title={t('টেমপ্লেট আর্কাইভ নিশ্চিতকরণ', 'Archive Notification Template')}
        titleEn="Confirm Template Archival"
        message={t(
          `আপনি কি "${deleteTarget?.template_code}" টেমপ্লেটটি আর্কাইভ করতে চান?`,
          `Are you sure you want to archive template "${deleteTarget?.template_code}"?`
        )}
        confirmText={t('হ্যাঁ, আর্কাইভ করুন', 'Yes, Archive')}
        cancelText={t('বাতিল', 'Cancel')}
        isLoading={deleteMutation.isPending}
        onCancel={() => setDeleteTarget(null)}
        onConfirm={() => {
          if (deleteTarget) {
            deleteMutation.mutate(deleteTarget.id, {
              onSettled: () => setDeleteTarget(null),
            });
          }
        }}
      />
    </div>
  );
};

export default NotificationTemplatePage;
