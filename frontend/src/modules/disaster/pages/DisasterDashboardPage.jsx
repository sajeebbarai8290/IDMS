import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import disasterApi from '../../../api/disasterApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import { canCreateDisaster } from '../utils/disasterPermissions';
import DisasterGisMap from '../components/DisasterGisMap';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';

export const DisasterDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const navigate = useNavigate();

  const { data, isLoading, error } = useQuery({
    queryKey: ['disasterDashboard'],
    queryFn: async () => {
      const res = await disasterApi.getDashboard();
      return res?.data || res || {};
    },
    refetchInterval: 30000,
  });

  const { data: geojsonData, isFetching: fetchingGeo } = useQuery({
    queryKey: ['disasterDashboardGeojson'],
    queryFn: async () => {
      const res = await disasterApi.getGeoJson({ active_only: 0 });
      return res?.geojson || null;
    },
  });

  if (isLoading) {
    return (
      <Loader
        message="দুর্যোগ ব্যবস্থাপনা ড্যাশবোর্ড লোড হচ্ছে..."
        messageEn="Loading Disaster Management Dashboard..."
      />
    );
  }

  if (error) {
    return (
      <AlertBanner
        type="danger"
        message={t('ড্যাশবোর্ড লোড ব্যর্থ হয়েছে: ', 'Failed to load disaster dashboard: ')}
        details={error?.message}
      />
    );
  }

  const s = data?.summary || {};
  const byCategory = data?.by_category || [];
  const bySeverity = data?.by_severity || [];
  const geoDist = data?.geo_distribution || [];
  const trends = data?.trends || [];

  return (
    <div className="container-fluid py-2">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-tsunami text-danger me-2"></i>
            {t('দুর্যোগ তথ্য ব্যবস্থাপনা ড্যাশবোর্ড (M03)', 'Disaster Information Dashboard (M03)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'সক্রিয় দুর্যোগ পর্যবেক্ষণ, সতর্কবার্তা, ক্ষয়ক্ষতি ও জিআইএস বিশ্লেষণ • দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয়',
              'Active disasters, early warnings, impact analytics & GIS visualization • MoDMR'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <Link to="/disaster/map" className="btn btn-outline-primary btn-sm">
            <i className="bi bi-map me-1"></i>
            {t('জিআইএস মানচিত্র', 'GIS Explorer')}
          </Link>
          <Link to="/disaster/events" className="btn btn-outline-secondary btn-sm">
            <i className="bi bi-list-ul me-1"></i>
            {t('সকল দুর্যোগ তালিকা', 'All Disasters')}
          </Link>
          {canCreateDisaster(user) && (
            <Link to="/disaster/events/new" className="btn btn-danger btn-sm shadow-sm">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন দুর্যোগ নিবন্ধন', 'Register Disaster')}
            </Link>
          )}
        </div>
      </div>

      {/* KPI Row */}
      <div className="row g-3 mb-4">
        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-danger h-100">
            <div className="card-body p-3 d-flex justify-content-between align-items-center">
              <div>
                <small className="text-muted fw-bold text-uppercase">{t('সক্রিয় দুর্যোগ', 'Active Disasters')}</small>
                <h3 className="fw-bold mb-0 text-danger">{s.active_disasters ?? 0}</h3>
                <small className="text-muted">
                  {t(`মোট নিবন্ধিত: ${s.total_disasters ?? 0}`, `Total recorded: ${s.total_disasters ?? 0}`)}
                </small>
              </div>
              <div className="rounded-circle bg-danger-subtle text-danger p-3">
                <i className="bi bi-exclamation-triangle-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-primary h-100">
            <div className="card-body p-3 d-flex justify-content-between align-items-center">
              <div>
                <small className="text-muted fw-bold text-uppercase">{t('ক্ষতিগ্রস্ত জনসংখ্যা', 'Affected Population')}</small>
                <h3 className="fw-bold mb-0 text-primary">{Number(s.total_affected_population || 0).toLocaleString()}</h3>
                <small className="text-muted">
                  {t(
                    `পরিবার: ${Number(s.total_affected_households || 0).toLocaleString()}`,
                    `Households: ${Number(s.total_affected_households || 0).toLocaleString()}`
                  )}
                </small>
              </div>
              <div className="rounded-circle bg-primary-subtle text-primary p-3">
                <i className="bi bi-people-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-warning h-100">
            <div className="card-body p-3 d-flex justify-content-between align-items-center">
              <div>
                <small className="text-muted fw-bold text-uppercase">{t('সক্রিয় সতর্কবার্তা', 'Active Warnings')}</small>
                <h3 className="fw-bold mb-0 text-warning-emphasis">{s.active_warnings_count ?? 0}</h3>
                <small className="text-muted">
                  {t(`জরুরি অবস্থা: ${s.emergency_declared_count ?? 0}`, `Emergencies: ${s.emergency_declared_count ?? 0}`)}
                </small>
              </div>
              <div className="rounded-circle bg-warning-subtle text-warning-emphasis p-3">
                <i className="bi bi-broadcast fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-dark h-100">
            <div className="card-body p-3 d-flex justify-content-between align-items-center">
              <div>
                <small className="text-muted fw-bold text-uppercase">{t('ক্ষতিগ্রস্ত ঘরবাড়ি ও মৃত্যু', 'Damaged Houses & Deaths')}</small>
                <h3 className="fw-bold mb-0">{Number(s.total_damaged_houses || 0).toLocaleString()}</h3>
                <small className="text-danger fw-semibold">
                  {t(`মৃত্যু: ${s.total_deaths ?? 0} • আহত: ${s.total_injured ?? 0}`, `Deaths: ${s.total_deaths ?? 0} • Injured: ${s.total_injured ?? 0}`)}
                </small>
              </div>
              <div className="rounded-circle bg-secondary-subtle text-dark p-3">
                <i className="bi bi-house-slash-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Interactive Map */}
      <div className="mb-4">
        <DisasterGisMap
          geojson={geojsonData}
          height="380px"
          mode="explore"
          isFetching={fetchingGeo}
          onSelectDisaster={(id) => navigate(`/disaster/events/${id}`)}
        />
      </div>

      {/* Category, Severity, Geo & Trends */}
      <div className="row g-3">
        <div className="col-lg-4">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              {t('দুর্যোগের ধরন অনুযায়ী বিভাজন', 'Disasters by Category')}
            </div>
            <div className="card-body p-0">
              <table className="table table-sm mb-0 small">
                <thead className="table-light">
                  <tr>
                    <th>{t('ধরন', 'Category')}</th>
                    <th className="text-center">{t('মোট', 'Total')}</th>
                    <th className="text-center">{t('সক্রিয়', 'Active')}</th>
                    <th className="text-end">{t('ক্ষতিগ্রস্ত', 'Affected')}</th>
                  </tr>
                </thead>
                <tbody>
                  {byCategory.length === 0 ? (
                    <tr>
                      <td colSpan="4" className="text-center text-muted py-3">{t('তথ্য নেই', 'No data')}</td>
                    </tr>
                  ) : (
                    byCategory.map((c) => (
                      <tr key={c.category}>
                        <td className="fw-semibold">{c.category}</td>
                        <td className="text-center">{c.count}</td>
                        <td className="text-center text-danger fw-bold">{c.active_count}</td>
                        <td className="text-end">{Number(c.affected_population).toLocaleString()}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              {t('তীব্রতা ও ভৌগোলিক বিস্তার', 'Severity & Geographic Distribution')}
            </div>
            <div className="card-body small">
              <div className="mb-3">
                <div className="text-muted fw-bold mb-1">{t('তীব্রতা অনুযায়ী', 'By Severity')}</div>
                <div className="d-flex flex-wrap gap-2">
                  {bySeverity.map((sev) => (
                    <span key={sev.severity_level} className="badge bg-light text-dark border">
                      {sev.severity_level}: <strong>{sev.count}</strong>
                    </span>
                  ))}
                </div>
              </div>
              <hr />
              <div className="text-muted fw-bold mb-1">{t('প্রভাবিত প্রশাসনিক এলাকা', 'Affected Administrative Units')}</div>
              {geoDist.length === 0 ? (
                <div className="text-muted">{t('কোনো এলাকা যুক্ত নেই', 'No mapped locations')}</div>
              ) : (
                <ul className="list-group list-group-flush">
                  {geoDist.slice(0, 6).map((g) => (
                    <li key={g.administrative_unit_id} className="list-group-item px-0 py-1 d-flex justify-content-between">
                      <span>{t(g.name_bn, g.name_en)} <small className="text-muted">({g.tier_level})</small></span>
                      <span className="badge bg-primary-subtle text-primary">{g.disasters_count}</span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              {t('দুর্যোগের ইতিহাস ও প্রবণতা', 'Disaster History & Trends')}
            </div>
            <div className="card-body p-0">
              <table className="table table-sm mb-0 small">
                <thead className="table-light">
                  <tr>
                    <th>{t('সময়কাল', 'Period')}</th>
                    <th className="text-center">{t('ঘটনা', 'Events')}</th>
                    <th className="text-end">{t('ক্ষতিগ্রস্ত জনসংখ্যা', 'Affected Pop')}</th>
                  </tr>
                </thead>
                <tbody>
                  {trends.length === 0 ? (
                    <tr>
                      <td colSpan="3" className="text-center text-muted py-3">{t('তথ্য নেই', 'No trend data')}</td>
                    </tr>
                  ) : (
                    trends.map((tr) => (
                      <tr key={tr.period}>
                        <td className="font-monospace">{tr.period}</td>
                        <td className="text-center fw-bold">{tr.events_count}</td>
                        <td className="text-end">{Number(tr.affected_population).toLocaleString()}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DisasterDashboardPage;
