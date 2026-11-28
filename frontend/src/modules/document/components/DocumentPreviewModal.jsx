import React, { useEffect, useState } from 'react';
import { documentApi } from '../../../api/documentApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import { formatDocumentApiError, triggerBlobDownload } from '../utils/documentPermissions';

export const DocumentPreviewModal = ({ doc, versionId = null, onClose }) => {
  const { t } = useLanguage();
  const [blobUrl, setBlobUrl] = useState(null);
  const [textContent, setTextContent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!doc) return undefined;
    let active = true;
    let objectUrl = null;

    const loadPreview = async () => {
      try {
        const res = await documentApi.previewDocument(doc.id, versionId ? { version_id: versionId } : {});
        const blob = res instanceof Blob ? res : (res?.data ?? res);
        if (!active) return;

        if (doc.mime_type === 'text/csv' || doc.mime_type === 'text/plain') {
          const text = await blob.text();
          if (active) {
            setTextContent(text);
            setLoading(false);
          }
          return;
        }

        objectUrl = window.URL.createObjectURL(new Blob([blob], { type: doc.mime_type }));
        setBlobUrl(objectUrl);
        setLoading(false);
      } catch (err) {
        if (active) {
          setError(formatDocumentApiError(err, t('প্রিভিউ লোড করা যায়নি।', 'Unable to load preview.')));
          setLoading(false);
        }
      }
    };

    loadPreview();

    return () => {
      active = false;
      if (objectUrl) {
        window.URL.revokeObjectURL(objectUrl);
      }
    };
  }, [doc, versionId, t]);

  if (!doc) return null;

  const handleDownload = async () => {
    const res = await documentApi.downloadDocument(doc.id, versionId ? { version_id: versionId } : {});
    const blob = res instanceof Blob ? res : (res?.data ?? res);
    triggerBlobDownload(blob, doc.original_filename || 'document');
  };

  return (
    <div className="modal d-block" style={{ backgroundColor: 'rgba(0,0,0,0.6)' }} role="dialog" aria-modal="true">
      <div className="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div className="modal-content">
          <div className="modal-header">
            <div>
              <h5 className="modal-title mb-0">{doc.title_bn || doc.title_en}</h5>
              <small className="text-muted">{doc.document_no} • {doc.original_filename}</small>
            </div>
            <div className="d-flex gap-2">
              <button type="button" className="btn btn-sm btn-outline-primary" onClick={handleDownload}>
                <i className="bi bi-download me-1"></i>
                {t('ডাউনলোড', 'Download')}
              </button>
              <button type="button" className="btn-close" onClick={onClose} aria-label="Close"></button>
            </div>
          </div>
          <div className="modal-body" style={{ minHeight: '65vh' }}>
            {loading && (
              <div className="text-center py-5">
                <div className="spinner-border text-primary" role="status"></div>
                <p className="text-muted mt-2">{t('নথি প্রিভিউ লোড হচ্ছে...', 'Loading authorized document preview...')}</p>
              </div>
            )}
            {error && <div className="alert alert-warning">{error}</div>}
            {!loading && !error && textContent !== null && (
              <pre className="bg-light p-3 rounded border small" style={{ maxHeight: '60vh', overflow: 'auto' }}>
                {textContent}
              </pre>
            )}
            {!loading && !error && blobUrl && doc.mime_type === 'application/pdf' && (
              <iframe title={doc.title_en} src={blobUrl} style={{ width: '100%', height: '65vh', border: 'none' }} />
            )}
            {!loading && !error && blobUrl && doc.mime_type?.startsWith('image/') && (
              <div className="text-center">
                <img src={blobUrl} alt={doc.title_en} className="img-fluid rounded shadow-sm" style={{ maxHeight: '65vh' }} />
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default DocumentPreviewModal;
