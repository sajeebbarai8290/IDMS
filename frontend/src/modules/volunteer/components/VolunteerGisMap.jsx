import React, { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useLanguage } from '../../../contexts/LanguageContext';

const BANGLADESH_CENTER = [23.685, 90.3563];

export const VolunteerGisMap = ({ geojson = null, height = '420px' }) => {
  const { t } = useLanguage();
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const layerGroupRef = useRef(null);

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

    return () => {
      map.remove();
      mapRef.current = null;
      layerGroupRef.current = null;
    };
  }, []);

  useEffect(() => {
    const map = mapRef.current;
    const group = layerGroupRef.current;
    if (!map || !group) return;

    group.clearLayers();
    const features = geojson?.features || [];
    const bounds = [];

    features.forEach((f) => {
      const coords = f?.geometry?.coordinates;
      if (!Array.isArray(coords) || coords.length < 2) return;
      const [lng, lat] = coords;
      const props = f.properties || {};
      const isDeployment = props.feature_type === 'active_deployment';
      const color = isDeployment ? '#dc2626' : '#0d6efd';

      const marker = L.circleMarker([lat, lng], {
        radius: isDeployment ? 9 : 7,
        color,
        fillColor: color,
        fillOpacity: 0.75,
        weight: 2,
      });

      const title = isDeployment
        ? `${props.deployment_no || ''} (${props.assigned_role || ''})`
        : `${props.volunteer_code || ''} — ${props.full_name_en || ''}`;

      marker.bindPopup(`
        <div style="min-width:190px">
          <strong>${title}</strong><br/>
          <small>${props.unit_name_en || ''}</small><br/>
          <small>${isDeployment ? props.deployment_location || '' : props.organization_name || ''}</small><br/>
          ${props.location_masked ? `<span class="badge bg-secondary mt-1">${t('আংশিক গোপন অবস্থান', 'Generalized Location')}</span>` : ''}
        </div>
      `);

      marker.addTo(group);
      bounds.push([lat, lng]);
    });

    if (bounds.length > 0) {
      map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
    }
  }, [geojson, t]);

  return (
    <div className="border rounded overflow-hidden">
      <div ref={containerRef} style={{ height, width: '100%' }} />
    </div>
  );
};

export default VolunteerGisMap;
