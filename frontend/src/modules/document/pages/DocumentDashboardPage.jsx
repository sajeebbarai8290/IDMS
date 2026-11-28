import React from 'react';
import { Link } from 'react-router-dom';
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
  FileFormatIcon,
} from '../components/DocumentBadges';
import {
  canManageDocumentCategories,
  canUploadDocuments,
  formatDocumentApiError,
  formatFileSize,
} from '../utils/documentPermissions';

export const DocumentDashboardPage = () => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['documents', 'dashboard'],
    queryFn: () => documentApi.getDashboard(),
  });

  if (isLoading) {
    return <Loader message="নথি ব্যবস্থাপনা ড্যাশবোর্ড লোড হচ্ছে..." messageEn="Loading Document Management dashboard..." />;
  }

  if (error) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner
          variant="danger"
          message={formatDocumentApiError(error, t('ড্যাশবোর্ড লোড করতে ব্যর্থ।', 'Failed to load document dashboard.'))}
        />
      </div>
    );
  }

  const data = res?.data || {};
  const kpis = data.kpis || {};
  const byCategory = data.by_category || [];
  const byClassification = data.by_classification || [];
  const recentDocuments = data.recent_documents || [];
  const pendingApprovalDocs = data.pending_approval_documents || [];
  const expiringDocuments = data.expiring_documents || [];
  const storageHealth = data.storage_health || {};
  const scannerHealth = data.scanner_health || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-folder2-open text-primary me-2"></i>
            {t('নথিপত্র ব্যবস্থাপনা ড্যাশবোর্ড (M14)', 'Document Management Dashboard (M14)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'কেন্দ্রীয় ও নিরাপদ ই-ডকস ভাণ্ডার, সংস্করণ নিয়ন্ত্রণ, অনুমোদন এবং মেয়াদোত্তীর্ণ নজরদারি',
              'Centralized private e-Docs vault, version control, approvals, and expiry monitoring'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <Link to="/documents" className="btn btn-outline-primary btn-sm">
            <i className="bi bi-files me-1"></i>
            {t('সকল নথিপত্র', 'Document Registry')}
          </Link>
          {canUploadDocuments(user) && (
            <Link to="/documents/upload" className="btn btn-primary btn-sm">
              <i className="bi bi-cloud-upload-fill me-1"></i>
              {t('নতুন নথি আপলোড', 'Upload Document')}
            </Link>
          )}
          {canManageDocumentCategories(user) && (
            <Link to="/documents/categories" className="btn btn-outline-secondary btn-sm">
              <i className="bi bi-tags-fill me-1"></i>
              {t('ক্যাটাগরি', 'Categories')}
            </Link>
          )}
        </div>
      </div>

      {/* Primary KPI Cards */}
      <div className="row g-3 mb-3">
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('মোট নথিপত্র', 'Total Documents')}</div>
              <div className="fs-3 fw-bold text-primary">{kpis.total_documents ?? 0}</div>
              <div className="small text-muted">{t('অনুমোদিত', 'Approved')}: {kpis.approved_documents ?? 0}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('অনুমোদন অপেক্ষমাণ', 'Pending Approvals')}</div>
              <div className="fs-3 fw-bold text-warning">{kpis.pending_approvals ?? 0}</div>
              <div className="small text-muted">{t('খসড়া', 'Drafts')}: {kpis.draft_documents ?? 0}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('শীঘ্রই মেয়াদোত্তীর্ণ', 'Expiring (30d)')}</div>
              <div className="fs-3 fw-bold text-info">{kpis.expiring_soon_count ?? 0}</div>
              <div className="small text-muted">{t('মেয়াদ উত্তীর্ণ', 'Expired')}: {kpis.expired_count ?? 0}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('গোপনীয় নথি', 'Confidential')}</div>
              <div className="fs-3 fw-bold text-danger">{kpis.confidential_count ?? 0}</div>
              <div className="small text-muted">{t('প্রাইভেট স্টোরেজ', 'Private vault only')}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('ব্যবহৃত স্টোরেজ', 'Storage Used')}</div>
              <div className="fs-4 fw-bold text-success">{formatFileSize(kpis.total_storage_bytes)}</div>
              <div className="small text-muted">{storageHealth.driver || 'laravel_private'}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('সুরক্ষা ও স্ক্যানার', 'Security Guard')}</div>
              <div className="fw-bold text-success mt-1">
                <i className="bi bi-shield-fill-check me-1"></i>
                {scannerHealth.ready ? t('সক্রিয়', 'Active') : t('নিষ্ক্রিয়', 'Inactive')}
              </div>
              <div className="small text-muted">{scannerHealth.engine || 'av-hook'}</div>
            </div>
          </div>
        </div>
      </div>

      {/* Category & Classification Breakdown */}
      <div className="row g-3 mb-3">
        <div className="col-lg-8">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              <i className="bi bi-grid-fill me-2 text-primary"></i>
              {t('ক্যাটাগরি অনুযায়ী নথিপত্র', 'Documents by Category')}
            </div>
            <div className="card-body p-0">
              {byCategory.length === 0 ? (
                <div className="p-3"><EmptyState title={t('কোনো নথি নেই', 'No documents registered yet')} /></div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0 small">
                    <thead className="table-light">
                      <tr>
                        <th>{t('ক্যাটাগরি', 'Category')}</th>
                        <th className="text-end">{t('মোট সংখ্যা', 'Count')}</th>
                        <th className="text-end">{t('মোট আকার', 'Size')}</th>
                        <th className="text-end">{t('ফিল্টার', 'View')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {byCategory.map((row) => (
                        <tr key={row.category_id}>
                          <td className="fw-semibold">
                            {lang === 'en' ? row.name_en : row.name_bn}
                            <div className="text-muted" style={{ fontSize: '0.75rem' }}>{row.code}</div>
                          </td>
                          <td className="text-end fw-bold">{row.total}</td>
                          <td className="text-end">{formatFileSize(row.total_bytes)}</td>
                          <td className="text-end">
                            <Link to={`/documents?category_id=${row.category_id}`} className="btn btn-sm btn-outline-primary py-0 px-2">
                              {t('দেখুন', 'Open')}
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              <i className="bi bi-shield-lock-fill me-2 text-warning"></i>
              {t('গোপনীয়তা শ্রেণিবিন্যাস', 'By Classification')}
            </div>
            <div className="card-body">
              {byClassification.length === 0 ? (
                <div className="text-muted small">{t('তথ্য নেই।', 'No classification data.')}</div>
              ) : (
                <div className="d-flex flex-column gap-2">
                  {byClassification.map((c) => (
                    <div key={c.classification} className="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                      <DocumentClassificationBadge classification={c.classification} />
                      <span className="fw-bold">{c.total}</span>
                    </div>
                  ))}
                </div>
              )}
              <hr />
              <div className="small text-muted">
                <i className="bi bi-lock-fill me-1 text-success"></i>
                {t(
                  'সমস্ত গোপনীয় নথি প্রাইভেট স্টোরেজে সংরক্ষিত এবং অনুমোদিত স্ট্রিমিং দ্বারা সুরক্ষিত।',
                  'Confidential files reside in private storage with zero public URLs.'
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Pending Approvals, Expiring & Recent Uploads */}
      <div className="row g-3">
        <div className="col-lg-4">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white d-flex justify-content-between align-items-center">
              <span className="fw-bold">
                <i className="bi bi-hourglass-split me-2 text-warning"></i>
                {t('অনুমোদন অপেক্ষমাণ', 'Pending Approvals')}
              </span>
              <Link to="/documents?approval_status=PENDING_APPROVAL" className="small text-decoration-none">
                {t('সব দেখুন', 'All')}
              </Link>
            </div>
            <div className="list-group list-group-flush small">
              {pendingApprovalDocs.length === 0 ? (
                <div className="p-3 text-muted">{t('অনুমোদন অপেক্ষমাণ কোনো নথি নেই।', 'No pending document approvals.')}</div>
              ) : (
                pendingApprovalDocs.map((d) => (
                  <Link key={d.id} to={`/documents/${d.id}`} className="list-group-item list-group-item-action">
                    <div className="d-flex justify-content-between">
                      <span className="fw-semibold text-truncate">{lang === 'en' ? d.title_en : d.title_bn}</span>
                      <DocumentApprovalBadge status={d.approval_status} />
                    </div>
                    <div className="text-muted">{d.document_no} • {lang === 'en' ? d.uploader?.full_name_en : (d.uploader?.full_name_bn || d.uploader?.full_name_en)}</div>
                  </Link>
                ))
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white d-flex justify-content-between align-items-center">
              <span className="fw-bold">
                <i className="bi bi-calendar-x me-2 text-danger"></i>
                {t('মেয়াদোত্তীর্ণ / আসন্ন মেয়াদ', 'Expiring Documents')}
              </span>
              <Link to="/documents?expiring_within_days=30" className="small text-decoration-none">
                {t('সব দেখুন', 'All')}
              </Link>
            </div>
            <div className="list-group list-group-flush small">
              {expiringDocuments.length === 0 ? (
                <div className="p-3 text-muted">{t('আসন্ন মেয়াদোত্তীর্ণ কোনো নথি নেই।', 'No expiring documents in next 30 days.')}</div>
              ) : (
                expiringDocuments.map((d) => (
                  <Link key={d.id} to={`/documents/${d.id}`} className="list-group-item list-group-item-action">
                    <div className="d-flex justify-content-between">
                      <span className="fw-semibold text-truncate">{lang === 'en' ? d.title_en : d.title_bn}</span>
                      <span className={`badge bg-${d.is_expired ? 'danger' : 'warning'}`}>
                        {d.expires_at ? new Date(d.expires_at).toLocaleDateString() : '-'}
                      </span>
                    </div>
                    <div className="text-muted">{d.document_no}</div>
                  </Link>
                ))
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white d-flex justify-content-between align-items-center">
              <span className="fw-bold">
                <i className="bi bi-clock-history me-2 text-primary"></i>
                {t('সাম্প্রতিক আপলোড', 'Recently Uploaded')}
              </span>
              <Link to="/documents" className="small text-decoration-none">
                {t('সব দেখুন', 'All')}
              </Link>
            </div>
            <div className="list-group list-group-flush small">
              {recentDocuments.length === 0 ? (
                <div className="p-3 text-muted">{t('সাম্প্রতিক কোনো আপলোড নেই।', 'No recent uploads.')}</div>
              ) : (
                recentDocuments.map((d) => (
                  <Link key={d.id} to={`/documents/${d.id}`} className="list-group-item list-group-item-action">
                    <div className="d-flex justify-content-between align-items-center">
                      <span className="fw-semibold text-truncate">
                        <FileFormatIcon extension={d.file_extension} />
                        {lang === 'en' ? d.title_en : d.title_bn}
                      </span>
                      <span className="text-muted ms-2">{formatFileSize(d.file_size_bytes)}</span>
                    </div>
                    <div className="text-muted">{d.document_no} • {lang === 'en' ? d.category?.name_en : (d.category?.name_bn || d.category?.name_en)}</div>
                  </Link>
                ))
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DocumentDashboardPage;
