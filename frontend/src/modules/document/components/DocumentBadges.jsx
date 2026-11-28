import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  APPROVAL_STATUS_META,
  CLASSIFICATION_META,
  DOCUMENT_TYPE_LABELS,
  SCAN_STATUS_META,
} from '../utils/documentPermissions';

export const DocumentApprovalBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = APPROVAL_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};

export const DocumentClassificationBadge = ({ classification }) => {
  const { t } = useLanguage();
  const meta = CLASSIFICATION_META[classification] || { bn: classification, en: classification, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};

export const DocumentScanBadge = ({ scanStatus }) => {
  const { t } = useLanguage();
  const meta = SCAN_STATUS_META[scanStatus] || { bn: scanStatus, en: scanStatus, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};

export const DocumentTypeLabel = ({ type }) => {
  const { t } = useLanguage();
  const meta = DOCUMENT_TYPE_LABELS[type] || { bn: type, en: type };
  return <span>{t(meta.bn, meta.en)}</span>;
};

export const FileFormatIcon = ({ extension }) => {
  const ext = (extension || '').toUpperCase();
  if (ext === 'PDF') return <i className="bi bi-file-earmark-pdf-fill text-danger me-1"></i>;
  if (ext === 'DOC' || ext === 'DOCX') return <i className="bi bi-file-earmark-word-fill text-primary me-1"></i>;
  if (ext === 'XLS' || ext === 'XLSX' || ext === 'CSV') return <i className="bi bi-file-earmark-excel-fill text-success me-1"></i>;
  if (ext === 'JPG' || ext === 'JPEG' || ext === 'PNG' || ext === 'WEBP') return <i className="bi bi-file-earmark-image-fill text-info me-1"></i>;
  return <i className="bi bi-file-earmark-text-fill text-secondary me-1"></i>;
};
