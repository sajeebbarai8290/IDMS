import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { documentApi } from '../../../api/documentApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  DocumentApprovalBadge,
  DocumentClassificationBadge,
  DocumentScanBadge,
  DocumentTypeLabel,
  FileFormatIcon,
} from '../components/DocumentBadges';
import DocumentPreviewModal from '../components/DocumentPreviewModal';
import {
  canApproveDocuments,
  canDeleteDocuments,
  canUploadDocuments,
  canVerifyDocumentIntegrity,
  formatDocumentApiError,
  formatFileSize,
  triggerBlobDownload,
} from '../utils/documentPermissions';

export const DocumentDetailPage = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const [activeTab, setActiveTab] = useState('overview');
  const [previewState, setPreviewState] = useState(null);
  const [feedback, setFeedback] = useState(null);
  const [approvalNotes, setApprovalNotes] = useState('');
  const [versionFile, setVersionFile] = useState(null);
  const [versionNotes, setVersionNotes] = useState('');
  const [versionLabel, setVersionLabel] = useState('');
  const [signedLinkData, setSignedLinkData] = useState(null);
  const [integrityReport, setIntegrityReport] = useState(null);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  const { data: detailRes, isLoading, error } = useQuery({
    queryKey: ['documents', 'detail', id],
    queryFn: () => documentApi.getDocument(id),
  });

  const { data: historyRes } = useQuery({
    queryKey: ['documents', 'history', id],
    queryFn: () => documentApi.getHistory(id),
    enabled: activeTab === 'history',
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['documents'] });
  };

  const approvalMutation = useMutation({
    mutationFn: ({ action }) => {
      if (action === 'submit') return documentApi.submitApproval(id);
      if (action === 'approve') return documentApi.approveDocument(id, { notes: approvalNotes });
      if (action === 'reject') return documentApi.rejectDocument(id, { reason: approvalNotes });
      if (action === 'archive') return documentApi.archiveDocument(id, { notes: approvalNotes });
      throw new Error('Unknown action');
    },
    onSuccess: () => {
      invalidate();
      setApprovalNotes('');
      setFeedback({ variant: 'success', message: t('নথির অবস্থা সফলভাবে হালনাগাদ হয়েছে।', 'Document status updated successfully.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDocumentApiError(err, t('কার্যক্রম ব্যর্থ হয়েছে।', 'Action failed.')) });
    },
  });

  const versionMutation = useMutation({
    mutationFn: async () => {
      const fd = new FormData();
      fd.append('file', versionFile);
      if (versionNotes) fd.append('change_notes', versionNotes);
      if (versionLabel) fd.append('version_label', versionLabel);
      return documentApi.uploadVersion(id, fd);
    },
    onSuccess: () => {
      invalidate();
      setVersionFile(null);
      setVersionNotes('');
      setVersionLabel('');
      setFeedback({ variant: 'success', message: t('নতুন সংস্করণ সফলভাবে আপলোড হয়েছে।', 'New version uploaded successfully.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDocumentApiError(err, t('সংস্করণ আপলোড ব্যর্থ।', 'Version upload failed.')) });
    },
  });

  const restoreMutation = useMutation({
    mutationFn: (versionId) => documentApi.restoreVersion(id, versionId),
    onSuccess: () => {
      invalidate();
      setFeedback({ variant: 'success', message: t('পূর্ববর্তী সংস্করণ পুনরুদ্ধার হয়েছে।', 'Version restored as active.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDocumentApiError(err, t('পুনরুদ্ধার ব্যর্থ।', 'Restore failed.')) });
    },
  });

  const integrityMutation = useMutation({
    mutationFn: () => documentApi.verifyIntegrity(id),
    onSuccess: (res) => {
      setIntegrityReport(res?.data);
      invalidate();
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDocumentApiError(err, t('অখণ্ডতা যাচাই ব্যর্থ।', 'Integrity verification failed.')) });
    },
  });

  const signedUrlMutation = useMutation({
    mutationFn: () => documentApi.generateSignedUrl(id, { ttl_minutes: 15 }),
    onSuccess: (res) => {
      setSignedLinkData(res?.data);
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDocumentApiError(err, t('স্বাক্ষরিত লিংক তৈরি ব্যর্থ।', 'Failed to generate signed link.')) });
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => documentApi.deleteDocument(id),
    onSuccess: () => {
      invalidate();
      navigate('/documents');
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDocumentApiError(err, t('মুছে ফেলা ব্যর্থ।', 'Delete failed.')) });
    },
  });

  const handleDownload = async (versionId = null, filename = null) => {
    try {
      const blobRes = versionId
        ? await documentApi.downloadVersion(id, versionId)
        : await documentApi.downloadDocument(id);
      const blob = blobRes instanceof Blob ? blobRes : (blobRes?.data ?? blobRes);
      triggerBlobDownload(blob, filename || doc.original_filename || 'document');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatDocumentApiError(err, t('ডাউনলোড ব্যর্থ।', 'Download failed.')) });
    }
  };

  if (isLoading) return <Loader />;
  if (error) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner variant="danger" message={formatDocumentApiError(error, t('নথির তথ্য লোড করা যায়নি।', 'Failed to load document.'))} />
      </div>
    );
  }

  const doc = detailRes?.data;
  const entitySummary = detailRes?.entity_summary;
  if (!doc) return null;

  const isUploaderOrSubmitter = doc.uploaded_by === user?.id || (doc.submitted_by && doc.submitted_by === user?.id);
  const canApproveHere =
    canApproveDocuments(user) &&
    !isUploaderOrSubmitter &&
    ['DRAFT', 'PENDING_APPROVAL'].includes(doc.approval_status);

  const accessLogs = historyRes?.data?.access_logs || [];
  const auditLogs = historyRes?.data?.audit_logs || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
          <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h4 className="fw-bold mb-0">
              <FileFormatIcon extension={doc.file_extension} />
              {lang === 'en' ? doc.title_en : doc.title_bn}
            </h4>
            <DocumentApprovalBadge status={doc.approval_status} />
            <DocumentClassificationBadge classification={doc.classification} />
            <DocumentScanBadge scanStatus={doc.scan_status} />
          </div>
          <div className="text-muted small">
            {doc.document_no}
            {doc.reference_no ? ` • Ref: ${doc.reference_no}` : ''}
            {` • v${doc.current_version_number} (${formatFileSize(doc.file_size_bytes)})`}
          </div>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <Link to="/documents" className="btn btn-outline-secondary btn-sm">
            <i className="bi bi-arrow-left me-1"></i>
            {t('তালিকা', 'Registry')}
          </Link>
          {doc.is_previewable && (
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => setPreviewState({ doc })}>
              <i className="bi bi-eye me-1"></i>
              {t('প্রিভিউ', 'Preview')}
            </button>
          )}
          <button type="button" className="btn btn-primary btn-sm" onClick={() => handleDownload()}>
            <i className="bi bi-download me-1"></i>
            {t('ডাউনলোড', 'Download')}
          </button>
          {canUploadDocuments(user) && doc.approval_status !== 'ARCHIVED' && (
            <Link to={`/documents/${doc.id}/edit`} className="btn btn-outline-primary btn-sm">
              <i className="bi bi-pencil me-1"></i>
              {t('সম্পাদনা', 'Edit')}
            </Link>
          )}
          {canDeleteDocuments(user) && (
            <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => setShowDeleteConfirm(true)}>
              <i className="bi bi-trash me-1"></i>
              {t('মুছুন', 'Delete')}
            </button>
          )}
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {/* Navigation Tabs */}
      <ul className="nav nav-tabs mb-3">
        <li className="nav-item">
          <button type="button" className={`nav-link ${activeTab === 'overview' ? 'active' : ''}`} onClick={() => setActiveTab('overview')}>
            <i className="bi bi-file-earmark-text me-1"></i>
            {t('বিস্তারিত ও অনুমোদন', 'Overview & Governance')}
          </button>
        </li>
        <li className="nav-item">
          <button type="button" className={`nav-link ${activeTab === 'versions' ? 'active' : ''}`} onClick={() => setActiveTab('versions')}>
            <i className="bi bi-layers me-1"></i>
            {t('সংস্করণ ইতিহাস', 'Versions')} ({(doc.versions || []).length})
          </button>
        </li>
        <li className="nav-item">
          <button type="button" className={`nav-link ${activeTab === 'history' ? 'active' : ''}`} onClick={() => setActiveTab('history')}>
            <i className="bi bi-clock-history me-1"></i>
            {t('প্রবেশ ও পরিবর্তনের ইতিহাস', 'Access & Audit History')}
          </button>
        </li>
      </ul>

      {activeTab === 'overview' && (
        <div className="row g-3">
          <div className="col-lg-8">
            <div className="card shadow-sm border-0 mb-3">
              <div className="card-header bg-white fw-bold">{t('নথির বিবরণ ও মেটাডাটা', 'Document Metadata')}</div>
              <div className="card-body small">
                <div className="row g-2">
                  <div className="col-md-6"><strong>{t('শিরোনাম (বাংলা)', 'Title (BN)')}:</strong> {doc.title_bn}</div>
                  <div className="col-md-6"><strong>{t('শিরোনাম (ইংরেজি)', 'Title (EN)')}:</strong> {doc.title_en}</div>
                  <div className="col-md-6"><strong>{t('ক্যাটাগরি', 'Category')}:</strong> {lang === 'en' ? doc.category?.name_en : doc.category?.name_bn}</div>
                  <div className="col-md-6"><strong>{t('ধরন', 'Type')}:</strong> <DocumentTypeLabel type={doc.document_type} /></div>
                  <div className="col-md-6"><strong>{t('ইস্যুকারী সংস্থা', 'Issuing Org')}:</strong> {doc.issuing_organization || '-'}</div>
                  <div className="col-md-6"><strong>{t('প্রশাসনিক এলাকা', 'Jurisdiction')}:</strong> {lang === 'en' ? doc.administrative_unit?.name_en : doc.administrative_unit?.name_bn}</div>
                  <div className="col-md-6"><strong>{t('মালিক', 'Owner')}:</strong> {lang === 'en' ? (doc.owner?.full_name_en || '-') : (doc.owner?.full_name_bn || doc.owner?.full_name_en || '-')}</div>
                  <div className="col-md-6"><strong>{t('আপলোডকারী', 'Uploaded By')}:</strong> {lang === 'en' ? (doc.uploader?.full_name_en || '-') : (doc.uploader?.full_name_bn || doc.uploader?.full_name_en || '-')}</div>
                  <div className="col-md-6"><strong>{t('কার্যকর তারিখ', 'Effective Date')}:</strong> {doc.effective_date || '-'}</div>
                  <div className="col-md-6">
                    <strong>{t('মেয়াদ উত্তীর্ণ', 'Expires At')}:</strong>{' '}
                    {doc.expires_at ? new Date(doc.expires_at).toLocaleDateString() : t('প্রযোজ্য নয়', 'N/A')}
                  </div>
                  <div className="col-12"><strong>{t('বিবরণ (বাংলা)', 'Description (BN)')}:</strong> {doc.description_bn || '-'}</div>
                  <div className="col-12"><strong>{t('বিবরণ (ইংরেজি)', 'Description (EN)')}:</strong> {doc.description_en || '-'}</div>
                  <div className="col-12">
                    <strong>{t('ট্যাগসমূহ', 'Tags')}:</strong>{' '}
                    {(doc.tags || []).length === 0 ? '-' : doc.tags.map((tag) => (
                      <span key={tag} className="badge bg-light text-dark border me-1">#{tag}</span>
                    ))}
                  </div>
                </div>
              </div>
            </div>

            {entitySummary && (
              <div className="card shadow-sm border-0 mb-3">
                <div className="card-header bg-white fw-bold">{t('সংযুক্ত মডিউল রেকর্ড', 'Linked IDMS Record')}</div>
                <div className="card-body small d-flex justify-content-between align-items-center">
                  <div>
                    <div className="fw-bold">{lang === 'en' ? entitySummary.label_en : entitySummary.label_bn}</div>
                    <span className="text-muted">{entitySummary.module_code} • {entitySummary.entity_type}</span>
                  </div>
                  <Link to={entitySummary.route} className="btn btn-sm btn-outline-primary">
                    {t('রেকর্ড দেখুন', 'Open Record')}
                  </Link>
                </div>
              </div>
            )}

            <div className="card shadow-sm border-0">
              <div className="card-header bg-white fw-bold">{t('ফাইল অখণ্ডতা ও প্রাইভেট স্টোরেজ নিরাপত্তা', 'Cryptographic Integrity & Security')}</div>
              <div className="card-body small">
                <div className="mb-2"><strong>{t('মূল ফাইল নাম', 'Original Filename')}:</strong> {doc.original_filename}</div>
                <div className="mb-2"><strong>MIME Type:</strong> <code>{doc.mime_type}</code></div>
                <div className="mb-2"><strong>SHA-256 Checksum:</strong> <code className="text-break">{doc.file_hash_sha256}</code></div>
                <div className="mb-3">
                  <strong>{t('ভাইরাস স্ক্যান', 'Antivirus Scan')}:</strong> <DocumentScanBadge scanStatus={doc.scan_status} />{' '}
                  <span className="text-muted ms-2">{doc.scan_engine}</span>
                </div>

                <div className="d-flex flex-wrap gap-2">
                  {canVerifyDocumentIntegrity(user) && (
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-success"
                      onClick={() => integrityMutation.mutate()}
                      disabled={integrityMutation.isPending}
                    >
                      <i className="bi bi-shield-check me-1"></i>
                      {integrityMutation.isPending ? t('যাচাই হচ্ছে...', 'Verifying...') : t('SHA-256 অখণ্ডতা যাচাই করুন', 'Verify SHA-256 Integrity')}
                    </button>
                  )}
                  <button
                    type="button"
                    className="btn btn-sm btn-outline-info"
                    onClick={() => signedUrlMutation.mutate()}
                    disabled={signedUrlMutation.isPending}
                  >
                    <i className="bi bi-link-45deg me-1"></i>
                    {t('স্বল্পমেয়াদী স্বাক্ষরিত লিংক তৈরি করুন (১৫ মি.)', 'Generate Signed Stream Link (15m)')}
                  </button>
                </div>

                {integrityReport && (
                  <div className={`alert alert-${integrityReport.checksum_verified ? 'success' : 'danger'} mt-3 mb-0 py-2`}>
                    <strong>{integrityReport.checksum_verified ? t('অখণ্ডতা নিশ্চিত', 'Integrity Verified') : t('অখণ্ডতা ব্যর্থ', 'Checksum Mismatch')}:</strong>{' '}
                    <code>{integrityReport.actual_sha256}</code>
                  </div>
                )}

                {signedLinkData && (
                  <div className="alert alert-info mt-3 mb-0 py-2">
                    <div className="fw-semibold">{t('স্বল্পমেয়াদী অনুমোদিত স্ট্রিমিং লিংক', 'Authorized Temporary Signed URL')}:</div>
                    <code className="d-block text-break small my-1">{signedLinkData.url}</code>
                    <div className="text-muted">{t('মেয়াদ শেষ', 'Expires')}: {new Date(signedLinkData.expires_at).toLocaleString()}</div>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="col-lg-4">
            <div className="card shadow-sm border-0">
              <div className="card-header bg-white fw-bold">{t('নথি অনুমোদন ও কর্মপ্রবাহ', 'Document Approval Workflow')}</div>
              <div className="card-body small">
                <div className="mb-2"><strong>{t('বর্তমান অবস্থা', 'Current Status')}:</strong> <DocumentApprovalBadge status={doc.approval_status} /></div>
                {doc.approved_by && (
                  <div className="mb-2 text-success">
                    <strong>{t('অনুমোদনকারী', 'Approved By')}:</strong> {doc.approver?.full_name_en} ({new Date(doc.approved_at).toLocaleString()})
                    {doc.approval_notes && <div className="text-muted">{doc.approval_notes}</div>}
                  </div>
                )}
                {doc.rejected_by && (
                  <div className="mb-2 text-danger">
                    <strong>{t('প্রত্যাখ্যানকারী', 'Rejected By')}:</strong> {doc.rejecter?.full_name_en}
                    <div>{doc.rejection_reason}</div>
                  </div>
                )}

                <hr />

                {canUploadDocuments(user) && ['DRAFT', 'REJECTED'].includes(doc.approval_status) && (
                  <button
                    type="button"
                    className="btn btn-primary btn-sm w-100 mb-2"
                    onClick={() => approvalMutation.mutate({ action: 'submit' })}
                    disabled={approvalMutation.isPending}
                  >
                    {t('অনুমোদনের জন্য দাখিল করুন', 'Submit for Approval')}
                  </button>
                )}

                {canApproveHere && (
                  <>
                    <div className="mb-2">
                      <label className="form-label fw-semibold">{t('মন্তব্য / কারণ', 'Notes / Reason')}</label>
                      <textarea
                        className="form-control form-control-sm"
                        rows={2}
                        value={approvalNotes}
                        onChange={(e) => setApprovalNotes(e.target.value)}
                      />
                    </div>
                    <div className="d-flex gap-2 mb-2">
                      <button
                        type="button"
                        className="btn btn-success btn-sm flex-grow-1"
                        onClick={() => approvalMutation.mutate({ action: 'approve' })}
                        disabled={approvalMutation.isPending}
                      >
                        {t('অনুমোদন করুন', 'Approve')}
                      </button>
                      <button
                        type="button"
                        className="btn btn-danger btn-sm flex-grow-1"
                        onClick={() => approvalMutation.mutate({ action: 'reject' })}
                        disabled={approvalMutation.isPending || !approvalNotes.trim()}
                      >
                        {t('প্রত্যাখ্যান', 'Reject')}
                      </button>
                    </div>
                  </>
                )}

                {isUploaderOrSubmitter && ['DRAFT', 'PENDING_APPROVAL'].includes(doc.approval_status) && canApproveDocuments(user) && (
                  <div className="alert alert-warning py-2 mb-2">
                    {t('৪-আই নীতি: আপলোডকারী নিজের নথি অনুমোদন করতে পারবেন না।', '4-Eye SoD: Uploader cannot approve their own document.')}
                  </div>
                )}

                {canUploadDocuments(user) && doc.approval_status !== 'ARCHIVED' && (
                  <button
                    type="button"
                    className="btn btn-outline-dark btn-sm w-100"
                    onClick={() => approvalMutation.mutate({ action: 'archive' })}
                    disabled={approvalMutation.isPending}
                  >
                    {t('আর্কাইভ করুন', 'Archive Document')}
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {activeTab === 'versions' && (
        <div className="row g-3">
          <div className="col-lg-8">
            <div className="card shadow-sm border-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0 small">
                  <thead className="table-light">
                    <tr>
                      <th>{t('সংস্করণ', 'Version')}</th>
                      <th>{t('ফাইল ও নোট', 'File & Change Notes')}</th>
                      <th>SHA-256</th>
                      <th>{t('আপলোডকারী', 'Uploaded By')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(doc.versions || []).map((ver) => (
                      <tr key={ver.id}>
                        <td>
                          <span className="fw-bold">#{ver.version_number} ({ver.version_label})</span>
                          {ver.is_current && <span className="badge bg-success ms-1">{t('সক্রিয়', 'Active')}</span>}
                        </td>
                        <td>
                          <div>{ver.original_filename} ({formatFileSize(ver.file_size_bytes)})</div>
                          <div className="text-muted">{ver.change_notes || '-'}</div>
                        </td>
                        <td><code className="small">{ver.file_hash_sha256?.slice(0, 14)}...</code></td>
                        <td>
                          <div>{ver.uploader?.full_name_en || '-'}</div>
                          <div className="text-muted">{new Date(ver.created_at).toLocaleString()}</div>
                        </td>
                        <td className="text-end">
                          <div className="btn-group btn-group-sm">
                            <button
                              type="button"
                              className="btn btn-outline-primary"
                              onClick={() => handleDownload(ver.id, ver.original_filename)}
                            >
                              <i className="bi bi-download"></i>
                            </button>
                            {!ver.is_current && canUploadDocuments(user) && (
                              <button
                                type="button"
                                className="btn btn-outline-warning"
                                onClick={() => restoreMutation.mutate(ver.id)}
                                disabled={restoreMutation.isPending}
                                title={t('পুনরুদ্ধার', 'Restore')}
                              >
                                <i className="bi bi-arrow-counterclockwise"></i>
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          {canUploadDocuments(user) && doc.approval_status !== 'ARCHIVED' && (
            <div className="col-lg-4">
              <div className="card shadow-sm border-0">
                <div className="card-header bg-white fw-bold">{t('নতুন সংস্করণ আপলোড', 'Upload New Version')}</div>
                <div className="card-body small">
                  <form
                    onSubmit={(e) => {
                      e.preventDefault();
                      versionMutation.mutate();
                    }}
                  >
                    <div className="mb-2">
                      <label className="form-label fw-semibold">{t('ফাইল', 'File')} *</label>
                      <input
                        type="file"
                        className="form-control form-control-sm"
                        required
                        onChange={(e) => setVersionFile(e.target.files?.[0] || null)}
                      />
                    </div>
                    <div className="mb-2">
                      <label className="form-label fw-semibold">{t('সংস্করণ লেবেল', 'Version Label')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        placeholder={`v${doc.current_version_number + 1}.0`}
                        value={versionLabel}
                        onChange={(e) => setVersionLabel(e.target.value)}
                      />
                    </div>
                    <div className="mb-3">
                      <label className="form-label fw-semibold">{t('পরিবর্তনের নোট', 'Change Notes')}</label>
                      <textarea
                        className="form-control form-control-sm"
                        rows={2}
                        value={versionNotes}
                        onChange={(e) => setVersionNotes(e.target.value)}
                      />
                    </div>
                    <button type="submit" className="btn btn-primary btn-sm w-100" disabled={versionMutation.isPending}>
                      {versionMutation.isPending ? t('আপলোড হচ্ছে...', 'Uploading...') : t('সংস্করণ আপলোড করুন', 'Upload Version')}
                    </button>
                  </form>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {activeTab === 'history' && (
        <div className="row g-3">
          <div className="col-lg-6">
            <div className="card shadow-sm border-0">
              <div className="card-header bg-white fw-bold">{t('নথি প্রবেশ ও ডাউনলোড লগ', 'Document Access History')}</div>
              <div className="list-group list-group-flush small">
                {accessLogs.length === 0 ? (
                  <div className="p-3 text-muted">{t('কোনো লগ নেই।', 'No access logs recorded.')}</div>
                ) : (
                  accessLogs.map((log) => (
                    <div key={log.id} className="list-group-item">
                      <div className="d-flex justify-content-between">
                        <span className="badge bg-light text-dark border">{log.action}</span>
                        <span className="text-muted">{new Date(log.created_at).toLocaleString()}</span>
                      </div>
                      <div className="mt-1">
                        {log.user?.full_name_en || 'System'} ({log.user?.role_code || '-'}) • IP: {log.ip_address || '-'}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>

          <div className="col-lg-6">
            <div className="card shadow-sm border-0">
              <div className="card-header bg-white fw-bold">{t('সিস্টেম অডিট ট্রেইল', 'Immutable Audit Trail')}</div>
              <div className="list-group list-group-flush small">
                {auditLogs.length === 0 ? (
                  <div className="p-3 text-muted">{t('কোনো অডিট লগ নেই।', 'No audit logs.')}</div>
                ) : (
                  auditLogs.map((a) => (
                    <div key={a.id} className="list-group-item">
                      <div className="d-flex justify-content-between">
                        <span className="badge bg-secondary">{a.action_name}</span>
                        <span className="text-muted">{new Date(a.created_at).toLocaleString()}</span>
                      </div>
                      <div className="mt-1">{a.user?.full_name_en || 'System'}</div>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {previewState && (
        <DocumentPreviewModal doc={previewState.doc} onClose={() => setPreviewState(null)} />
      )}

      <ConfirmDialog
        isOpen={showDeleteConfirm}
        title={t('নথি মুছে ফেলার নিশ্চিতকরণ', 'Confirm Document Deletion')}
        titleEn="Confirm Document Deletion"
        message={t('আপনি কি নিশ্চিতভাবে এই নথিটি আর্কাইভ/মুছে ফেলতে চান?', 'Are you sure you want to delete this document?')}
        confirmText={t('মুছে ফেলুন', 'Delete')}
        cancelText={t('বাতিল', 'Cancel')}
        confirmVariant="danger"
        isLoading={deleteMutation.isPending}
        onConfirm={() => deleteMutation.mutate()}
        onCancel={() => setShowDeleteConfirm(false)}
      />
    </div>
  );
};

export default DocumentDetailPage;
