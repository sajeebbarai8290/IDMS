import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import disasterApi from '../../../api/disasterApi';
import beneficiaryApi from '../../../api/beneficiaryApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import { LIFECYCLE_PHASES, SEVERITY_LEVELS } from '../utils/disasterPermissions';
import { DisasterPhaseBadge, DisasterSeverityBadge } from '../components/DisasterStatusBadges';
import DisasterGisMap from '../components/DisasterGisMap';
import AlertBanner from '../../../components/common/AlertBanner';

export const DisasterMapPage = () => {
  const { t } = useLanguage();
  const navigate = useNavigate();

  const [filters, setFilters] = useState({
    search: '',
    disaster_type: '',
    severity_level: '',
    lifecycle_phase: '',
    administrative_unit_id: '',
    date_from: '',
    date_to: '',
    active_only: '',
    bbox: '',
  });

  const { data: categories = [] } = useQuery({
    queryKey: ['disasterCategories'],
    queryFn: async () => (await disasterApi.getCategories())?.data || [],
  });

  const { data: adminUnits = [] } = useQuery({
    queryKey: ['adminUnitsForMap'],
    queryFn: async () => (await beneficiaryApi.getAdministrativeUnits())?.data || [],
  });

  const cleanParams = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''));

  const { data: geoData, isFetching: fetchingGeo, error: geoError } = useQuery({
    queryKey: ['disasterGisExplorer', cleanParams],
    queryFn: async () => await disasterApi.getGeoJson(cleanParams),
    placeholderData: (prev) => prev,
  });

  const { data: listData } = useQuery({
    queryKey: ['disasterGisList', cleanParams],
    queryFn: async () => await disasterApi.getEvents({ ...cleanParams, per_page: 25 }),
    placeholderData: (prev) => prev,
  });

  const matchedEvents = listData?.data || [];

  const resetFilters = () =>
    setFilters({
      search: '',
      disaster_type: '',
      severity_level: '',
      lifecycle_phase: '',
      administrative_unit_id: '',
      date_from: '',
      date_to: '',
      active_only: '',
      bbox: '',
    });

  return (
    <div className="container-fluid py-2">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-map-fill text-primary me-2"></i>
            {t('দুর্যোগ জিআইএস মানচিত্র ও স্থানিক বিশ্লেষণ', 'Disaster GIS Interactive Map & Spatial Query')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'লিফলেট ও পোস্টজিআইএস মানচিত্র: ফিল্টার প্রয়োগ করুন, শেপফাইল (.zip) ওভারলে দেখুন এবং স্থানিক এলাকা খুঁজুন',
              'Leaflet & PostGIS map: apply filters, preview Shapefile (.zip) overlays, and run spatial queries'
            )}
          </p>
        </div>
        <div className="d-flex gap-2">
          <Link to="/disaster/dashboard" className="btn btn-outline-secondary btn-sm">
            {t('ড্যাশবোর্ড', 'Dashboard')}
          </Link>
          <Link to="/disaster/events" className="btn btn-outline-primary btn-sm">
            {t('তালিকা দেখুন', 'List View')}
          </Link>
        </div>
      </div>

      {/* Attribute & Spatial Filter Controls */}
      <div className="card shadow-sm border-0 mb-3">
        <div className="card-body py-2">
          <div className="row g-2 align-items-end">
            <div className="col-md-3">
              <label className="form-label small fw-bold mb-1">{t('অনুসন্ধান', 'Search')}</label>
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('কোড, নাম বা এলাকা...', 'Code, title, area...')}
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value })}
              />
            </div>

            <div className="col-md-2">
              <label className="form-label small fw-bold mb-1">{t('দুর্যোগের ধরন', 'Category')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.disaster_type}
                onChange={(e) => setFilters({ ...filters, disaster_type: e.target.value })}
              >
                <option value="">{t('সকল', 'All')}</option>
                {categories.map((c) => (
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
                onChange={(e) => setFilters({ ...filters, severity_level: e.target.value })}
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
              <label className="form-label small fw-bold mb-1">{t('জীবনচক্র', 'Lifecycle')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.lifecycle_phase}
                onChange={(e) => setFilters({ ...filters, lifecycle_phase: e.target.value })}
              >
                <option value="">{t('সকল', 'All')}</option>
                {LIFECYCLE_PHASES.map((p) => (
                  <option key={p.value} value={p.value}>
                    {t(p.bn, p.en)}
                  </option>
                ))}
              </select>
            </div>

            <div className="col-md-3">
              <label className="form-label small fw-bold mb-1">{t('প্রশাসনিক এলাকা', 'Administrative Unit')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.administrative_unit_id}
                onChange={(e) => setFilters({ ...filters, administrative_unit_id: e.target.value })}
              >
                <option value="">{t('সকল এলাকা', 'All Areas')}</option>
                {adminUnits.map((u) => (
                  <option key={u.id} value={u.id}>
                    [{u.tier_level}] {u.name_bn} ({u.name_en})
                  </option>
                ))}
              </select>
            </div>

            <div className="col-md-2">
              <label className="form-label small fw-bold mb-1">{t('তারিখ থেকে', 'From Date')}</label>
              <input
                type="date"
                className="form-control form-control-sm"
                value={filters.date_from}
                onChange={(e) => setFilters({ ...filters, date_from: e.target.value })}
              />
            </div>

            <div className="col-md-2">
              <label className="form-label small fw-bold mb-1">{t('তারিখ পর্যন্ত', 'To Date')}</label>
              <input
                type="date"
                className="form-control form-control-sm"
                value={filters.date_to}
                onChange={(e) => setFilters({ ...filters, date_to: e.target.value })}
              />
            </div>

            <div className="col-md-2">
              <div className="form-check mb-1">
                <input
                  id="chkMapActiveOnly"
                  type="checkbox"
                  className="form-check-input"
                  checked={filters.active_only === '1'}
                  onChange={(e) => setFilters({ ...filters, active_only: e.target.checked ? '1' : '' })}
                />
                <label className="form-check-label small" htmlFor="chkMapActiveOnly">
                  {t('শুধু চলমান', 'Active Only')}
                </label>
              </div>
            </div>

            <div className="col-md-6 d-flex flex-wrap justify-content-end gap-2 align-items-center">
              {filters.bbox && (
                <span className="badge bg-info-subtle text-info-emphasis border border-info font-monospace">
                  BBox: {filters.bbox}
                </span>
              )}
              <button type="button" className="btn btn-outline-secondary btn-sm" onClick={resetFilters}>
                <i className="bi bi-arrow-counterclockwise me-1"></i>
                {t('সব ফিল্টার মুছুন', 'Reset Filters')}
              </button>
              <span className="badge bg-primary py-2 px-3">
                {geoData?.count ?? matchedEvents.length} {t('টি দুর্যোগ', 'Events')}
              </span>
            </div>
          </div>
        </div>
      </div>

      {geoError && (
        <AlertBanner
          type="danger"
          message={t('মানচিত্র ডেটা লোড হয়নি', 'Failed to load GIS features')}
          details={geoError?.message}
        />
      )}

      {/* Keep Leaflet map mounted continuously across filter changes */}
      <div className="mb-3">
        <DisasterGisMap
          geojson={geoData?.geojson || null}
          height="520px"
          mode="explore"
          isFetching={fetchingGeo}
          onBoundingBoxSearch={(bbox) => setFilters((prev) => ({ ...prev, bbox }))}
          onSelectDisaster={(id) => navigate(`/disaster/events/${id}`)}
        />
      </div>

      {/* Filtered Disasters Table */}
      <div className="card shadow-sm border-0">
        <div className="card-header bg-white fw-bold d-flex justify-content-between align-items-center small">
          <span>
            <i className="bi bi-table me-2 text-primary"></i>
            {t('ফিল্টারকৃত দুর্যোগসমূহ', 'Filtered Disaster Events')} ({matchedEvents.length})
          </span>
        </div>
        <div className="table-responsive">
          <table className="table table-sm table-hover align-middle mb-0 small">
            <thead className="table-light">
              <tr>
                <th>{t('কোড', 'Code')}</th>
                <th>{t('শিরোনাম', 'Title')}</th>
                <th>{t('ধরন', 'Type')}</th>
                <th>{t('তীব্রতা', 'Severity')}</th>
                <th>{t('অবস্থা', 'Phase')}</th>
                <th>{t('অক্ষ/দ্রাঘিমা', 'Coordinates')}</th>
                <th className="text-end">{t('অ্যাকশন', 'Action')}</th>
              </tr>
            </thead>
            <tbody>
              {matchedEvents.length === 0 ? (
                <tr>
                  <td colSpan="7" className="text-center py-3 text-muted">
                    {t('কোনো দুর্যোগ মিলেনি।', 'No matching disasters.')}
                  </td>
                </tr>
              ) : (
                matchedEvents.map((ev) => (
                  <tr key={ev.id}>
                    <td className="font-monospace fw-bold">{ev.disaster_code}</td>
                    <td>{t(ev.name_bn, ev.name_en)}</td>
                    <td>{ev.disaster_type}</td>
                    <td>
                      <DisasterSeverityBadge severity={ev.severity_level} />
                    </td>
                    <td>
                      <DisasterPhaseBadge phase={ev.lifecycle_phase} />
                    </td>
                    <td className="font-monospace">
                      {ev.epicenter_lat && ev.epicenter_lng
                        ? `${Number(ev.epicenter_lat).toFixed(4)}, ${Number(ev.epicenter_lng).toFixed(4)}`
                        : '—'}
                    </td>
                    <td className="text-end">
                      <Link to={`/disaster/events/${ev.id}`} className="btn btn-outline-primary btn-sm py-0 px-2">
                        {t('বিস্তারিত', 'Details')}
                      </Link>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default DisasterMapPage;
