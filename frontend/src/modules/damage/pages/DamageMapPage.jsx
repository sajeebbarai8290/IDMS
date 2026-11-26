import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { damageApi } from '../../../api/damageApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import DamageGisMap from '../components/DamageGisMap';
import { DamageSeverityBadge } from '../components/DamageStatusBadges';
import {
  DAMAGE_SEVERITY_META,
  DAMAGE_STATUS_META,
  formatBdt,
  formatDamageApiError,
} from '../utils/damagePermissions';

export const DamageMapPage = () => {
  const { t } = useLanguage();
  const navigate = useNavigate();

  const [disasterId, setDisasterId] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [severityFilter, setSeverityFilter] = useState('');
  const [showInfrastructure, setShowInfrastructure] = useState(true);
  const [showHeatmap, setShowHeatmap] = useState(true);
  const [showAreaSeverity, setShowAreaSeverity] = useState(true);

  const filterParams = {
    ...(disasterId ? { disaster_event_id: disasterId } : {}),
    ...(statusFilter ? { verification_status: statusFilter } : {}),
    ...(severityFilter ? { severity_level: severityFilter } : {}),
  };

  const { data: lookupsRes } = useQuery({
    queryKey: ['damage', 'lookups'],
    queryFn: () => damageApi.getLookups(),
  });

  const { data: geoRes, isLoading: loadingGeo, error: geoError } = useQuery({
    queryKey: ['damage', 'gis', 'geojson', filterParams],
    queryFn: () => damageApi.getGeoJson(filterParams),
  });

  const { data: heatRes, isLoading: loadingHeat } = useQuery({
    queryKey: ['damage', 'gis', 'heatmap', filterParams],
    queryFn: () => damageApi.getHeatmap(filterParams),
  });

  if (loadingGeo || loadingHeat) return <Loader />;

  const disasters = lookupsRes?.data?.disasters || [];
  const geojson = geoRes?.data || null;
  const heatmapPoints = heatRes?.data?.points || [];
  const areas = heatRes?.data?.area_severity || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-geo-alt-fill text-danger me-2"></i>
            {t('ক্ষয়ক্ষতি জিআইএস মানচিত্র ও হিটম্যাপ (M09 GIS)', 'Damage GIS Map, Heatmap & Spatial Aggregation')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'ক্ষতিগ্রস্ত অবকাঠামো, তীব্রতা হিটম্যাপ এবং এলাকাভিত্তিক স্থানিক একত্রীকরণ',
              'Damaged infrastructure layer, thermal damage heatmap, and area-wise spatial severity aggregation'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <select
            className="form-select form-select-sm"
            style={{ width: '185px' }}
            value={disasterId}
            onChange={(e) => setDisasterId(e.target.value)}
          >
            <option value="">{t('সকল দুর্যোগ', 'All Disasters')}</option>
            {disasters.map((d) => (
              <option key={d.id} value={d.id}>{t(d.name_bn, d.name_en)}</option>
            ))}
          </select>

          <select
            className="form-select form-select-sm"
            style={{ width: '160px' }}
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
          >
            <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
            {Object.entries(DAMAGE_STATUS_META).map(([k, v]) => (
              <option key={k} value={k}>{t(v.bn, v.en)}</option>
            ))}
          </select>

          <select
            className="form-select form-select-sm"
            style={{ width: '150px' }}
            value={severityFilter}
            onChange={(e) => setSeverityFilter(e.target.value)}
          >
            <option value="">{t('সকল তীব্রতা', 'All Severities')}</option>
            {Object.entries(DAMAGE_SEVERITY_META).map(([k, v]) => (
              <option key={k} value={k}>{t(v.bn, v.en)}</option>
            ))}
          </select>
        </div>
      </div>

      {geoError && (
        <AlertBanner variant="danger" message={formatDamageApiError(geoError, t('মানচিত্র লোড ব্যর্থ।', 'Failed to load GIS data.'))} />
      )}

      {/* Layer Controls */}
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body py-2 d-flex flex-wrap gap-4 align-items-center">
          <span className="small fw-semibold text-muted">{t('মানচিত্র স্তর (GIS Layers):', 'Map Layers:')}</span>
          <div className="form-check form-switch mb-0">
            <input
              className="form-check-input"
              type="checkbox"
              id="layerInfra"
              checked={showInfrastructure}
              onChange={(e) => setShowInfrastructure(e.target.checked)}
            />
            <label className="form-check-label small" htmlFor="layerInfra">
              {t('ক্ষতিগ্রস্ত অবকাঠামো ও নিরূপণ', 'Damaged Infrastructure & D-Forms')}
            </label>
          </div>
          <div className="form-check form-switch mb-0">
            <input
              className="form-check-input"
              type="checkbox"
              id="layerHeat"
              checked={showHeatmap}
              onChange={(e) => setShowHeatmap(e.target.checked)}
            />
            <label className="form-check-label small" htmlFor="layerHeat">
              {t('ক্ষয়ক্ষতি হিটম্যাপ (Heatmap)', 'Damage Heatmap')}
            </label>
          </div>
          <div className="form-check form-switch mb-0">
            <input
              className="form-check-input"
              type="checkbox"
              id="layerArea"
              checked={showAreaSeverity}
              onChange={(e) => setShowAreaSeverity(e.target.checked)}
            />
            <label className="form-check-label small" htmlFor="layerArea">
              {t('এলাকাভিত্তিক তীব্রতা', 'Area Severity Aggregation')}
            </label>
          </div>
        </div>
      </div>

      <div className="row g-3">
        <div className="col-lg-8">
          <div className="card border-0 shadow-sm">
            <div className="card-body p-2">
              <DamageGisMap
                geojson={geojson}
                heatmapPoints={heatmapPoints}
                areaAggregations={areas}
                showInfrastructure={showInfrastructure}
                showHeatmap={showHeatmap}
                showAreaSeverity={showAreaSeverity}
                height="520px"
                onSelectAssessment={(assessmentId) => navigate(`/damage/assessments/${assessmentId}`)}
              />
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white fw-semibold">
              {t('স্থানিক এলাকাভিত্তিক সারসংক্ষেপ', 'Spatial Aggregation by Area')}
            </div>
            <div className="card-body p-0">
              {areas.length === 0 ? (
                <div className="p-4 text-center text-muted small">
                  {t('কোনো এলাকা তথ্য পাওয়া যায়নি।', 'No spatial area aggregations available.')}
                </div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm table-hover mb-0 align-middle">
                    <thead className="table-light">
                      <tr>
                        <th>{t('এলাকা', 'Area')}</th>
                        <th className="text-center">{t('তীব্রতা', 'Severity')}</th>
                        <th className="text-end">{t('আর্থিক ক্ষতি', 'Loss')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {areas.map((area) => (
                        <tr key={area.administrative_unit_id}>
                          <td>
                            <div className="fw-medium">{t(area.name_bn, area.name_en)}</div>
                            <div className="small text-muted">
                              {t('নিরূপণ', 'Forms')}: {area.assessment_count} | {t('পরিবার', 'HH')}:{' '}
                              {Number(area.affected_households || 0).toLocaleString()}
                            </div>
                          </td>
                          <td className="text-center">
                            <DamageSeverityBadge severity={area.max_severity} />
                          </td>
                          <td className="text-end fw-semibold small">{formatBdt(area.total_estimated_loss_bdt)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DamageMapPage;
