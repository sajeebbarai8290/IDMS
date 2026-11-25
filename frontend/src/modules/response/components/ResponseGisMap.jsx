import React, { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useLanguage } from '../../../contexts/LanguageContext';

const BANGLADESH_CENTER = [23.685, 90.3563];

export const ResponseGisMap = ({
  geojson = null,
  height = '450px',
  mode = 'explore', // 'explore' | 'pick'
  pickerLat = null,
  pickerLng = null,
  onPickPoint = null,
}) => {
  const { t } = useLanguage();
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const layerGroupRef = useRef(null);
  const pickerMarkerRef = useRef(null);
  const onPickRef = useRef(onPickPoint);

  const [showAffected, setShowAffected] = useState(true);
  const [showCenters, setShowCenters] = useState(true);
  const [showTeams, setShowTeams] = useState(true);
  const [showIncidents, setShowIncidents] = useState(true);

  useEffect(() => {
    onPickRef.current = onPickPoint;
  }, [onPickPoint]);

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

    if (pickerLat !== null && pickerLng !== null && !Number.isNaN(Number(pickerLat)) && !Number.isNaN(Number(pickerLng))) {
      pickerMarkerRef.current = L.circleMarker([Number(pickerLat), Number(pickerLng)], {
        radius: 8,
        color: '#dc2626',
        fillColor: '#ef4444',
        fillOpacity: 0.85,
      }).addTo(map);
    }
  }, [mode, pickerLat, pickerLng]);

  useEffect(() => {
    const map = mapRef.current;
    const group = layerGroupRef.current;
    if (!map || !group) return;

    group.clearLayers();
    const features = geojson?.features || [];
    if (features.length === 0) return;

    const bounds = [];

    features.forEach((feat) => {
      const props = feat.properties || {};
      const type = props.feature_type;

      if (type === 'affected_area' && !showAffected) return;
      if (type === 'response_center' && !showCenters) return;
      if (type === 'response_team' && !showTeams) return;
      if (type === 'situation_report' && !showIncidents) return;

      const colorMap = {
        affected_area: '#f97316',
        response_center: '#0284c7',
        response_team: '#16a34a',
        situation_report: '#dc2626',
      };
      const color = colorMap[type] || '#6b7280';

      const layer = L.geoJSON(feat, {
        style: () => ({
          color,
          weight: 2,
          fillColor: color,
          fillOpacity: 0.18,
        }),
        pointToLayer: (_, latlng) => {
          bounds.push([latlng.lat, latlng.lng]);
          return L.circleMarker(latlng, {
            radius: type === 'response_center' ? 9 : 7,
            color: '#ffffff',
            weight: 2,
            fillColor: color,
            fillOpacity: 0.9,
          });
        },
        onEachFeature: (_, l) => {
          const title = t(props.title_bn || props.title_en, props.title_en || '');
          const privacyBadge = props.location_masked
            ? `<div class="badge bg-warning text-dark mt-1">${t('গোপনীয়তার জন্য আনুমানিক অবস্থান', 'Privacy-Masked (~1.1km)')}</div>`
            : '';
          l.bindPopup(`
            <div style="min-width:190px">
              <div class="fw-bold">${title}</div>
              ${props.code ? `<div class="small text-muted">${props.code}</div>` : ''}
              ${props.status ? `<div class="small">Status: <strong>${props.status}</strong></div>` : ''}
              ${props.location_description ? `<div class="small mt-1">${props.location_description}</div>` : ''}
              ${privacyBadge}
            </div>
          `);
        },
      });

      layer.addTo(group);
      try {
        const b = layer.getBounds();
        if (b && b.isValid()) {
          bounds.push(b.getSouthWest());
          bounds.push(b.getNorthEast());
        }
      } catch {
        // Ignore single point layers without bounds
      }
    });

    if (mode === 'explore' && bounds.length > 0) {
      map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
    }
  }, [geojson, showAffected, showCenters, showTeams, showIncidents, mode, t]);

  return (
    <div>
      {mode === 'explore' && (
        <div className="d-flex flex-wrap gap-3 mb-2 small bg-light p-2 rounded border">
          <label className="form-check-label d-flex align-items-center gap-1">
            <input type="checkbox" checked={showAffected} onChange={(e) => setShowAffected(e.target.checked)} />
            <span style={{ color: '#f97316' }}>●</span> {t('দুর্যোগ আক্রান্ত এলাকা', 'Affected Areas')}
          </label>
          <label className="form-check-label d-flex align-items-center gap-1">
            <input type="checkbox" checked={showCenters} onChange={(e) => setShowCenters(e.target.checked)} />
            <span style={{ color: '#0284c7' }}>●</span> {t('সাড়াদান কেন্দ্র (EOC)', 'Response Centers')}
          </label>
          <label className="form-check-label d-flex align-items-center gap-1">
            <input type="checkbox" checked={showTeams} onChange={(e) => setShowTeams(e.target.checked)} />
            <span style={{ color: '#16a34a' }}>●</span> {t('সাড়াদান দল', 'Response Teams')}
          </label>
          <label className="form-check-label d-flex align-items-center gap-1">
            <input type="checkbox" checked={showIncidents} onChange={(e) => setShowIncidents(e.target.checked)} />
            <span style={{ color: '#dc2626' }}>●</span> {t('জরুরি ঘটনা / সিটরেপ', 'Incidents / SitReps')}
          </label>
        </div>
      )}
      <div ref={containerRef} style={{ height, width: '100%', borderRadius: '8px', border: '1px solid #dee2e6' }} />
    </div>
  );
};

export default ResponseGisMap;
