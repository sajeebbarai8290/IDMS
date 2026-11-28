import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { documentApi } from '../../../api/documentApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canManageDocumentCategories,
  formatDocumentApiError,
} from '../utils/documentPermissions';
import { DocumentClassificationBadge } from '../components/DocumentBadges';

const INITIAL_FORM = {
  code: '',
  name_en: '',
  name_bn: '',
  description_en: '',
  description_bn: '',
  default_classification: 'INTERNAL',
  allowed_formats: 'PDF,DOCX,XLSX,CSV,JPEG,PNG',
  retention_days: 1825,
  requires_approval: false,
  is_active: true,
};

export const DocumentCategoryPage = () => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();
  const canManage = canManageDocumentCategories(user);

  const [form, setForm] = useState(INITIAL_FORM);
  const [editingId, setEditingId] = useState(null);
  const [feedback, setFeedback] = useState(null);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['documents', 'categories'],
    queryFn: () => documentApi.getCategories(),
  });

  const saveMutation = useMutation({
    mutationFn: () => {
      const formats = form.allowed_formats
        .split(',')
        .map((s) => s.trim().toUpperCase())
        .filter(Boolean);
      const payload = {
        ...form,
        allowed_formats: formats,
        retention_days: form.retention_days ? Number(form.retention_days) : null,
      };
      return editingId
        ? documentApi.updateCategory(editingId, payload)
        : documentApi.createCategory(payload);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['documents'] });
      setForm(INITIAL_FORM);
      setEditingId(null);
      setFeedback({
        variant: 'success',
        message: t('নথির ক্যাটাগরি সফলভাবে সংরক্ষিত হয়েছে।', 'Document category saved successfully.'),
      });
    },
    onError: (err) => {
      setFeedback({
        variant: 'danger',
        message: formatDocumentApiError(err, t('সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save category.')),
      });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => documentApi.deleteCategory(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['documents'] });
      setFeedback({ variant: 'success', message: t('ক্যাটাগরি মুছে ফেলা হয়েছে।', 'Category deleted.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDocumentApiError(err, t('মুছে ফেলা ব্যর্থ।', 'Delete failed.')) });
    },
  });

  const categories = res?.data || [];

  const handleEdit = (cat) => {
    setEditingId(cat.id);
    setForm({
      code: cat.code,
      name_en: cat.name_en,
      name_bn: cat.name_bn,
      description_en: cat.description_en || '',
      description_bn: cat.description_bn || '',
      default_classification: cat.default_classification || 'INTERNAL',
      allowed_formats: (cat.allowed_formats || []).join(', '),
      retention_days: cat.retention_days || '',
      requires_approval: Boolean(cat.requires_approval),
      is_active: Boolean(cat.is_active),
    });
  };

  if (isLoading) return <Loader />;

  return (
    <div className="container-fluid py-3">
      <div className="mb-3">
        <h4 className="fw-bold mb-1">
          <i className="bi bi-tags-fill text-primary me-2"></i>
          {t('নথির ক্যাটাগরি ব্যবস্থাপনা (M14)', 'Document Categories (M14)')}
        </h4>
        <p className="text-muted small mb-0">
          {t(
            'সরকারি আদেশ, প্রতিবেদন, অনুমোদন নথি এবং কাস্টম নথি ক্যাটাগরি কনফিগারেশন',
            'Configure system and custom document categories, formats, retention, and approval rules'
          )}
        </p>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}
      {error && (
        <AlertBanner variant="danger" message={formatDocumentApiError(error, t('তথ্য লোড ব্যর্থ।', 'Failed to load categories.'))} />
      )}

      <div className="row g-3">
        <div className={canManage ? 'col-lg-8' : 'col-12'}>
          <div className="card shadow-sm border-0">
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0 small">
                <thead className="table-light">
                  <tr>
                    <th>{t('কোড ও নাম', 'Code & Name')}</th>
                    <th>{t('ডিফল্ট শ্রেণিবিন্যাস', 'Default Class')}</th>
                    <th>{t('অনুমোদিত ফরম্যাট', 'Allowed Formats')}</th>
                    <th>{t('অনুমোদন', 'Approval')}</th>
                    <th>{t('নথি সংখ্যা', 'Docs')}</th>
                    {canManage && <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>}
                  </tr>
                </thead>
                <tbody>
                  {categories.map((cat) => (
                    <tr key={cat.id}>
                      <td>
                        <div className="fw-bold">{lang === 'en' ? cat.name_en : cat.name_bn}</div>
                        <span className="text-muted">{cat.code}</span>
                        {cat.is_system && <span className="badge bg-light text-dark border ms-1">SYSTEM</span>}
                      </td>
                      <td><DocumentClassificationBadge classification={cat.default_classification} /></td>
                      <td>{(cat.allowed_formats || []).join(', ') || 'ALL'}</td>
                      <td>
                        <span className={`badge bg-${cat.requires_approval ? 'warning' : 'secondary'}`}>
                          {cat.requires_approval ? t('প্রয়োজন', 'Required') : t('ঐচ্ছিক', 'Optional')}
                        </span>
                      </td>
                      <td className="fw-bold">{cat.documents_count ?? 0}</td>
                      {canManage && (
                        <td className="text-end">
                          <div className="btn-group btn-group-sm">
                            <button type="button" className="btn btn-outline-primary" onClick={() => handleEdit(cat)}>
                              <i className="bi bi-pencil"></i>
                            </button>
                            {!cat.is_system && (
                              <button
                                type="button"
                                className="btn btn-outline-danger"
                                onClick={() => deleteMutation.mutate(cat.id)}
                                disabled={deleteMutation.isPending}
                              >
                                <i className="bi bi-trash"></i>
                              </button>
                            )}
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {canManage && (
          <div className="col-lg-4">
            <div className="card shadow-sm border-0">
              <div className="card-header bg-white fw-bold">
                {editingId ? t('ক্যাটাগরি হালনাগাদ', 'Update Category') : t('নতুন ক্যাটাগরি তৈরি', 'Add Custom Category')}
              </div>
              <div className="card-body">
                <form
                  onSubmit={(e) => {
                    e.preventDefault();
                    saveMutation.mutate();
                  }}
                >
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('কোড', 'Code')} *</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      required
                      disabled={Boolean(editingId)}
                      placeholder="CUSTOM_SURVEY"
                      value={form.code}
                      onChange={(e) => setForm((p) => ({ ...p, code: e.target.value.toUpperCase() }))}
                    />
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('নাম (বাংলা)', 'Name (Bangla)')} *</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      required
                      value={form.name_bn}
                      onChange={(e) => setForm((p) => ({ ...p, name_bn: e.target.value }))}
                    />
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('নাম (ইংরেজি)', 'Name (English)')} *</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      required
                      value={form.name_en}
                      onChange={(e) => setForm((p) => ({ ...p, name_en: e.target.value }))}
                    />
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('ডিফল্ট শ্রেণিবিন্যাস', 'Default Classification')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={form.default_classification}
                      onChange={(e) => setForm((p) => ({ ...p, default_classification: e.target.value }))}
                    >
                      <option value="PUBLIC">PUBLIC</option>
                      <option value="INTERNAL">INTERNAL</option>
                      <option value="CONFIDENTIAL">CONFIDENTIAL</option>
                      <option value="RESTRICTED">RESTRICTED</option>
                    </select>
                  </div>
                  <div className="mb-2">
                    <label className="form-label small fw-semibold">{t('অনুমোদিত ফরম্যাট', 'Allowed Formats')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={form.allowed_formats}
                      onChange={(e) => setForm((p) => ({ ...p, allowed_formats: e.target.value }))}
                    />
                  </div>
                  <div className="form-check mb-2">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id="reqApp"
                      checked={form.requires_approval}
                      onChange={(e) => setForm((p) => ({ ...p, requires_approval: e.target.checked }))}
                    />
                    <label className="form-check-label small" htmlFor="reqApp">
                      {t('অনুমোদন আবশ্যক', 'Requires formal approval')}
                    </label>
                  </div>
                  <div className="d-flex gap-2">
                    <button type="submit" className="btn btn-primary btn-sm" disabled={saveMutation.isPending}>
                      {saveMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('সংরক্ষণ করুন', 'Save Category')}
                    </button>
                    {editingId && (
                      <button
                        type="button"
                        className="btn btn-outline-secondary btn-sm"
                        onClick={() => {
                          setEditingId(null);
                          setForm(INITIAL_FORM);
                        }}
                      >
                        {t('বাতিল', 'Cancel')}
                      </button>
                    )}
                  </div>
                </form>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default DocumentCategoryPage;
