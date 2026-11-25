import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { reliefApi } from '../../../api/reliefApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import EmptyState from '../../../components/common/EmptyState';
import AlertBanner from '../../../components/common/AlertBanner';
import { canCreateRequisition, REQUISITION_STATUS_META } from '../utils/reliefPermissions';
import { PriorityBadge, RequisitionStatusBadge } from '../components/ReliefStatusBadges';

export const ReliefRequisitionListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const [searchParams] = useSearchParams();

  const [search, setSearch] = useState('');
  const [status, setStatus] = useState(searchParams.get('status') || '');
  const [priority, setPriority] = useState('');
  const [page, setPage] = useState(1);

  const { data, isLoading, error } = useQuery({
    queryKey: ['relief', 'requisitions', { search, status, priority, page }],
    queryFn: () =>
      reliefApi.getRequisitions({
        search: search || undefined,
        status: status || undefined,
        priority: priority || undefined,
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
            <i className="bi bi-file-earmark-medical text-success me-2"></i>
            {t('ত্রাণ চাহিদাপত্র তালিকা', 'Relief Requisitions')}
          </h4>
          <p className="text-muted small mb-0">
            {t('মাঠ পর্যায় থেকে প্রাপ্ত ত্রাণ চাহিদা ও অনুমোদন অবস্থা', 'Field-to-ministry relief requisition queue & approval status')}
          </p>
        </div>

        <div className="d-flex gap-2">
          <Link to="/relief/dashboard" className="btn btn-sm btn-outline-secondary">
            <i className="bi bi-speedometer2 me-1"></i>
            {t('ড্যাশবোর্ড', 'Dashboard')}
          </Link>
          {canCreateRequisition(user) && (
            <Link to="/relief/requisitions/new" className="btn btn-sm btn-success">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন চাহিদাপত্র', 'Create Requisition')}
            </Link>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body">
          <div className="row g-2">
            <div className="col-md-4">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('চাহিদাপত্র নং বা সংস্থা অনুসন্ধান...', 'Search requisition no or organization...')}
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
                value={status}
                onChange={(e) => {
                  setStatus(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
                {Object.entries(REQUISITION_STATUS_META).map(([key, meta]) => (
                  <option key={key} value={key}>{t(meta.bn, meta.en)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-3">
              <select
                className="form-select form-select-sm"
                value={priority}
                onChange={(e) => {
                  setPriority(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সকল অগ্রাধিকার', 'All Priorities')}</option>
                <option value="CRITICAL">{t('অতি জরুরি', 'Critical')}</option>
                <option value="HIGH">{t('উচ্চ', 'High')}</option>
                <option value="MEDIUM">{t('মাঝারি', 'Medium')}</option>
                <option value="LOW">{t('সাধারণ', 'Low')}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {error && <AlertBanner variant="danger" message={error?.message || t('তালিকা লোড করতে ব্যর্থ', 'Failed to load requisitions')} />}
      {isLoading ? (
        <Loader />
      ) : rows.length === 0 ? (
        <EmptyState
          title={t('কোন চাহিদাপত্র পাওয়া যায়নি', 'No Requisitions Found')}
          titleEn="No relief requisitions match your filters"
          action={
            canCreateRequisition(user) && (
              <Link to="/relief/requisitions/new" className="btn btn-success btn-sm">
                {t('প্রথম চাহিদাপত্র তৈরি করুন', 'Create Requisition')}
              </Link>
            )
          }
        />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('চাহিদাপত্র নং', 'Requisition No')}</th>
                  <th>{t('দুর্যোগ', 'Disaster')}</th>
                  <th>{t('এলাকা', 'Jurisdiction')}</th>
                  <th>{t('উপকারভোগী', 'Beneficiaries')}</th>
                  <th>{t('অগ্রাধিকার', 'Priority')}</th>
                  <th>{t('অবস্থা', 'Status')}</th>
                  <th>{t('তারিখ', 'Request Date')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((req) => (
                  <tr key={req.id}>
                    <td className="fw-semibold">
                      <Link to={`/relief/requisitions/${req.id}`} className="text-decoration-none">
                        {req.requisition_no}
                      </Link>
                      {req.requesting_organization && (
                        <div className="small text-muted">{req.requesting_organization}</div>
                      )}
                    </td>
                    <td>{t(req.disaster_event?.name_bn || req.disaster_event?.name_en, req.disaster_event?.name_en)}</td>
                    <td>{t(req.administrative_unit?.name_bn || req.administrative_unit?.name_en, req.administrative_unit?.name_en)}</td>
                    <td>{req.estimated_beneficiaries}</td>
                    <td><PriorityBadge priority={req.priority} /></td>
                    <td><RequisitionStatusBadge status={req.status} /></td>
                    <td>{req.request_date}</td>
                    <td className="text-end">
                      <Link to={`/relief/requisitions/${req.id}`} className="btn btn-sm btn-outline-primary">
                        {t('বিস্তারিত', 'View')}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {pagination.last_page > 1 && (
            <div className="card-footer bg-white d-flex justify-content-between align-items-center">
              <small className="text-muted">
                {t(`মোট ${pagination.total}টি`, `Total ${pagination.total}`)}
              </small>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                >
                  {t('পূর্ববর্তী', 'Prev')}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={page >= pagination.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
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

export default ReliefRequisitionListPage;
