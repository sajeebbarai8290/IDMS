import React, { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useLanguage } from '../../../contexts/LanguageContext';
import { parseSpatialFile } from '../../disaster/utils/shapefileParser';

const BANGLADESH_CENTER = [23.685, 90.3563];

export const ProjectGisMap = ({
  geojson = null,
  height = '420px',
  mode = 'explore', // 'explore' | 'pick'
  pickerLat = null,
  pickerLng = null,
  pickerBoundary = null,
  onPickPoint = null,
  onBoundaryParsed = null,
  onSelectProject = null,
}) => {
  const { t } = useLanguage();
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const dataLayerRef = useRef(null);
  const drawLayerRef = useRef(null);
  const fileInputRef = useRef(null);

  const [drawTool, setDrawTool] = useState('point'); // 'point' | 'polygon'
  const [draftVertices, setDraftVertices] = useState([]);
  const [uploadError, setUploadError] = useState('');

  const drawToolRef = useRef(drawTool);
  const onPickRef = useRef(onPickPoint);
  const onSelectRef = useRef(onSelectProject);

  useEffect(() => {
    drawToolRef.current = drawTool;
  }, [drawTool]);

  useEffect(() => {
    onPickRef.current = onPickPoint;
  }, [onPickPoint]);

  useEffect(() => {
    onSelectRef.current = onSelectProject;
  }, [onSelectProject]);

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

    dataLayerRef.current = L.featureGroup().addTo(map);
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
      dataLayerRef.current = null;
      drawLayerRef.current = null;
    };
  }, [mode]);

  useEffect(() => {
    const group = drawLayerRef.current;
    if (!group) return;
    group.clearLayers();

    if (pickerLat !== null && pickerLng !== null && !Number.isNaN(Number(pickerLat)) && !Number.isNaN(Number(pickerLng))) {
      L.circleMarker([Number(pickerLat), Number(pickerLng)], {
        radius: 8,
        color: '#0d6efd',
        fillColor: '#0d6efd',
        fillOpacity: 0.85,
      }).addTo(group);
    }

    if (pickerBoundary) {
      L.geoJSON(pickerBoundary, {
        style: () => ({ color: '#198754', weight: 2, fillColor: '#198754', fillOpacity: 0.2 }),
      }).addTo(group);
    }

    if (draftVertices.length > 0) {
      const latLngs = draftVertices.map(([lng, lat]) => [lat, lng]);
      if (latLngs.length >= 3) {
        L.polygon(latLngs, { color: '#fd7e14', weight: 2, fillOpacity: 0.2 }).addTo(group);
      } else {
        L.polyline(latLngs, { color: '#fd7e14', weight: 2 }).addTo(group);
      }
      latLngs.forEach((pt) => {
        L.circleMarker(pt, { radius: 4, color: '#fd7e14', fillOpacity: 1 }).addTo(group);
      });
    }
  }, [pickerLat, pickerLng, pickerBoundary, draftVertices]);

  useEffect(() => {
    const map = mapRef.current;
    const group = dataLayerRef.current;
    if (!map || !group) return;

    group.clearLayers();
    const features = geojson?.features || [];
    if (features.length === 0) return;

    features.forEach((feat) => {
      const props = feat.properties || {};
      const color = props.is_delayed ? '#dc3545' : (props.color_hex || '#0d6efd');

      const layer = L.geoJSON(feat, {
        style: () => ({
          color,
          weight: 2,
          fillColor: color,
          fillOpacity: 0.18,
        }),
        pointToLayer: (_, latlng) =>
          L.circleMarker(latlng, {
            radius: props.feature_type === 'project_location' ? 6 : 8,
            color,
            fillColor: color,
            fillOpacity: 0.85,
            weight: 2,
          }),
      });

      layer.bindPopup(
        `<div style="min-width:190px">` +
          `<strong>${props.project_code || ''}</strong><br/>` +
          `<span>${props.title_bn || props.title_en || ''}</span><br/>` +
          `<small class="text-muted">${props.administrative_unit || ''}</small><br/>` +
          `<small>Status: <b>${props.status || ''}</b> | Physical: <b>${props.physical_progress_pct ?? 0}%</b></small>` +
          `</div>`
      );

      if (onSelectRef.current && props.id) {
        layer.on('click', () => onSelectRef.current(props.id));
      }

      layer.addTo(group);
    });

    const bounds = group.getBounds();
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [25, 25], maxZoom: 12 });
    }
  }, [geojson]);

  const finishPolygon = () => {
    if (draftVertices.length < 3) return;
    const ring = [...draftVertices, draftVertices[0]];
    const geom = { type: 'Polygon', coordinates: [ring] };
    onBoundaryParsed?.(geom);
    setDraftVertices([]);
  };

  const handleFileUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setUploadError('');
    try {
      const parsed = await parseSpatialFile(file);
      onBoundaryParsed?.(parsed);
    } catch (err) {
      setUploadError(err.message || t('জিআইএস ফাইল পার্স করতে ব্যর্থ।', 'Failed to parse GIS file.'));
    } finally {
      e.target.value = '';
    }
  };

  return (
    <div>
      {mode === 'pick' && (
        <div className="d-flex flex-wrap align-items-center gap-2 mb-2">
          <div className="btn-group btn-group-sm">
            <button
              type="button"
              className={`btn ${drawTool === 'point' ? 'btn-primary' : 'btn-outline-primary'}`}
              onClick={() => setDrawTool('point')}
            >
              <i className="bi bi-geo-alt me-1"></i>
              {t('জিপিএস পিন', 'GPS Point')}
            </button>
            <button
              type="button"
              className={`btn ${drawTool === 'polygon' ? 'btn-warning' : 'btn-outline-warning'}`}
              onClick={() => setDrawTool('polygon')}
            >
              <i className="bi bi-bounding-box me-1"></i>
              {t('সীমানা আঁকুন', 'Draw Boundary')}
            </button>
          </div>

          {draftVertices.length >= 3 && (
            <button type="button" className="btn btn-sm btn-success" onClick={finishPolygon}>
              {t('পলিগন সম্পন্ন করুন', 'Complete Polygon')}
            </button>
          )}
          {draftVertices.length > 0 && (
            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setDraftVertices([])}>
              {t('বাতিল', 'Clear Draft')}
            </button>
          )}

          <input
            ref={fileInputRef}
            type="file"
            accept=".zip,.geojson,.json"
            className="d-none"
            onChange={handleFileUpload}
          />
          <button
            type="button"
            className="btn btn-sm btn-outline-success ms-auto"
            onClick={() => fileInputRef.current?.click()}
          >
            <i className="bi bi-upload me-1"></i>
            {t('শেপফাইল (.zip) / GeoJSON', 'Upload Shapefile (.zip) / GeoJSON')}
          </button>
        </div>
      )}

      {uploadError && <div className="alert alert-danger py-1 px-2 small">{uploadError}</div>}

      <div
        ref={containerRef}
        style={{ height, width: '100%', borderRadius: '6px', border: '1px solid #dee2e6' }}
      />
    </div>
  );
};

export default ProjectGisMap;
