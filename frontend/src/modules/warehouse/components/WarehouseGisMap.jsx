import React, { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useLanguage } from '../../../contexts/LanguageContext';

const BANGLADESH_CENTER = [23.685, 90.3563];

const TIER_COLORS = {
  CENTRAL_CSD: '#0d6efd',
  REGIONAL_LSD: '#0284c7',
  DISTRICT_GODOWN: '#16a34a',
  UPAZILA_STORE: '#6c757d',
};

export const WarehouseGisMap = ({
  geojson = null,
  height = '420px',
  mode = 'explore',
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

    if (pickerLat && pickerLng && !Number.isNaN(Number(pickerLat)) && !Number.isNaN(Number(pickerLng))) {
      const lat = Number(pickerLat);
      const lng = Number(pickerLng);
      pickerMarkerRef.current = L.circleMarker([lat, lng], {
        radius: 9,
        color: '#b91c1c',
        fillColor: '#ef4444',
        fillOpacity: 0.85,
        weight: 2,
      })
        .bindPopup(`${lat.toFixed(5)}, ${lng.toFixed(5)}`)
        .addTo(map);
      map.setView([lat, lng], Math.max(map.getZoom(), 9));
    }
  }, [mode, pickerLat, pickerLng]);

  useEffect(() => {
    const group = layerGroupRef.current;
    const map = mapRef.current;
    if (!group || !map || mode !== 'explore') return;

    group.clearLayers();

    const features = geojson?.features || [];
    if (!features.length) return;

    const bounds = [];

    features.forEach((f) => {
      const coords = f.geometry?.coordinates;
      if (!coords || coords.length < 2) return;
      const latlng = [coords[1], coords[0]];
      const p = f.properties || {};
      const color = TIER_COLORS[p.tier] || '#0d6efd';

      const marker = L.circleMarker(latlng, {
        radius: 8,
        color: '#ffffff',
        weight: 2,
        fillColor: color,
        fillOpacity: 0.9,
      });

      marker.bindPopup(
        `<div style="min-width:190px">` +
          `<div class="fw-bold">${t(p.warehouse_name_bn || p.warehouse_name, p.warehouse_name)}</div>` +
          `<div class="small text-muted">${p.warehouse_code} • ${p.tier}</div>` +
          `<div class="small mt-1">${t('ক্ষমতা', 'Capacity')}: <strong>${Number(p.capacity_mt || 0).toLocaleString()} MT</strong></div>` +
          `<div class="small">${t('মোট মজুদ', 'Physical Stock')}: <strong>${Number(p.total_current_quantity || 0).toLocaleString()}</strong></div>` +
          `<div class="small">${t('উপলব্ধ মজুদ', 'Available')}: <strong>${Number(p.total_available_quantity || 0).toLocaleString()}</strong></div>` +
        `</div>`
      );

      marker.addTo(group);
      bounds.push(latlng);
    });

    if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [30, 30], maxZoom: 11 });
    }
  }, [geojson, mode, t]);

  return (
    <div className="border rounded overflow-hidden shadow-sm">
      <div ref={containerRef} style={{ height, width: '100%' }} />
      {mode === 'pick' && (
        <div className="bg-light px-3 py-2 small text-muted border-top">
          <i className="bi bi-crosshair me-1 text-danger"></i>
          {t('গুদামের জিপিএস অবস্থান নির্ধারণ করতে মানচিত্রে ক্লিক করুন।', 'Click on the map to capture the warehouse GPS coordinates.')}
        </div>
      )}
    </div>
  );
};
