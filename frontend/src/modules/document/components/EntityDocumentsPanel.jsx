import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { documentApi } from '../../../api/documentApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  canUploadDocuments,
  formatFileSize,
  triggerBlobDownload,
} from '../utils/documentPermissions';
import { DocumentApprovalBadge, DocumentClassificationBadge, FileFormatIcon } from './DocumentBadges';
import DocumentPreviewModal from './DocumentPreviewModal';

/**
 * Reusable cross-module component displaying centralized e-Docs attached to any IDMS entity.
 */
export const EntityDocumentsPanel = ({ entityType, entityId }) => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const [previewDoc, setPreviewDoc] = useState(null);

  const { data: res, isLoading } = useQuery({
    queryKey: ['documents', 'entity', entityType, entityId],
    queryFn: () => documentApi.getEntityDocuments(entityType, entityId),
    enabled: Boolean(entityType && entityId),
  });

  const docs = res?.data || [];

  const handleDownload = async (doc) => {
    const blobRes = await documentApi.downloadDocument(doc.id);
    const blob = blobRes instanceof Blob ? blobRes : (blobRes?.data ?? blobRes);
    triggerBlobDownload(blob, doc.original_filename || 'document');
  };

  return (
    <div className="card shadow-sm border-0">
      <div className="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 className="mb-0 fw-bold">
          <i className="bi bi-folder2-open me-2 text-primary"></i>
          {t('সংযুক্ত কেন্দ্রীয় নথিপত্র (e-Docs)', 'Linked Central Documents (e-Docs)')}
        </h6>
        {canUploadDocuments(user) && (
          <Link
            to={`/documents/upload?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}`}
            className="btn btn-sm btn-outline-primary"
          >
            <i className="bi bi-upload me-1"></i>
            {t('নতুন নথি সংযুক্ত করুন', 'Attach Document')}
          </Link>
        )}
      </div>
      <div className="card-body p-0">
        {isLoading ? (
          <div className="p-3 text-muted small">{t('নথি লোড হচ্ছে...', 'Loading documents...')}</div>
        ) : docs.length === 0 ? (
          <div className="p-3 text-muted small">{t('কোনো কেন্দ্রীয় নথি সংযুক্ত নেই।', 'No centralized documents linked yet.')}</div>
        ) : (
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0 small">
              <thead className="table-light">
                <tr>
                  <th>{t('নথি ও শিরোনাম', 'Document')}</th>
                  <th>{t('ক্যাটাগরি', 'Category')}</th>
                  <th>{t('শ্রেণিবিন্যাস', 'Classification')}</th>
                  <th>{t('অবস্থা', 'Status')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {docs.map((d) => (
                  <tr key={d.id}>
                    <td>
                      <FileFormatIcon extension={d.file_extension} />
                      <Link to={`/documents/${d.id}`} className="fw-semibold text-decoration-none">
                        {lang === 'en' ? d.title_en : d.title_bn}
                      </Link>
                      <div className="text-muted">
                        {d.document_no} • v{d.current_version_number} • {formatFileSize(d.file_size_bytes)}
                      </div>
                    </td>
                    <td>{lang === 'en' ? d.category?.name_en : d.category?.name_bn}</td>
                    <td><DocumentClassificationBadge classification={d.classification} /></td>
                    <td><DocumentApprovalBadge status={d.approval_status} /></td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        {d.is_previewable && (
                          <button type="button" className="btn btn-outline-secondary" onClick={() => setPreviewDoc(d)}>
                            <i className="bi bi-eye"></i>
                          </button>
                        )}
                        <button type="button" className="btn btn-outline-primary" onClick={() => handleDownload(d)}>
                          <i className="bi bi-download"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      {previewDoc && <DocumentPreviewModal doc={previewDoc} onClose={() => setPreviewDoc(null)} />}
    </div>
  );
};

export default EntityDocumentsPanel;
