import React, { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useLanguage } from '../../../contexts/LanguageContext';
import { parseSpatialFile } from '../../disaster/utils/shapefileParser';
import { DAMAGE_SEVERITY_META } from '../utils/damagePermissions';

const BANGLADESH_CENTER = [23.685, 90.3563];

const escapeHtml = (value) =>
  String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');

export const DamageGisMap = ({
  geojson = null,
  heatmapPoints = [],
  areaAggregations = [],
  showInfrastructure = true,
  showHeatmap = true,
  showAreaSeverity = true,
  height = '450px',
  mode = 'explore', // 'explore' | 'pick'
  pickerLat = null,
  pickerLng = null,
  pickerBoundary = null,
  onPickPoint = null,
  onBoundaryParsed = null,
  onSelectAssessment = null,
}) => {
  const { t } = useLanguage();
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const infraLayerRef = useRef(null);
  const heatLayerRef = useRef(null);
  const areaLayerRef = useRef(null);
  const drawLayerRef = useRef(null);
  const fileInputRef = useRef(null);

  const [drawTool, setDrawTool] = useState('point');
  const [draftVertices, setDraftVertices] = useState([]);
  const [uploadError, setUploadError] = useState('');

  const drawToolRef = useRef(drawTool);
  const onPickRef = useRef(onPickPoint);
  const onSelectRef = useRef(onSelectAssessment);

  useEffect(() => {
    drawToolRef.current = drawTool;
  }, [drawTool]);

  useEffect(() => {
    onPickRef.current = onPickPoint;
  }, [onPickPoint]);

  useEffect(() => {
    onSelectRef.current = onSelectAssessment;
  }, [onSelectAssessment]);

  useEffect(() => {
    if (!containerRef.current || mapRef.current) return;

    const map = L.map(containerRef.current, {
      center: BANGLADESH_CENTER,
      zoom: 7,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 18,
    }).addTo(map);

    heatLayerRef.current = L.featureGroup().addTo(map);
    areaLayerRef.current = L.featureGroup().addTo(map);
    infraLayerRef.current = L.featureGroup().addTo(map);
    drawLayerRef.current = L.featureGroup().addTo(map);
    mapRef.current = map;

    if (mode === 'pick') {
      map.on('click', (e) => {
        const lat = Number(e.latlng.lat.toFixed(6));
        const lng = Number(e.latlng.lng.toFixed(6));
        if (drawToolRef.current === 'point' && onPickRef.current) {
          onPickRef.current(lat, lng);
        } else if (drawToolRef.current === 'polygon') {
          setDraftVertices((prev) => [...prev, [lng, lat]]);
        }
      });
    }

    return () => {
      map.remove();
      mapRef.current = null;
      infraLayerRef.current = null;
      heatLayerRef.current = null;
      areaLayerRef.current = null;
      drawLayerRef.current = null;
    };
  }, [mode]);

  // Draw picker point & polygon
  useEffect(() => {
    const group = drawLayerRef.current;
    if (!group) return;
    group.clearLayers();

    if (pickerLat !== null && pickerLng !== null && !Number.isNaN(Number(pickerLat)) && !Number.isNaN(Number(pickerLng))) {
      L.circleMarker([Number(pickerLat), Number(pickerLng)], {
        radius: 8,
        color: '#dc3545',
        fillColor: '#dc3545',
        fillOpacity: 0.9,
        weight: 2,
      }).addTo(group);
    }

    if (pickerBoundary) {
      L.geoJSON(pickerBoundary, {
        style: () => ({ color: '#fd7e14', weight: 2, fillColor: '#fd7e14', fillOpacity: 0.22 }),
      }).addTo(group);
    }

    if (draftVertices.length > 0) {
      const latLngs = draftVertices.map(([lng, lat]) => [lat, lng]);
      if (latLngs.length >= 3) {
        L.polygon(latLngs, { color: '#0d6efd', weight: 2, fillOpacity: 0.2 }).addTo(group);
      } else {
        L.polyline(latLngs, { color: '#0d6efd', weight: 2 }).addTo(group);
      }
      latLngs.forEach((pt) => {
        L.circleMarker(pt, { radius: 4, color: '#0d6efd', fillOpacity: 1 }).addTo(group);
      });
    }
  }, [pickerLat, pickerLng, pickerBoundary, draftVertices]);

  // Render Heatmap thermal intensity layer
  useEffect(() => {
    const group = heatLayerRef.current;
    if (!group) return;
    group.clearLayers();

    if (!showHeatmap || !Array.isArray(heatmapPoints) || heatmapPoints.length === 0) return;

    heatmapPoints.forEach((pt) => {
      if (pt.latitude == null || pt.longitude == null) return;
      const intensity = Math.min(1, Math.max(0.15, Number(pt.intensity || 0.4)));
      const baseRadius = 12000 + intensity * 26000;

      // Multi-stop concentric thermal aura
      L.circle([Number(pt.latitude), Number(pt.longitude)], {
        radius: baseRadius,
        stroke: false,
        fillColor: '#fd7e14',
        fillOpacity: 0.14 * intensity,
      }).addTo(group);

      L.circle([Number(pt.latitude), Number(pt.longitude)], {
        radius: baseRadius * 0.55,
        stroke: false,
        fillColor: '#dc3545',
        fillOpacity: 0.24 * intensity,
      }).addTo(group);

      L.circle([Number(pt.latitude), Number(pt.longitude)], {
        radius: baseRadius * 0.22,
        stroke: false,
        fillColor: '#b02a37',
        fillOpacity: 0.36 * intensity,
      }).addTo(group);
    });
  }, [heatmapPoints, showHeatmap]);

  // Render Geographical Area Severity bubbles
  useEffect(() => {
    const group = areaLayerRef.current;
    if (!group) return;
    group.clearLayers();

    if (!showAreaSeverity || !Array.isArray(areaAggregations) || areaAggregations.length === 0) return;

    areaAggregations.forEach((area) => {
      if (area.latitude == null || area.longitude == null) return;
      const color = area.severity_color || DAMAGE_SEVERITY_META[area.max_severity]?.color || '#fd7e14';
      const radius = Math.min(28, Math.max(10, 10 + Number(area.assessment_count || 1) * 3));

      const marker = L.circleMarker([Number(area.latitude), Number(area.longitude)], {
        radius,
        color,
        fillColor: color,
        fillOpacity: 0.28,
        weight: 2,
        dashArray: '4, 3',
      });

      marker.bindPopup(
        `<div style="min-width:190px">` +
          `<strong>${escapeHtml(area.name_bn || area.name_en)} (${escapeHtml(area.tier_level)})</strong><br/>` +
          `<small>${escapeHtml(t('মোট নিরূপণ', 'Assessments'))}: ${Number(area.assessment_count || 0)} (${escapeHtml(t('যাচাইকৃত', 'Verified'))}: ${Number(area.verified_count || 0)})</small><br/>` +
          `<small>${escapeHtml(t('সর্বোচ্চ তীব্রতা', 'Max Severity'))}: <b>${escapeHtml(area.max_severity)}</b></small><br/>` +
          `<small>${escapeHtml(t('ক্ষতিগ্রস্ত পরিবার', 'Affected HH'))}: ${Number(area.affected_households || 0).toLocaleString()}</small><br/>` +
          `<small>${escapeHtml(t('আনুমানিক ক্ষতি', 'Estimated Loss'))}: ৳ ${Number(area.total_estimated_loss_bdt || 0).toLocaleString()}</small>` +
        `</div>`
      );
      marker.addTo(group);
    });
  }, [areaAggregations, showAreaSeverity, t]);

  // Render Damaged Infrastructure & Assessment GeoJSON
  useEffect(() => {
    const map = mapRef.current;
    const group = infraLayerRef.current;
    if (!map || !group) return;
    group.clearLayers();

    if (!showInfrastructure) return;
    const features = geojson?.features || [];
    if (features.length === 0) return;

    features.forEach((feat) => {
      const props = feat.properties || {};
      const color = props.category_color || props.severity_color || '#dc3545';

      const layer = L.geoJSON(feat, {
        style: () => ({
          color,
          weight: 2,
          fillColor: color,
          fillOpacity: 0.18,
        }),
        pointToLayer: (_, latlng) =>
          L.circleMarker(latlng, {
            radius: props.feature_type === 'damaged_infrastructure_item' ? 6 : 8,
            color,
            fillColor: color,
            fillOpacity: 0.88,
            weight: 2,
          }),
      });

      const titleLine = props.item_title
        ? `${props.item_title} (${props.category_name_bn || props.category_name_en || props.sector_code})`
        : `${props.d_form_code || ''} — ${props.administrative_unit_bn || props.administrative_unit || ''}`;

      layer.bindPopup(
        `<div style="min-width:210px">` +
          `<strong>${escapeHtml(titleLine)}</strong><br/>` +
          `<small class="text-muted">${escapeHtml(props.disaster_name_bn || props.disaster_name_en || '')}</small><br/>` +
          `<small>${escapeHtml(t('অবস্থা', 'Status'))}: ${escapeHtml(props.verification_status || '')} | ${escapeHtml(t('তীব্রতা', 'Severity'))}: ${escapeHtml(props.severity_level || '')}</small><br/>` +
          `<small>${escapeHtml(t('ক্ষতিগ্রস্ত পরিবার', 'Affected HH'))}: ${Number(props.affected_households || 0).toLocaleString()}</small><br/>` +
          `<small>${escapeHtml(t('আর্থিক ক্ষতি', 'Est. Loss'))}: ৳ ${Number(props.total_estimated_loss_bdt || props.estimated_loss_bdt || 0).toLocaleString()}</small>` +
        `</div>`
      );

      layer.on('click', () => {
        if (onSelectRef.current && props.id) {
          onSelectRef.current(props.id);
        }
      });

      layer.addTo(group);
    });

    if (mode === 'explore') {
      const bounds = group.getBounds();
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
      }
    }
  }, [geojson, showInfrastructure, mode, t]);

  const handleFinishPolygon = () => {
    if (draftVertices.length < 3 || !onBoundaryParsed) return;
    const closed = [...draftVertices, draftVertices[0]];
    onBoundaryParsed({
      type: 'Polygon',
      coordinates: [closed],
    });
    setDraftVertices([]);
  };

  const handleFileUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploadError('');
    try {
      const result = await parseSpatialFile(file);
      if (onBoundaryParsed && result.geometry) {
        onBoundaryParsed(result.geometry, result.centroid);
      }
    } catch (err) {
      setUploadError(err.message || t('ফাইল পার্স করা যায়নি।', 'Failed to parse spatial file.'));
    } finally {
      e.target.value = '';
    }
  };

  return (
    <div>
      {mode === 'pick' && (
        <div className="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <div className="btn-group btn-group-sm">
            <button
              type="button"
              className={`btn ${drawTool === 'point' ? 'btn-danger' : 'btn-outline-secondary'}`}
              onClick={() => setDrawTool('point')}
            >
              <i className="bi bi-geo-alt me-1"></i>
              {t('পয়েন্ট নির্বাচন (GPS)', 'Pick Point')}
            </button>
            <button
              type="button"
              className={`btn ${drawTool === 'polygon' ? 'btn-primary' : 'btn-outline-secondary'}`}
              onClick={() => setDrawTool('polygon')}
            >
              <i className="bi bi-pentagon me-1"></i>
              {t('ক্ষতিগ্রস্ত এলাকা আঁকুন', 'Draw Damage Polygon')}
            </button>
          </div>

          <div className="d-flex flex-wrap gap-2 align-items-center">
            {drawTool === 'polygon' && draftVertices.length >= 3 && (
              <button type="button" className="btn btn-sm btn-success" onClick={handleFinishPolygon}>
                <i className="bi bi-check2 me-1"></i>
                {t('পলিগন সম্পন্ন করুন', 'Complete Polygon')}
              </button>
            )}
            {draftVertices.length > 0 && (
              <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => setDraftVertices([])}>
                {t('মুছুন', 'Clear Draft')}
              </button>
            )}
            <button
              type="button"
              className="btn btn-sm btn-outline-primary"
              onClick={() => fileInputRef.current?.click()}
            >
              <i className="bi bi-upload me-1"></i>
              {t('GeoJSON / Shapefile (.zip)', 'Upload GeoJSON / Shapefile')}
            </button>
            <input
              ref={fileInputRef}
              type="file"
              accept=".geojson,.json,.zip"
              className="d-none"
              onChange={handleFileUpload}
            />
          </div>
        </div>
      )}

      {uploadError && <div className="alert alert-danger py-1 small mb-2">{uploadError}</div>}

      <div
        ref={containerRef}
        style={{ height, width: '100%', borderRadius: '8px', border: '1px solid #dee2e6', zIndex: 1 }}
      />
    </div>
  );
};

export default DamageGisMap;
