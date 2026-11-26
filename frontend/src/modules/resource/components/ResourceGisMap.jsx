import React, { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useLanguage } from '../../../contexts/LanguageContext';

const BANGLADESH_CENTER = [23.685, 90.3563];

const STATUS_COLORS = {
  AVAILABLE: '#16a34a',
  ASSIGNED: '#0d6efd',
  IN_USE: '#0284c7',
  DEPLOYED: '#0284c7',
  UNDER_MAINTENANCE: '#d97706',
  MAINTENANCE: '#d97706',
  DAMAGED: '#dc2626',
  RETIRED: '#4b5563',
};

export const ResourceGisMap = ({
  geojson = null,
  height = '480px',
  mode = 'explore', // 'explore' | 'pick'
  pickerLat = null,
  pickerLng = null,
  onPickPoint = null,
  onSelectAsset = null,
}) => {
  const { t } = useLanguage();
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const layerGroupRef = useRef(null);
  const pickerMarkerRef = useRef(null);
  const onPickRef = useRef(onPickPoint);
  const onSelectRef = useRef(onSelectAsset);

  const [showAssets, setShowAssets] = useState(true);
  const [showAllocations, setShowAllocations] = useState(true);

  useEffect(() => {
    onPickRef.current = onPickPoint;
    onSelectRef.current = onSelectAsset;
  }, [onPickPoint, onSelectAsset]);

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

    layerGroupRef.current = L.layerGroup().addTo(map);
    mapRef.current = map;

    if (mode === 'pick') {
      map.on('click', (e) => {
        if (onPickRef.current) {
          onPickRef.current(
            Number(e.latlng.lat.toFixed(6)),
            Number(e.latlng.lng.toFixed(6))
          );
        }
      });
    }

    return () => {
      map.remove();
      mapRef.current = null;
      layerGroupRef.current = null;
      pickerMarkerRef.current = null;
    };
  }, [mode]);

  useEffect(() => {
    const map = mapRef.current;
    if (!map || mode !== 'pick') return;

    if (pickerMarkerRef.current) {
      map.removeLayer(pickerMarkerRef.current);
      pickerMarkerRef.current = null;
    }

    if (
      pickerLat !== null &&
      pickerLng !== null &&
      pickerLat !== '' &&
      pickerLng !== '' &&
      !Number.isNaN(Number(pickerLat)) &&
      !Number.isNaN(Number(pickerLng))
    ) {
      pickerMarkerRef.current = L.circleMarker([Number(pickerLat), Number(pickerLng)], {
        radius: 9,
        color: '#0d6efd',
        fillColor: '#3b82f6',
        fillOpacity: 0.85,
      }).addTo(map);
    }
  }, [mode, pickerLat, pickerLng]);

  useEffect(() => {
    const map = mapRef.current;
    const group = layerGroupRef.current;
    if (!map || !group || mode === 'pick') return;

    group.clearLayers();
    const features = geojson?.features || [];
    if (features.length === 0) return;

    const bounds = [];

    features.forEach((feat) => {
      const props = feat.properties || {};
      const ftype = props.feature_type;
      if (ftype === 'resource_asset' && !showAssets) return;
      if (ftype === 'active_allocation' && !showAllocations) return;

      const coords = feat.geometry?.coordinates;
      if (!Array.isArray(coords) || coords.length < 2) return;
      const latLng = [coords[1], coords[0]];
      bounds.push(latLng);

      const isAlloc = ftype === 'active_allocation';
      const color = isAlloc ? '#7c3aed' : (STATUS_COLORS[props.status] || '#6b7280');

      const marker = L.circleMarker(latLng, {
        radius: isAlloc ? 9 : 8,
        color,
        weight: 2,
        fillColor: color,
        fillOpacity: isAlloc ? 0.55 : 0.8,
      });

      const title = isAlloc
        ? `${props.allocation_no} — ${props.asset_code || ''}`
        : `${props.asset_code} — ${t(props.name_bn || props.name_en, props.name_en)}`;

      const detailLines = isAlloc
        ? [
            `<div><strong>${t('অবস্থান', 'Location')}:</strong> ${props.allocation_location || '-'}</div>`,
            props.disaster_code ? `<div><strong>${t('দুর্যোগ', 'Disaster')}:</strong> ${props.disaster_code}</div>` : '',
            `<div><strong>${t('অবস্থা', 'Status')}:</strong> ${props.status}</div>`,
          ]
        : [
            `<div><strong>${t('ধরন', 'Category')}:</strong> ${t(props.category_name_bn || props.category_name_en || '-', props.category_name_en || '-')}</div>`,
            `<div><strong>${t('সংস্থা', 'Org')}:</strong> ${props.owning_organization || '-'}</div>`,
            `<div><strong>${t('অবস্থান', 'Location')}:</strong> ${props.current_location || props.unit_name_en || '-'}</div>`,
            `<div><strong>${t('অবস্থা', 'Status')}:</strong> ${props.status}</div>`,
          ];

      marker.bindPopup(`
        <div style="min-width:200px;font-size:12px;">
          <div style="font-weight:700;margin-bottom:4px;">${title}</div>
          ${detailLines.join('')}
          ${props.location_masked ? `<div class="text-muted mt-1"><em>${t('গোপনীয়তা সুরক্ষিত অবস্থান', 'Privacy-masked coordinates')}</em></div>` : ''}
        </div>
      `);

      if (!isAlloc && onSelectRef.current && props.id) {
        marker.on('dblclick', () => onSelectRef.current(props.id));
      }

      marker.addTo(group);
    });

    if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
    }
  }, [geojson, mode, showAssets, showAllocations, t]);

  return (
    <div>
      {mode === 'explore' && (
        <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2 small">
          <div className="d-flex flex-wrap gap-3">
            <label className="form-check-label d-inline-flex align-items-center gap-1">
              <input type="checkbox" className="form-check-input" checked={showAssets} onChange={(e) => setShowAssets(e.target.checked)} />
              <span>{t('নিবন্ধিত সম্পদ', 'Registered Resources')}</span>
            </label>
            <label className="form-check-label d-inline-flex align-items-center gap-1">
              <input type="checkbox" className="form-check-input" checked={showAllocations} onChange={(e) => setShowAllocations(e.target.checked)} />
              <span>{t('সক্রিয় বরাদ্দ ও মোতায়েন', 'Active Allocations')}</span>
            </label>
          </div>
          <div className="d-flex flex-wrap gap-2 text-muted">
            <span><span className="badge bg-success">&nbsp;</span> {t('উপলব্ধ', 'Available')}</span>
            <span><span className="badge bg-primary">&nbsp;</span> {t('বরাদ্দকৃত', 'Assigned')}</span>
            <span><span className="badge bg-warning">&nbsp;</span> {t('রক্ষণাবেক্ষণ', 'Maintenance')}</span>
            <span><span className="badge bg-danger">&nbsp;</span> {t('ক্ষতিগ্রস্ত', 'Damaged')}</span>
          </div>
        </div>
      )}
      {mode === 'pick' && (
        <p className="text-muted small mb-2">
          <i className="bi bi-geo-alt me-1 text-primary"></i>
          {t('মানচিত্রে ক্লিক করে সম্পদের বর্তমান জিপিএস অবস্থান নির্বাচন করুন।', 'Click on the map to set the resource GPS coordinates.')}
        </p>
      )}
      <div ref={containerRef} style={{ width: '100%', height, borderRadius: '8px', border: '1px solid #dee2e6' }} />
    </div>
  );
};

export default ResourceGisMap;
