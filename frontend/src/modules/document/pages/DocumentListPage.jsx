import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { documentApi } from '../../../api/documentApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import {
  DocumentApprovalBadge,
  DocumentClassificationBadge,
  DocumentScanBadge,
  FileFormatIcon,
} from '../components/DocumentBadges';
import DocumentPreviewModal from '../components/DocumentPreviewModal';
import {
  APPROVAL_STATUS_META,
  CLASSIFICATION_META,
  canUploadDocuments,
  DOCUMENT_TYPE_LABELS,
  formatDocumentApiError,
  formatFileSize,
  triggerBlobDownload,
} from '../utils/documentPermissions';

export const DocumentListPage = () => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const [searchParams] = useSearchParams();

  const [filters, setFilters] = useState({
    search: searchParams.get('search') || '',
    category_id: searchParams.get('category_id') || '',
    document_type: '',
    module_code: searchParams.get('module_code') || '',
    approval_status: searchParams.get('approval_status') || '',
    classification: '',
    administrative_unit_id: '',
    expiring_within_days: searchParams.get('expiring_within_days') || '',
    tag: '',
    sort_by: 'uploaded_at',
    sort_dir: 'desc',
    page: 1,
    per_page: 15,
  });

  const [previewDoc, setPreviewDoc] = useState(null);
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['documents', 'lookups'],
    queryFn: () => documentApi.getLookups(),
  });

  const { data: listRes, isLoading, error } = useQuery({
    queryKey: ['documents', 'list', filters],
    queryFn: () => {
      const clean = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined)
      );
      return documentApi.getDocuments(clean);
    },
  });

  const lookups = lookupsRes?.data || {};
  const docs = listRes?.data || [];
  const meta = listRes?.meta || { current_page: 1, last_page: 1, total: 0 };

  const updateFilter = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: key === 'page' ? value : 1 }));
  };

  const handleDownload = async (doc) => {
    try {
      const blobRes = await documentApi.downloadDocument(doc.id);
      const blob = blobRes instanceof Blob ? blobRes : (blobRes?.data ?? blobRes);
      triggerBlobDownload(blob, doc.original_filename || 'document');
    } catch (err) {
      setFeedback({
        variant: 'danger',
        message: formatDocumentApiError(err, t('ফাইল ডাউনলোড ব্যর্থ হয়েছে।', 'File download failed.')),
      });
    }
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-files text-primary me-2"></i>
            {t('কেন্দ্রীয় নথিপত্র তালিকা (e-Docs)', 'Central Document Registry (e-Docs)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'নিরাপদ নথি অনুসন্ধান, মেটাডাটা ফিল্টার, প্রিভিউ এবং অনুমোদন অবস্থা পর্যালোচনা',
              'Search, metadata filtering, authorized inline preview, and approval status tracking'
            )}
          </p>
        </div>

        <div className="d-flex gap-2">
          <Link to="/documents/dashboard" className="btn btn-outline-secondary btn-sm">
            <i className="bi bi-speedometer2 me-1"></i>
            {t('ড্যাশবোর্ড', 'Dashboard')}
          </Link>
          {canUploadDocuments(user) && (
            <Link to="/documents/upload" className="btn btn-primary btn-sm">
              <i className="bi bi-cloud-upload-fill me-1"></i>
              {t('নতুন নথি আপলোড', 'Upload Document')}
            </Link>
          )}
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {/* Search & Metadata Filter Card */}
      <div className="card shadow-sm border-0 mb-3">
        <div className="card-body">
          <div className="row g-2">
            <div className="col-md-4">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('শিরোনাম, রেফারেন্স নং, নথি নং বা ফাইল খুঁজুন...', 'Search title, ref no, doc no, or filename...')}
                value={filters.search}
                onChange={(e) => updateFilter('search', e.target.value)}
              />
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.category_id}
                onChange={(e) => updateFilter('category_id', e.target.value)}
              >
                <option value="">{t('সকল ক্যাটাগরি', 'All Categories')}</option>
                {(lookups.categories || []).map((c) => (
                  <option key={c.id} value={c.id}>{lang === 'en' ? c.name_en : c.name_bn}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.document_type}
                onChange={(e) => updateFilter('document_type', e.target.value)}
              >
                <option value="">{t('সকল ধরন', 'All Types')}</option>
                {(lookups.document_types || []).map((dt) => (
                  <option key={dt} value={dt}>
                    {lang === 'en' ? (DOCUMENT_TYPE_LABELS[dt]?.en || dt) : (DOCUMENT_TYPE_LABELS[dt]?.bn || dt)}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.approval_status}
                onChange={(e) => updateFilter('approval_status', e.target.value)}
              >
                <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
                {(lookups.approval_statuses || []).map((st) => (
                  <option key={st} value={st}>
                    {lang === 'en' ? (APPROVAL_STATUS_META[st]?.en || st) : (APPROVAL_STATUS_META[st]?.bn || st)}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.classification}
                onChange={(e) => updateFilter('classification', e.target.value)}
              >
                <option value="">{t('সকল শ্রেণিবিন্যাস', 'All Classifications')}</option>
                {(lookups.classifications || []).map((cl) => (
                  <option key={cl} value={cl}>
                    {lang === 'en' ? (CLASSIFICATION_META[cl]?.en || cl) : (CLASSIFICATION_META[cl]?.bn || cl)}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.module_code}
                onChange={(e) => updateFilter('module_code', e.target.value)}
              >
                <option value="">{t('সকল মডিউল', 'All Modules')}</option>
                {(lookups.supported_modules || []).map((m) => (
                  <option key={m} value={m}>{m}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.administrative_unit_id}
                onChange={(e) => updateFilter('administrative_unit_id', e.target.value)}
              >
                <option value="">{t('সকল এলাকা', 'All Areas')}</option>
                {(lookups.administrative_units || []).map((u) => (
                  <option key={u.id} value={u.id}>{lang === 'en' ? u.name_en : u.name_bn}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('ট্যাগ ফিল্টার...', 'Filter by tag...')}
                value={filters.tag}
                onChange={(e) => updateFilter('tag', e.target.value)}
              />
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.expiring_within_days}
                onChange={(e) => updateFilter('expiring_within_days', e.target.value)}
              >
                <option value="">{t('মেয়াদ ফিল্টার', 'Any Expiry')}</option>
                <option value="7">{t('৭ দিনের মধ্যে', 'Expiring in 7d')}</option>
                <option value="30">{t('৩০ দিনের মধ্যে', 'Expiring in 30d')}</option>
                <option value="90">{t('৯০ দিনের মধ্যে', 'Expiring in 90d')}</option>
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={`${filters.sort_by}:${filters.sort_dir}`}
                onChange={(e) => {
                  const [sb, sd] = e.target.value.split(':');
                  setFilters((prev) => ({ ...prev, sort_by: sb, sort_dir: sd, page: 1 }));
                }}
              >
                <option value="uploaded_at:desc">{t('সর্বশেষ আপলোড', 'Newest Uploads')}</option>
                <option value="uploaded_at:asc">{t('পুরাতন আপলোড', 'Oldest Uploads')}</option>
                <option value="expires_at:asc">{t('আসন্ন মেয়াদোত্তীর্ণ', 'Earliest Expiry')}</option>
                <option value="title_en:asc">{t('শিরোনাম (A-Z)', 'Title (A-Z)')}</option>
                <option value="file_size_bytes:desc">{t('বড় ফাইল প্রথমে', 'Largest Files')}</option>
              </select>
            </div>
            <div className="col-md-2">
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm w-100"
                onClick={() =>
                  setFilters({
                    search: '',
                    category_id: '',
                    document_type: '',
                    module_code: '',
                    approval_status: '',
                    classification: '',
                    administrative_unit_id: '',
                    expiring_within_days: '',
                    tag: '',
                    sort_by: 'uploaded_at',
                    sort_dir: 'desc',
                    page: 1,
                    per_page: 15,
                  })
                }
              >
                <i className="bi bi-x-circle me-1"></i>
                {t('রিসেট', 'Reset')}
              </button>
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader message="নথিপত্র তালিকা লোড হচ্ছে..." messageEn="Loading documents..." />
      ) : error ? (
        <AlertBanner variant="danger" message={formatDocumentApiError(error, t('তালিকা লোড করতে ব্যর্থ।', 'Failed to load documents.'))} />
      ) : docs.length === 0 ? (
        <div className="card shadow-sm border-0">
          <div className="card-body">
            <EmptyState
              title={t('কোনো নথি পাওয়া যায়নি', 'No matching documents found')}
              description={t('অনুগ্রহ করে ফিল্টার পরিবর্তন করুন বা নতুন নথি আপলোড করুন।', 'Adjust your filters or upload a new document.')}
            />
          </div>
        </div>
      ) : (
        <div className="card shadow-sm border-0">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0 small">
              <thead className="table-light">
                <tr>
                  <th>{t('নথি ও শিরোনাম', 'Document & Title')}</th>
                  <th>{t('ক্যাটাগরি ও মডিউল', 'Category / Module')}</th>
                  <th>{t('ফাইল ও সংস্করণ', 'File / Version')}</th>
                  <th>{t('শ্রেণিবিন্যাস ও স্ক্যান', 'Security')}</th>
                  <th>{t('অনুমোদন ও মেয়াদ', 'Approval / Expiry')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {docs.map((doc) => (
                  <tr key={doc.id}>
                    <td>
                      <Link to={`/documents/${doc.id}`} className="fw-bold text-decoration-none d-block">
                        {lang === 'en' ? doc.title_en : doc.title_bn}
                      </Link>
                      <span className="text-muted">
                        {doc.document_no}
                        {doc.reference_no ? ` • Ref: ${doc.reference_no}` : ''}
                      </span>
                      {doc.issuing_organization && (
                        <div className="text-muted">{doc.issuing_organization}</div>
                      )}
                    </td>
                    <td>
                      <div>{lang === 'en' ? doc.category?.name_en : doc.category?.name_bn}</div>
                      <span className="badge bg-light text-dark border">{doc.module_code}</span>
                      {doc.entity_reference && (
                        <span className="badge bg-info-subtle text-info-emphasis ms-1">{doc.entity_reference}</span>
                      )}
                    </td>
                    <td>
                      <FileFormatIcon extension={doc.file_extension} />
                      <span>{doc.original_filename}</span>
                      <div className="text-muted">
                        v{doc.current_version_number} • {formatFileSize(doc.file_size_bytes)}
                      </div>
                    </td>
                    <td>
                      <div className="mb-1"><DocumentClassificationBadge classification={doc.classification} /></div>
                      <DocumentScanBadge scanStatus={doc.scan_status} />
                    </td>
                    <td>
                      <div className="mb-1"><DocumentApprovalBadge status={doc.approval_status} /></div>
                      {doc.expires_at && (
                        <span className={`badge bg-${doc.is_expired ? 'danger' : 'light text-dark border'}`}>
                          {t('মেয়াদ', 'Exp')}: {new Date(doc.expires_at).toLocaleDateString()}
                        </span>
                      )}
                    </td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <Link to={`/documents/${doc.id}`} className="btn btn-outline-primary" title={t('বিস্তারিত', 'Details')}>
                          <i className="bi bi-info-circle"></i>
                        </Link>
                        {doc.is_previewable && (
                          <button type="button" className="btn btn-outline-secondary" onClick={() => setPreviewDoc(doc)} title={t('প্রিভিউ', 'Preview')}>
                            <i className="bi bi-eye"></i>
                          </button>
                        )}
                        <button type="button" className="btn btn-outline-success" onClick={() => handleDownload(doc)} title={t('ডাউনলোড', 'Download')}>
                          <i className="bi bi-download"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="card-footer bg-white d-flex justify-content-between align-items-center small">
            <span className="text-muted">
              {t('মোট নথি', 'Total')}: {meta.total} | {t('পৃষ্ঠা', 'Page')} {meta.current_page} / {meta.last_page}
            </span>
            <div className="btn-group btn-group-sm">
              <button
                type="button"
                className="btn btn-outline-secondary"
                disabled={meta.current_page <= 1}
                onClick={() => updateFilter('page', meta.current_page - 1)}
              >
                {t('পূর্ববর্তী', 'Previous')}
              </button>
              <button
                type="button"
                className="btn btn-outline-secondary"
                disabled={meta.current_page >= meta.last_page}
                onClick={() => updateFilter('page', meta.current_page + 1)}
              >
                {t('পরবর্তী', 'Next')}
              </button>
            </div>
          </div>
        </div>
      )}

      {previewDoc && <DocumentPreviewModal doc={previewDoc} onClose={() => setPreviewDoc(null)} />}
    </div>
  );
};

export default DocumentListPage;
