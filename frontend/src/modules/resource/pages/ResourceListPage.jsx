import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { resourceApi } from '../../../api/resourceApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { ResourceStatusBadge } from '../components/ResourceStatusBadges';
import {
  canExportResources,
  canWriteResourceAssets,
  formatResourceApiError,
  RESOURCE_STATUS_META,
  triggerResourceBlobDownload,
} from '../utils/resourcePermissions';

export const ResourceListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const [searchParams] = useSearchParams();

  const [search, setSearch] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [status, setStatus] = useState(searchParams.get('status') || '');
  const [sortBy, setSortBy] = useState('created_at');
  const [page, setPage] = useState(1);
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['resource', 'lookups'],
    queryFn: () => resourceApi.getLookups(),
  });

  const { data: listRes, isLoading, error } = useQuery({
    queryKey: ['resource', 'assets', search, categoryId, status, sortBy, page],
    queryFn: () =>
      resourceApi.getAssets({
        page,
        per_page: 15,
        sort_by: sortBy,
        ...(search ? { search } : {}),
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(status ? { status } : {}),
      }),
  });

  const handleExport = async () => {
    try {
      const res = await resourceApi.exportCsv({
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(status ? { status } : {}),
      });
      triggerResourceBlobDownload(res.data, 'idms-resource-register.csv');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('এক্সপোর্ট ব্যর্থ', 'Export failed')) });
    }
  };

  const categories = lookupsRes?.data?.categories || [];
  const assets = listRes?.data || [];
  const pagination = listRes?.pagination || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-boxes text-primary me-2"></i>
            {t('সম্পদ রেজিস্টার (Resource Register)', 'Resource Register')}
          </h4>
          <p className="text-muted small mb-0">
            {t('সকল নিবন্ধিত যানবাহন, বোট, সরঞ্জাম ও বিশেষজ্ঞ সম্পদ', 'All registered vehicles, boats, equipment, and human resources')}
          </p>
        </div>

        <div className="d-flex gap-2">
          {canExportResources(user) && (
            <button type="button" className="btn btn-sm btn-outline-success" onClick={handleExport}>
              <i className="bi bi-download me-1"></i>{t('সিএসভি এক্সপোর্ট', 'Export CSV')}
            </button>
          )}
          {canWriteResourceAssets(user) && (
            <Link to="/resource/assets/new" className="btn btn-sm btn-primary">
              <i className="bi bi-plus-circle me-1"></i>{t('নতুন সম্পদ নিবন্ধন', 'Register Resource')}
            </Link>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatResourceApiError(error, t('তালিকা লোড ব্যর্থ', 'Failed to load resources'))} />}

      {/* Search & Filters */}
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body">
          <div className="row g-2">
            <div className="col-md-4">
              <input
                className="form-control form-control-sm"
                placeholder={t('কোড, নাম, সংস্থা বা অবস্থান দিয়ে খুঁজুন...', 'Search by code, name, org, or location...')}
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
              />
            </div>
            <div className="col-md-3">
              <select
                className="form-select form-select-sm"
                value={categoryId}
                onChange={(e) => {
                  setCategoryId(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সকল শ্রেণি', 'All Categories')}</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-3">
              <select
                className="form-select form-select-sm"
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সকল প্রাপ্যতা অবস্থা', 'All Availability Statuses')}</option>
                {['AVAILABLE', 'ASSIGNED', 'IN_USE', 'UNDER_MAINTENANCE', 'DAMAGED', 'RETIRED'].map((s) => (
                  <option key={s} value={s}>{t(RESOURCE_STATUS_META[s]?.bn || s, RESOURCE_STATUS_META[s]?.en || s)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select className="form-select form-select-sm" value={sortBy} onChange={(e) => setSortBy(e.target.value)}>
                <option value="created_at">{t('সর্বশেষ নিবন্ধন', 'Newest First')}</option>
                <option value="asset_code">{t('কোড অনুযায়ী', 'Asset Code')}</option>
                <option value="name_en">{t('নাম অনুযায়ী', 'Name')}</option>
                <option value="next_maintenance_due_at">{t('রক্ষণাবেক্ষণ তারিখ', 'Maintenance Due')}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader />
      ) : assets.length === 0 ? (
        <EmptyState
          title={t('কোন সম্পদ পাওয়া যায়নি', 'No resources found')}
          description={t('অনুসন্ধান ফিল্টার পরিবর্তন করুন বা নতুন সম্পদ নিবন্ধন করুন।', 'Adjust your filters or register a new disaster management resource.')}
        />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0 small">
              <thead className="table-light">
                <tr>
                  <th>{t('সম্পদ কোড', 'Code')}</th>
                  <th>{t('নাম ও ধরন', 'Name & Type')}</th>
                  <th>{t('শ্রেণি', 'Category')}</th>
                  <th>{t('অধিক্ষেত্র ও বর্তমান অবস্থান', 'Unit & Current Location')}</th>
                  <th>{t('মালিকানাধীন সংস্থা', 'Owning Org')}</th>
                  <th>{t('অবস্থা', 'Status')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {assets.map((a) => (
                  <tr key={a.id}>
                    <td className="fw-bold font-monospace">{a.asset_code}</td>
                    <td>
                      <Link to={`/resource/assets/${a.id}`} className="fw-semibold text-decoration-none">
                        {t(a.name_bn, a.name_en)}
                      </Link>
                      <div className="text-muted">{a.resource_type}</div>
                    </td>
                    <td>{a.category ? t(a.category.name_bn, a.category.name_en) : '—'}</td>
                    <td>
                      <div>{a.administrative_unit ? t(a.administrative_unit.name_bn, a.administrative_unit.name_en) : '—'}</div>
                      <small className="text-muted">{a.current_location || '—'}</small>
                    </td>
                    <td>{a.owning_organization || '—'}</td>
                    <td><ResourceStatusBadge status={a.status} /></td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <Link to={`/resource/assets/${a.id}`} className="btn btn-outline-primary">
                          {t('বিস্তারিত', 'View')}
                        </Link>
                        {canWriteResourceAssets(user) && (
                          <Link to={`/resource/assets/${a.id}/edit`} className="btn btn-outline-secondary">
                            {t('সম্পাদনা', 'Edit')}
                          </Link>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {pagination.last_page > 1 && (
            <div className="card-footer bg-white d-flex justify-content-between align-items-center small">
              <span>{t(`মোট ${pagination.total}টি সম্পদ`, `Total ${pagination.total} resources`)}</span>
              <div className="btn-group btn-group-sm">
                <button type="button" className="btn btn-outline-secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  {t('পূর্ববর্তী', 'Prev')}
                </button>
                <span className="btn btn-light disabled">{page} / {pagination.last_page}</span>
                <button type="button" className="btn btn-outline-secondary" disabled={page >= pagination.last_page} onClick={() => setPage((p) => p + 1)}>
                  {t('পরবর্তী', 'Next')}
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default ResourceListPage;
