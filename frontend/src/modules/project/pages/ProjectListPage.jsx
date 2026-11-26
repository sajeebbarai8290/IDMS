import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { projectApi } from '../../../api/projectApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { ProjectStatusBadge } from '../components/ProjectStatusBadges';
import {
  canCreateProject,
  canExportProjects,
  formatBdt,
  formatProjectApiError,
  PROJECT_STATUS_META,
  triggerBlobDownload,
} from '../utils/projectPermissions';

export const ProjectListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const [searchParams] = useSearchParams();

  const [search, setSearch] = useState('');
  const [status, setStatus] = useState(() => searchParams.get('status') || '');
  const [categoryId, setCategoryId] = useState('');
  const [isDelayed, setIsDelayed] = useState(() => searchParams.get('is_delayed') || '');
  const [page, setPage] = useState(1);

  const { data: lookupsRes } = useQuery({
    queryKey: ['project', 'lookups'],
    queryFn: () => projectApi.getLookups(),
  });

  const { data: listRes, isLoading, error } = useQuery({
    queryKey: ['project', 'schemes', search, status, categoryId, isDelayed, page],
    queryFn: () =>
      projectApi.getProjects({
        page,
        per_page: 12,
        ...(search ? { search } : {}),
        ...(status ? { status } : {}),
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(isDelayed !== '' ? { is_delayed: isDelayed } : {}),
      }),
  });

  const handleExport = async () => {
    const res = await projectApi.exportCsv({
      ...(status ? { status } : {}),
      ...(categoryId ? { category_id: categoryId } : {}),
    });
    triggerBlobDownload(res.data, 'idms-projects.csv');
  };

  const categories = lookupsRes?.data?.categories || [];
  const statuses = lookupsRes?.data?.lifecycle_statuses || [];
  const projects = listRes?.data || [];
  const pagination = listRes?.pagination || { current_page: 1, last_page: 1, total: 0 };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-list-check text-primary me-2"></i>
            {t('প্রকল্প নিবন্ধন তালিকা', 'Disaster Management Projects Registry')}
          </h4>
          <p className="text-muted small mb-0">
            {t('মোট প্রকল্প:', 'Total Projects:')} {pagination.total}
          </p>
        </div>

        <div className="d-flex gap-2">
          {canExportProjects(user) && (
            <button type="button" className="btn btn-sm btn-outline-success" onClick={handleExport}>
              <i className="bi bi-file-earmark-spreadsheet me-1"></i>
              {t('এক্সপোর্ট CSV', 'Export CSV')}
            </button>
          )}
          {canCreateProject(user) && (
            <Link to="/project/schemes/new" className="btn btn-sm btn-primary">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন প্রকল্প নিবন্ধন', 'Register Project')}
            </Link>
          )}
        </div>
      </div>

      {/* Filters */}
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body py-2">
          <div className="row g-2 align-items-center">
            <div className="col-md-4">
              <input
                type="search"
                className="form-control form-control-sm"
                placeholder={t('কোড, শিরোনাম, সংস্থা বা তহবিল অনুসন্ধান...', 'Search code, title, organization, funding...')}
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
                <option value="">{t('সকল ধরন', 'All Categories')}</option>
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
                <option value="">{t('সকল অবস্থা', 'All Lifecycle States')}</option>
                {statuses.map((s) => (
                  <option key={s} value={s}>{t(PROJECT_STATUS_META[s]?.bn || s, PROJECT_STATUS_META[s]?.en || s)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={isDelayed}
                onChange={(e) => {
                  setIsDelayed(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সময়সূচি (সকল)', 'Schedule (All)')}</option>
                <option value="1">{t('বিলম্বিত', 'Delayed Only')}</option>
                <option value="0">{t('সময়মতো', 'On Schedule')}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      {error && <AlertBanner variant="danger" message={formatProjectApiError(error, t('তালিকা লোড ব্যর্থ', 'Failed to load projects'))} />}

      {isLoading ? (
        <Loader />
      ) : projects.length === 0 ? (
        <EmptyState
          title={t('কোনো প্রকল্প পাওয়া যায়নি', 'No projects found')}
          message={t('ফিল্টার পরিবর্তন করুন অথবা নতুন প্রকল্প নিবন্ধন করুন।', 'Adjust filters or register a new disaster management project.')}
        />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('প্রকল্প কোড', 'Code')}</th>
                  <th>{t('শিরোনাম ও ধরন', 'Title & Category')}</th>
                  <th>{t('অবস্থান', 'Jurisdiction')}</th>
                  <th className="text-end">{t('বাজেট ও ব্যয়', 'Budget / Spent')}</th>
                  <th>{t('অগ্রগতি (ভৌত / আর্থিক)', 'Progress (Phys / Fin)')}</th>
                  <th>{t('অবস্থা', 'Status')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {projects.map((p) => (
                  <tr key={p.id}>
                    <td>
                      <Link to={`/project/schemes/${p.id}`} className="fw-semibold text-decoration-none">
                        {p.project_code}
                      </Link>
                      {p.reference_no && <div className="small text-muted">{p.reference_no}</div>}
                    </td>
                    <td>
                      <div className="fw-semibold">{t(p.title_bn, p.title_en)}</div>
                      <small className="text-muted">
                        {p.category ? t(p.category.name_bn, p.category.name_en) : ''} • {p.implementing_organization}
                      </small>
                    </td>
                    <td>
                      <div className="small">{p.administrative_unit ? t(p.administrative_unit.name_bn, p.administrative_unit.name_en) : ''}</div>
                      {p.ward_no && <small className="text-muted">{p.ward_no}</small>}
                    </td>
                    <td className="text-end">
                      <div className="small fw-semibold">{formatBdt(p.effective_budget_bdt)}</div>
                      <small className="text-muted">{t('ব্যয়:', 'Spent:')} {formatBdt(p.expenditure_bdt)}</small>
                    </td>
                    <td style={{ minWidth: '170px' }}>
                      <div className="d-flex align-items-center gap-2 mb-1">
                        <small className="text-muted" style={{ width: '32px' }}>{t('ভৌত', 'Phy')}</small>
                        <div className="progress flex-grow-1" style={{ height: '6px' }}>
                          <div className="progress-bar bg-success" style={{ width: `${p.physical_progress_pct}%` }} />
                        </div>
                        <small>{p.physical_progress_pct}%</small>
                      </div>
                      <div className="d-flex align-items-center gap-2">
                        <small className="text-muted" style={{ width: '32px' }}>{t('আর্থিক', 'Fin')}</small>
                        <div className="progress flex-grow-1" style={{ height: '6px' }}>
                          <div className="progress-bar bg-info" style={{ width: `${p.financial_progress_pct}%` }} />
                        </div>
                        <small>{p.financial_progress_pct}%</small>
                      </div>
                    </td>
                    <td>
                      <ProjectStatusBadge status={p.status} />
                      {p.is_delayed && (
                        <div className="mt-1">
                          <span className="badge bg-danger bg-opacity-75">
                            {t('বিলম্বিত', 'Delayed')} ({p.delay_days}d)
                          </span>
                        </div>
                      )}
                    </td>
                    <td className="text-end">
                      <Link to={`/project/schemes/${p.id}`} className="btn btn-sm btn-outline-primary">
                        {t('বিস্তারিত', 'Details')}
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
                {t('পৃষ্ঠা', 'Page')} {pagination.current_page} / {pagination.last_page}
              </small>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                >
                  {t('পূর্ববর্তী', 'Previous')}
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

export default ProjectListPage;
