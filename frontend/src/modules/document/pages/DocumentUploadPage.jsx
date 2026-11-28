import React, { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { documentApi } from '../../../api/documentApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  DOCUMENT_TYPE_LABELS,
  ENTITY_TYPE_LABELS,
  formatDocumentApiError,
} from '../utils/documentPermissions';

const DocumentFormInner = ({ existingDoc, lookups, initialEntityType, initialEntityId }) => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const isEdit = Boolean(existingDoc);

  const [form, setForm] = useState(() => ({
    title_bn: existingDoc?.title_bn || '',
    title_en: existingDoc?.title_en || '',
    reference_no: existingDoc?.reference_no || '',
    category_id: existingDoc?.category_id || (lookups.categories?.[0]?.id || ''),
    document_type: existingDoc?.document_type || 'OTHER',
    description_bn: existingDoc?.description_bn || '',
    description_en: existingDoc?.description_en || '',
    issuing_organization: existingDoc?.issuing_organization || '',
    organization_id: existingDoc?.organization_id || user?.organization_id || '',
    administrative_unit_id: existingDoc?.administrative_unit_id || user?.administrative_unit_id || '',
    owner_user_id: existingDoc?.owner_user_id || user?.id || '',
    effective_date: existingDoc?.effective_date || '',
    expires_at: existingDoc?.expires_at ? existingDoc.expires_at.slice(0, 10) : '',
    entity_type: existingDoc?.entity_type || initialEntityType || 'GENERAL',
    entity_id: existingDoc?.entity_id || initialEntityId || '',
    classification: existingDoc?.classification || 'INTERNAL',
    tags: (existingDoc?.tags || []).join(', '),
    submit_for_approval: false,
    change_notes: '',
  }));

  const [file, setFile] = useState(null);
  const [entitySearch, setEntitySearch] = useState('');
  const [feedback, setFeedback] = useState(null);

  const { data: targetEntitiesRes, isFetching: fetchingTargets } = useQuery({
    queryKey: ['documents', 'target-entities', form.entity_type, entitySearch],
    queryFn: () =>
      documentApi.getTargetEntities({
        entity_type: form.entity_type,
        search: entitySearch || undefined,
      }),
    enabled: Boolean(form.entity_type && form.entity_type !== 'GENERAL'),
  });

  const targetEntities = targetEntitiesRes?.data || [];

  const saveMutation = useMutation({
    mutationFn: async () => {
      const tagsArray = form.tags
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

      if (isEdit) {
        return documentApi.updateDocument(existingDoc.id, {
          title_bn: form.title_bn,
          title_en: form.title_en,
          reference_no: form.reference_no || null,
          category_id: form.category_id,
          document_type: form.document_type,
          description_bn: form.description_bn || null,
          description_en: form.description_en || null,
          issuing_organization: form.issuing_organization || null,
          organization_id: form.organization_id || null,
          administrative_unit_id: form.administrative_unit_id || null,
          owner_user_id: form.owner_user_id || null,
          effective_date: form.effective_date || null,
          expires_at: form.expires_at || null,
          entity_type: form.entity_type === 'GENERAL' ? null : form.entity_type,
          entity_id: form.entity_type === 'GENERAL' ? null : form.entity_id || null,
          classification: form.classification,
          tags: tagsArray,
        });
      }

      if (!file) {
        throw { message: t('অনুগ্রহ করে একটি ফাইল নির্বাচন করুন।', 'Please select a file to upload.') };
      }

      const fd = new FormData();
      fd.append('file', file);
      fd.append('title_bn', form.title_bn);
      fd.append('title_en', form.title_en);
      if (form.reference_no) fd.append('reference_no', form.reference_no);
      fd.append('category_id', form.category_id);
      fd.append('document_type', form.document_type);
      if (form.description_bn) fd.append('description_bn', form.description_bn);
      if (form.description_en) fd.append('description_en', form.description_en);
      if (form.issuing_organization) fd.append('issuing_organization', form.issuing_organization);
      if (form.organization_id) fd.append('organization_id', form.organization_id);
      if (form.administrative_unit_id) fd.append('administrative_unit_id', form.administrative_unit_id);
      if (form.owner_user_id) fd.append('owner_user_id', form.owner_user_id);
      if (form.effective_date) fd.append('effective_date', form.effective_date);
      if (form.expires_at) fd.append('expires_at', form.expires_at);
      if (form.entity_type && form.entity_type !== 'GENERAL') {
        fd.append('entity_type', form.entity_type);
        if (form.entity_id) fd.append('entity_id', form.entity_id);
      }
      fd.append('classification', form.classification);
      tagsArray.forEach((tag) => fd.append('tags[]', tag));
      if (form.submit_for_approval) fd.append('submit_for_approval', '1');
      if (form.change_notes) fd.append('change_notes', form.change_notes);

      return documentApi.uploadDocument(fd);
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['documents'] });
      const docId = res?.data?.id || existingDoc?.id;
      navigate(docId ? `/documents/${docId}` : '/documents');
    },
    onError: (err) => {
      setFeedback({
        variant: 'danger',
        message: formatDocumentApiError(err, t('নথি সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save document.')),
      });
    },
  });

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className={`bi ${isEdit ? 'bi-pencil-square' : 'bi-cloud-upload-fill'} text-primary me-2`}></i>
            {isEdit
              ? t('নথির মেটাডাটা সম্পাদনা', 'Edit Document Metadata')
              : t('নতুন নথি আপলোড ও নিবন্ধন (e-Docs)', 'Upload & Register Document (e-Docs)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'ম্যাজিক-বাইট MIME যাচাই, অ্যান্টিভাইরাস স্ক্যানিং, SHA-256 হ্যাশ এবং প্রাইভেট স্টোরেজ এনক্রিপশনসহ নিরাপদ আপলোড',
              'Secured upload pipeline with magic-byte verification, antivirus scan, SHA-256 hash, and private storage'
            )}
          </p>
        </div>
        <Link to="/documents" className="btn btn-outline-secondary btn-sm">
          <i className="bi bi-arrow-left me-1"></i>
          {t('ফিরে যান', 'Back to Registry')}
        </Link>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      <form
        onSubmit={(e) => {
          e.preventDefault();
          saveMutation.mutate();
        }}
      >
        <div className="row g-3">
          <div className="col-lg-8">
            <div className="card shadow-sm border-0 mb-3">
              <div className="card-header bg-white fw-bold">{t('নথির মূল তথ্য', 'Core Metadata')}</div>
              <div className="card-body">
                <div className="row g-3">
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('নথির শিরোনাম (বাংলা)', 'Document Title (Bangla)')} *</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      required
                      value={form.title_bn}
                      onChange={(e) => setForm((p) => ({ ...p, title_bn: e.target.value }))}
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('নথির শিরোনাম (ইংরেজি)', 'Document Title (English)')} *</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      required
                      value={form.title_en}
                      onChange={(e) => setForm((p) => ({ ...p, title_en: e.target.value }))}
                    />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('রেফারেন্স / স্মারক নম্বর', 'Reference / Memo No')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder="51.00.0000.123.14.001.26-102"
                      value={form.reference_no}
                      onChange={(e) => setForm((p) => ({ ...p, reference_no: e.target.value }))}
                    />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('নথির ক্যাটাগরি', 'Document Category')} *</label>
                    <select
                      className="form-select form-select-sm"
                      required
                      value={form.category_id}
                      onChange={(e) => setForm((p) => ({ ...p, category_id: e.target.value }))}
                    >
                      {(lookups.categories || []).map((c) => (
                        <option key={c.id} value={c.id}>{lang === 'en' ? c.name_en : c.name_bn}</option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('নথির ধরন', 'Document Type')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={form.document_type}
                      onChange={(e) => setForm((p) => ({ ...p, document_type: e.target.value }))}
                    >
                      {(lookups.document_types || []).map((dt) => (
                        <option key={dt} value={dt}>
                          {lang === 'en' ? (DOCUMENT_TYPE_LABELS[dt]?.en || dt) : (DOCUMENT_TYPE_LABELS[dt]?.bn || dt)}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('বিবরণ (বাংলা)', 'Description (Bangla)')}</label>
                    <textarea
                      className="form-control form-control-sm"
                      rows={3}
                      value={form.description_bn}
                      onChange={(e) => setForm((p) => ({ ...p, description_bn: e.target.value }))}
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('বিবরণ (ইংরেজি)', 'Description (English)')}</label>
                    <textarea
                      className="form-control form-control-sm"
                      rows={3}
                      value={form.description_en}
                      onChange={(e) => setForm((p) => ({ ...p, description_en: e.target.value }))}
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('ইস্যুকারী সংস্থা / দপ্তর', 'Issuing Organization')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder={t('যেমন: দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয়', 'e.g. Ministry of Disaster Management & Relief')}
                      value={form.issuing_organization}
                      onChange={(e) => setForm((p) => ({ ...p, issuing_organization: e.target.value }))}
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('ট্যাগসমূহ (কমা দ্বারা পৃথক)', 'Tags (comma-separated)')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder="cyclone, relief, go-2026, khulna"
                      value={form.tags}
                      onChange={(e) => setForm((p) => ({ ...p, tags: e.target.value }))}
                    />
                  </div>
                </div>
              </div>
            </div>

            {!isEdit && (
              <div className="card shadow-sm border-0">
                <div className="card-header bg-white fw-bold">{t('ফাইল আপলোড ও নিরাপত্তা', 'File Upload & Security')}</div>
                <div className="card-body">
                  <div className="mb-3">
                    <label className="form-label small fw-semibold">{t('ফাইল নির্বাচন করুন', 'Select File')} *</label>
                    <input
                      type="file"
                      className="form-control form-control-sm"
                      required
                      accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp,.txt"
                      onChange={(e) => setFile(e.target.files?.[0] || null)}
                    />
                    <div className="form-text">
                      {t(
                        'অনুমোদিত ফরম্যাট: PDF, DOC, DOCX, XLS, XLSX, CSV, JPEG, PNG, WEBP (সর্বোচ্চ ২০ এমবি)।',
                        'Approved formats: PDF, DOC, DOCX, XLS, XLSX, CSV, JPEG, PNG, WEBP (Max 20 MB).'
                      )}
                    </div>
                  </div>
                  <div className="form-check">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id="submitApprovalCheck"
                      checked={form.submit_for_approval}
                      onChange={(e) => setForm((p) => ({ ...p, submit_for_approval: e.target.checked }))}
                    />
                    <label className="form-check-label small" htmlFor="submitApprovalCheck">
                      {t('আপলোডের সাথে সাথে অনুমোদনের জন্য দাখিল করুন', 'Submit immediately for document approval')}
                    </label>
                  </div>
                </div>
              </div>
            )}
          </div>

          <div className="col-lg-4">
            <div className="card shadow-sm border-0 mb-3">
              <div className="card-header bg-white fw-bold">{t('অধিক্ষেত্র, গোপনীয়তা ও মেয়াদ', 'Scope, Classification & Expiry')}</div>
              <div className="card-body">
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('প্রশাসনিক ইউনিট', 'Administrative Unit')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={form.administrative_unit_id}
                    onChange={(e) => setForm((p) => ({ ...p, administrative_unit_id: e.target.value }))}
                  >
                    <option value="">{t('স্বয়ংক্রিয় নির্ধারণ', 'Auto / User Scope')}</option>
                    {(lookups.administrative_units || []).map((u) => (
                      <option key={u.id} value={u.id}>
                        {lang === 'en' ? `${u.name_en} (${u.tier_level})` : `${u.name_bn} (${u.tier_level})`}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('গোপনীয়তার শ্রেণিবিন্যাস', 'Security Classification')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={form.classification}
                    onChange={(e) => setForm((p) => ({ ...p, classification: e.target.value }))}
                  >
                    {(lookups.classifications || []).map((cl) => (
                      <option key={cl} value={cl}>{cl}</option>
                    ))}
                  </select>
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('নথির মালিক (Owner)', 'Document Owner')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={form.owner_user_id}
                    onChange={(e) => setForm((p) => ({ ...p, owner_user_id: e.target.value }))}
                  >
                    <option value="">{t('আপলোডকারী', 'Current User')}</option>
                    {(lookups.users || []).map((u) => (
                      <option key={u.id} value={u.id}>{u.full_name_en} ({u.role_code})</option>
                    ))}
                  </select>
                </div>
                <div className="row g-2">
                  <div className="col-6">
                    <label className="form-label small fw-semibold">{t('কার্যকর তারিখ', 'Effective Date')}</label>
                    <input
                      type="date"
                      className="form-control form-control-sm"
                      value={form.effective_date}
                      onChange={(e) => setForm((p) => ({ ...p, effective_date: e.target.value }))}
                    />
                  </div>
                  <div className="col-6">
                    <label className="form-label small fw-semibold">{t('মেয়াদ উত্তীর্ণের তারিখ', 'Expiry Date')}</label>
                    <input
                      type="date"
                      className="form-control form-control-sm"
                      value={form.expires_at}
                      onChange={(e) => setForm((p) => ({ ...p, expires_at: e.target.value }))}
                    />
                  </div>
                </div>
              </div>
            </div>

            <div className="card shadow-sm border-0 mb-3">
              <div className="card-header bg-white fw-bold">{t('সংশ্লিষ্ট মডিউল ও রেকর্ড সংযোগ', 'Related Module & Record')}</div>
              <div className="card-body">
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('রেকর্ডের ধরন', 'Target Entity Type')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={form.entity_type}
                    onChange={(e) => setForm((p) => ({ ...p, entity_type: e.target.value, entity_id: '' }))}
                  >
                    <option value="GENERAL">{lang === 'en' ? ENTITY_TYPE_LABELS.GENERAL.en : ENTITY_TYPE_LABELS.GENERAL.bn}</option>
                    {(lookups.entity_types || []).map((et) => (
                      <option key={et} value={et}>
                        {lang === 'en' ? (ENTITY_TYPE_LABELS[et]?.en || et) : (ENTITY_TYPE_LABELS[et]?.bn || et)}
                      </option>
                    ))}
                  </select>
                </div>

                {form.entity_type && form.entity_type !== 'GENERAL' && (
                  <>
                    <div className="mb-2">
                      <label className="form-label small fw-semibold">{t('রেকর্ড খুঁজুন', 'Search Record')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        placeholder={t('কোড বা নাম লিখুন...', 'Type code or name...')}
                        value={entitySearch}
                        onChange={(e) => setEntitySearch(e.target.value)}
                      />
                    </div>
                    <div className="mb-2">
                      <label className="form-label small fw-semibold">{t('লক্ষ্য রেকর্ড নির্বাচন', 'Select Target Record')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={form.entity_id}
                        onChange={(e) => {
                          const chosen = targetEntities.find((x) => x.entity_id === e.target.value);
                          setForm((p) => ({
                            ...p,
                            entity_id: e.target.value,
                            administrative_unit_id: chosen?.administrative_unit_id || p.administrative_unit_id,
                          }));
                        }}
                      >
                        <option value="">{fetchingTargets ? t('লোড হচ্ছে...', 'Loading...') : t('-- নির্বাচন করুন --', '-- Select Record --')}</option>
                        {targetEntities.map((item) => (
                          <option key={item.entity_id} value={item.entity_id}>
                            {lang === 'en' ? item.label_en : item.label_bn}
                          </option>
                        ))}
                      </select>
                    </div>
                  </>
                )}
              </div>
            </div>

            <button type="submit" className="btn btn-primary w-100" disabled={saveMutation.isPending}>
              <i className="bi bi-check-circle-fill me-2"></i>
              {saveMutation.isPending
                ? t('প্রক্রিয়াকরণ হচ্ছে...', 'Processing...')
                : isEdit
                ? t('মেটাডাটা হালনাগাদ করুন', 'Update Metadata')
                : t('নিরাপদে আপলোড করুন', 'Upload & Register Document')}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
};

export const DocumentUploadPage = () => {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const isEdit = Boolean(id);

  const { data: lookupsRes, isLoading: loadingLookups } = useQuery({
    queryKey: ['documents', 'lookups'],
    queryFn: () => documentApi.getLookups(),
  });

  const { data: detailRes, isLoading: loadingDoc } = useQuery({
    queryKey: ['documents', 'detail', id],
    queryFn: () => documentApi.getDocument(id),
    enabled: isEdit,
  });

  if (loadingLookups || (isEdit && loadingDoc)) {
    return <Loader />;
  }

  const lookups = lookupsRes?.data || {};
  const existingDoc = isEdit ? detailRes?.data : null;

  return (
    <DocumentFormInner
      key={existingDoc?.id || 'new'}
      existingDoc={existingDoc}
      lookups={lookups}
      initialEntityType={searchParams.get('entity_type')}
      initialEntityId={searchParams.get('entity_id')}
    />
  );
};

export default DocumentUploadPage;
