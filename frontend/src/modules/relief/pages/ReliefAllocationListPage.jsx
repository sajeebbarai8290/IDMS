import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { reliefApi } from '../../../api/reliefApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import EmptyState from '../../../components/common/EmptyState';
import AlertBanner from '../../../components/common/AlertBanner';
import { ALLOCATION_STATUS_META, canCreateAllocation } from '../utils/reliefPermissions';
import { AllocationStatusBadge } from '../components/ReliefStatusBadges';

export const ReliefAllocationListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();

  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);

  const { data, isLoading, error } = useQuery({
    queryKey: ['relief', 'allocations', { search, status, page }],
    queryFn: () =>
      reliefApi.getAllocations({
        search: search || undefined,
        status: status || undefined,
        page,
        per_page: 15,
      }),
  });

  const rows = data?.data || [];
  const pagination = data?.pagination || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-truck text-primary me-2"></i>
            {t('ত্রাণ বরাদ্দ আদেশ তালিকা', 'Relief Allocations')}
          </h4>
          <p className="text-muted small mb-0">
            {t('গুদাম থেকে কেন্দ্রভিত্তিক ত্রাণ বরাদ্দ, মজুদ সংরক্ষণ ও প্রেরণ', 'Official sanction orders, warehouse stock reservation & center dispatch')}
          </p>
        </div>

        <div className="d-flex gap-2">
          <Link to="/relief/dashboard" className="btn btn-sm btn-outline-secondary">
            {t('ড্যাশবোর্ড', 'Dashboard')}
          </Link>
          {canCreateAllocation(user) && (
            <Link to="/relief/allocations/new" className="btn btn-sm btn-primary">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন বরাদ্দ আদেশ', 'Create Allocation')}
            </Link>
          )}
        </div>
      </div>

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body">
          <div className="row g-2">
            <div className="col-md-5">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('বরাদ্দ আদেশ নং অনুসন্ধান...', 'Search allocation order no...')}
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
              />
            </div>
            <div className="col-md-4">
              <select
                className="form-select form-select-sm"
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
                {Object.entries(ALLOCATION_STATUS_META).map(([key, meta]) => (
                  <option key={key} value={key}>{t(meta.bn, meta.en)}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>

      {error && <AlertBanner variant="danger" message={error?.message || t('তালিকা লোড ব্যর্থ', 'Failed to load allocations')} />}
      {isLoading ? (
        <Loader />
      ) : rows.length === 0 ? (
        <EmptyState
          title={t('কোন বরাদ্দ আদেশ নেই', 'No Allocations Found')}
          titleEn="No relief allocation orders found"
        />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('আদেশ নং', 'Order No')}</th>
                  <th>{t('দুর্যোগ ও এলাকা', 'Disaster & Area')}</th>
                  <th>{t('উৎস গুদাম', 'Warehouse')}</th>
                  <th>{t('বিতরণ কেন্দ্র', 'Distribution Center')}</th>
                  <th>{t('অবস্থা', 'Status')}</th>
                  <th>{t('উপকারভোগী', 'Roster')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((a) => (
                  <tr key={a.id}>
                    <td className="fw-semibold">
                      <Link to={`/relief/allocations/${a.id}`} className="text-decoration-none">
                        {a.allocation_order_no}
                      </Link>
                      {a.requisition && (
                        <div className="small text-muted">{a.requisition.requisition_no}</div>
                      )}
                    </td>
                    <td>
                      <div>{t(a.disaster_event?.name_bn || a.disaster_event?.name_en, a.disaster_event?.name_en)}</div>
                      <small className="text-muted">
                        {t(a.administrative_unit?.name_bn || a.administrative_unit?.name_en, a.administrative_unit?.name_en)}
                      </small>
                    </td>
                    <td>{t(a.warehouse?.warehouse_name_bn || a.warehouse?.warehouse_name, a.warehouse?.warehouse_name)}</td>
                    <td>{a.distribution_point ? t(a.distribution_point.point_name_bn || a.distribution_point.point_name, a.distribution_point.point_name) : '—'}</td>
                    <td><AllocationStatusBadge status={a.status} /></td>
                    <td>{a.beneficiaries_count ?? 0}</td>
                    <td className="text-end">
                      <Link to={`/relief/allocations/${a.id}`} className="btn btn-sm btn-outline-primary">
                        {t('বিস্তারিত', 'Manage')}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {pagination.last_page > 1 && (
            <div className="card-footer bg-white d-flex justify-content-between align-items-center">
              <small className="text-muted">{t(`মোট ${pagination.total}টি`, `Total ${pagination.total}`)}</small>
              <div className="btn-group btn-group-sm">
                <button type="button" className="btn btn-outline-secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  {t('পূর্ববর্তী', 'Prev')}
                </button>
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

export default ReliefAllocationListPage;
