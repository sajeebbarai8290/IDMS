import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { responseApi } from '../../../api/responseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import EmptyState from '../../../components/common/EmptyState';
import AlertBanner from '../../../components/common/AlertBanner';
import { canCreateOperation, formatResponseApiError, OPERATION_STATUS_META } from '../utils/responsePermissions';
import { OperationStatusBadge } from '../components/ResponseStatusBadges';

export const ResponseOperationListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();

  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [priority, setPriority] = useState('');
  const [page, setPage] = useState(1);

  const { data, isLoading, error } = useQuery({
    queryKey: ['response', 'operations', { search, status, priority, page }],
    queryFn: () =>
      responseApi.getOperations({
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
            <i className="bi bi-shield-exclamation text-danger me-2"></i>
            {t('দুর্যোগ সাড়াদান অভিযান তালিকা', 'Response Operations')}
          </h4>
          <p className="text-muted small mb-0">
            {t('চলমান ও পরিকল্পিত সাড়াদান অভিযান, টাস্ক ও দল পরিচালনা', 'Coordinate emergency response operations, task progress & field teams')}
          </p>
        </div>

        <div className="d-flex gap-2">
          <Link to="/response/dashboard" className="btn btn-sm btn-outline-secondary">
            {t('ড্যাশবোর্ড', 'Dashboard')}
          </Link>
          {canCreateOperation(user) && (
            <Link to="/response/operations/new" className="btn btn-sm btn-danger">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন অভিযান', 'New Operation')}
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
                placeholder={t('অভিযান নং, শিরোনাম বা সংস্থা অনুসন্ধান...', 'Search operation no, title or agency...')}
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
                {Object.entries(OPERATION_STATUS_META).map(([k, m]) => (
                  <option key={k} value={k}>{t(m.bn, m.en)}</option>
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

      {error && <AlertBanner variant="danger" message={formatResponseApiError(error, t('তালিকা লোড ব্যর্থ', 'Failed to load operations'))} />}
      {isLoading ? (
        <Loader />
      ) : rows.length === 0 ? (
        <EmptyState title={t('কোন সাড়াদান অভিযান নেই', 'No Response Operations Found')} titleEn="No matching operations" />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('অভিযান নং ও শিরোনাম', 'Operation & Title')}</th>
                  <th>{t('দুর্যোগ ও এলাকা', 'Disaster & Area')}</th>
                  <th>{t('নেতৃত্বদানকারী সংস্থা', 'Lead Agency')}</th>
                  <th>{t('অবস্থা', 'Status')}</th>
                  <th>{t('দল / টাস্ক', 'Teams / Tasks')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((op) => (
                  <tr key={op.id}>
                    <td>
                      <Link to={`/response/operations/${op.id}`} className="fw-bold text-decoration-none">
                        {op.operation_no}
                      </Link>
                      <div className="small">{t(op.title_bn || op.title_en, op.title_en)}</div>
                    </td>
                    <td className="small">
                      <div>{t(op.disaster_event?.name_bn || op.disaster_event?.name_en, op.disaster_event?.name_en)}</div>
                      <span className="text-muted">{t(op.administrative_unit?.name_bn || op.administrative_unit?.name_en, op.administrative_unit?.name_en)}</span>
                    </td>
                    <td className="small">{op.lead_organization}</td>
                    <td><OperationStatusBadge status={op.status} /></td>
                    <td className="small">
                      <span className="badge bg-light text-dark border me-1">{op.teams_count ?? 0} {t('দল', 'Teams')}</span>
                      <span className="badge bg-light text-dark border">{op.tasks_count ?? 0} {t('কাজ', 'Tasks')}</span>
                    </td>
                    <td className="text-end">
                      <Link to={`/response/operations/${op.id}`} className="btn btn-sm btn-outline-primary">
                        {t('কমান্ড ভিউ', 'Command')}
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

export default ResponseOperationListPage;
