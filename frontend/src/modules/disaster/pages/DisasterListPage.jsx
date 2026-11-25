import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import disasterApi from '../../../api/disasterApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import { canCreateDisaster, canUpdateDisaster, LIFECYCLE_PHASES, SEVERITY_LEVELS } from '../utils/disasterPermissions';
import { DisasterPhaseBadge, DisasterSeverityBadge } from '../components/DisasterStatusBadges';
import Loader from '../../../components/common/Loader';
import EmptyState from '../../../components/common/EmptyState';
import AlertBanner from '../../../components/common/AlertBanner';

export const DisasterListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();

  const [filters, setFilters] = useState({
    search: '',
    disaster_type: '',
    severity_level: '',
    lifecycle_phase: '',
    active_only: '',
    page: 1,
    per_page: 15,
  });

  const { data: categoriesData } = useQuery({
    queryKey: ['disasterCategories'],
    queryFn: async () => {
      const res = await disasterApi.getCategories();
      return res?.data || [];
    },
  });

  const { data, isLoading, error } = useQuery({
    queryKey: ['disasterEvents', filters],
    queryFn: async () => {
      const clean = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined)
      );
      return await disasterApi.getEvents(clean);
    },
    keepPreviousData: true,
  });

  const events = data?.data || [];
  const pagination = data?.pagination || {};

  return (
    <div className="container-fluid py-2">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-exclamation-diamond-fill text-danger me-2"></i>
            {t('দুর্যোগ নিবন্ধন ও তালিকা (Disaster Registry)', 'Disaster Registry')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'বাংলাদেশব্যাপী প্রতিবেদনকৃত, যাচাইকৃত ও চলমান দুর্যোগসমূহের তালিকা',
              'Reported, verified, active, and historical disaster events across Bangladesh'
            )}
          </p>
        </div>

        <div className="d-flex gap-2">
          <Link to="/disaster/map" className="btn btn-outline-primary btn-sm">
            <i className="bi bi-map me-1"></i>
            {t('জিআইএস মানচিত্র', 'GIS Map')}
          </Link>
          {canCreateDisaster(user) && (
            <Link to="/disaster/events/new" className="btn btn-danger btn-sm shadow-sm">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন দুর্যোগ নিবন্ধন', 'Register Disaster')}
            </Link>
          )}
        </div>
      </div>

      {/* Search & Filters */}
      <div className="card shadow-sm border-0 mb-3">
        <div className="card-body py-3">
          <div className="row g-2 align-items-end">
            <div className="col-md-3">
              <label className="form-label small fw-bold mb-1">{t('অনুসন্ধান', 'Search')}</label>
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('কোড, শিরোনাম বা এলাকা...', 'Code, title, affected area...')}
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value, page: 1 })}
              />
            </div>

            <div className="col-md-2">
              <label className="form-label small fw-bold mb-1">{t('দুর্যোগের ধরন', 'Category')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.disaster_type}
                onChange={(e) => setFilters({ ...filters, disaster_type: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল ধরন', 'All Categories')}</option>
                {categoriesData?.map((c) => (
                  <option key={c.id} value={c.code}>
                    {t(c.name_bn, c.name_en)}
                  </option>
                ))}
              </select>
            </div>

            <div className="col-md-2">
              <label className="form-label small fw-bold mb-1">{t('তীব্রতা', 'Severity')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.severity_level}
                onChange={(e) => setFilters({ ...filters, severity_level: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল', 'All')}</option>
                {SEVERITY_LEVELS.map((s) => (
                  <option key={s.value} value={s.value}>
                    {t(s.bn, s.en)}
                  </option>
                ))}
              </select>
            </div>

            <div className="col-md-2">
              <label className="form-label small fw-bold mb-1">{t('অবস্থা (Lifecycle)', 'Lifecycle Phase')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.lifecycle_phase}
                onChange={(e) => setFilters({ ...filters, lifecycle_phase: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল অবস্থা', 'All Phases')}</option>
                {LIFECYCLE_PHASES.map((p) => (
                  <option key={p.value} value={p.value}>
                    {t(p.bn, p.en)}
                  </option>
                ))}
              </select>
            </div>

            <div className="col-md-2">
              <div className="form-check mb-1">
                <input
                  id="chkActiveOnly"
                  type="checkbox"
                  className="form-check-input"
                  checked={filters.active_only === '1'}
                  onChange={(e) => setFilters({ ...filters, active_only: e.target.checked ? '1' : '', page: 1 })}
                />
                <label className="form-check-label small" htmlFor="chkActiveOnly">
                  {t('শুধু চলমান', 'Active Only')}
                </label>
              </div>
            </div>

            <div className="col-md-1 d-grid">
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm"
                onClick={() =>
                  setFilters({
                    search: '',
                    disaster_type: '',
                    severity_level: '',
                    lifecycle_phase: '',
                    active_only: '',
                    page: 1,
                    per_page: 15,
                  })
                }
              >
                {t('রিসেট', 'Reset')}
              </button>
            </div>
          </div>
        </div>
      </div>

      {isLoading && <Loader message="দুর্যোগের তালিকা লোড হচ্ছে..." messageEn="Loading disaster events..." />}

      {error && (
        <AlertBanner
          type="danger"
          message={t('দুর্যোগ তালিকা লোড ব্যর্থ হয়েছে: ', 'Failed to load disaster events: ')}
          details={error?.message}
        />
      )}

      {!isLoading && !error && events.length === 0 && (
        <EmptyState
          icon="bi-cloud-sun"
          title={t('কোনো দুর্যোগ রেকর্ড পাওয়া যায়নি', 'No disaster events found')}
          titleEn="Try clearing filters or registering a new event"
          description={t('বর্তমান অনুসন্ধান অনুযায়ী কোনো দুর্যোগ পাওয়া যায়নি।', 'No disasters matched the selected criteria.')}
        />
      )}

      {!isLoading && !error && events.length > 0 && (
        <div className="card shadow-sm border-0">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light small">
                <tr>
                  <th>{t('আইডি কোড', 'Disaster ID')}</th>
                  <th>{t('শিরোনাম ও ধরন', 'Title & Category')}</th>
                  <th>{t('তীব্রতা', 'Severity')}</th>
                  <th>{t('অবস্থা', 'Lifecycle')}</th>
                  <th>{t('ক্ষতিগ্রস্ত এলাকা', 'Affected Areas')}</th>
                  <th className="text-end">{t('ক্ষতিগ্রস্ত জনসংখ্যা', 'Affected Pop')}</th>
                  <th className="text-end">{t('অ্যাকশন', 'Actions')}</th>
                </tr>
              </thead>
              <tbody className="small">
                {events.map((ev) => (
                  <tr key={ev.id}>
                    <td className="font-monospace fw-bold">
                      <Link to={`/disaster/events/${ev.id}`} className="text-decoration-none">
                        {ev.disaster_code}
                      </Link>
                      {ev.is_emergency_declared && (
                        <div>
                          <span className="badge bg-danger mt-1">EMERGENCY</span>
                        </div>
                      )}
                    </td>
                    <td>
                      <div className="fw-bold">{t(ev.name_bn, ev.name_en)}</div>
                      <div className="text-muted">
                        {ev.disaster_type}
                        {ev.subtype ? ` / ${ev.subtype}` : ''}
                      </div>
                    </td>
                    <td>
                      <DisasterSeverityBadge severity={ev.severity_level} />
                    </td>
                    <td>
                      <DisasterPhaseBadge phase={ev.lifecycle_phase} />
                    </td>
                    <td>
                      <div>{ev.affected_areas_summary || '—'}</div>
                      {ev.locations?.length > 0 && (
                        <small className="text-muted">
                          {ev.locations.length} {t('টি প্রশাসনিক এলাকা', 'admin units')}
                        </small>
                      )}
                    </td>
                    <td className="text-end fw-semibold">
                      {Number(ev.impact?.affected_population || 0).toLocaleString()}
                    </td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <Link to={`/disaster/events/${ev.id}`} className="btn btn-outline-primary">
                          {t('বিস্তারিত', 'Details')}
                        </Link>
                        {canUpdateDisaster(user) && (
                          <Link to={`/disaster/events/${ev.id}/edit`} className="btn btn-outline-secondary">
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
              <span className="text-muted">
                {t(
                  `পৃষ্ঠা ${pagination.current_page} / ${pagination.last_page} (মোট ${pagination.total})`,
                  `Page ${pagination.current_page} of ${pagination.last_page} (${pagination.total} total)`
                )}
              </span>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={pagination.current_page <= 1}
                  onClick={() => setFilters({ ...filters, page: pagination.current_page - 1 })}
                >
                  {t('পূর্ববর্তী', 'Previous')}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={pagination.current_page >= pagination.last_page}
                  onClick={() => setFilters({ ...filters, page: pagination.current_page + 1 })}
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

export default DisasterListPage;
