import React, { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useLanguage } from '../../../contexts/LanguageContext';
import { parseSpatialFile } from '../utils/shapefileParser';

const SEVERITY_COLORS = {
  ADVISORY: '#0dcaf0',
  WARNING: '#ffc107',
  DANGER: '#fd7e14',
  GREAT_DANGER: '#dc3545',
  CATASTROPHIC: '#6f1d1b',
};

const BANGLADESH_CENTER = [23.685, 90.3563];

/**
 * Interactive Leaflet GIS Map Component for IDMS Disaster Module.
 * Supports:
 * - Explorer mode: renders GeoJSON points & polygons, rich popups, OSM + Topo + optional GeoServer WMS,
 *   bounding-box filtering, and Shapefile (.zip) / GeoJSON overlay import.
 * - Picker mode: click to set epicenter lat/lng, click-to-draw polygon boundary, or upload Shapefile (.zip) / GeoJSON.
 */
export const DisasterGisMap = ({
  geojson = null,
  height = '440px',
  mode = 'explore', // 'explore' | 'pick'
  isFetching = false,
  pickerLat = null,
  pickerLng = null,
  pickerPolygonGeoJson = null,
  onPickPoint = null,
  onDrawPolygon = null,
  onShapefileParsed = null,
  onBoundingBoxSearch = null,
  onSelectDisaster = null,
}) => {
  const { t } = useLanguage();
  const containerRef = useRef(null);
  const mapRef = useRef(null);
  const dataLayerRef = useRef(null);
  const overlayLayerRef = useRef(null);
  const drawLayerRef = useRef(null);
  const wmsLayerRef = useRef(null);
  const fileInputRef = useRef(null);

  const [drawTool, setDrawTool] = useState('point'); // 'point' | 'polygon'
  const [draftVertices, setDraftVertices] = useState([]);
  const [showWms, setShowWms] = useState(false);
  const [uploadError, setUploadError] = useState('');
  const [uploadedOverlayInfo, setUploadedOverlayInfo] = useState(null);

  const drawToolRef = useRef(drawTool);
  const onPickPointRef = useRef(onPickPoint);
  const onSelectDisasterRef = useRef(onSelectDisaster);

  useEffect(() => {
    drawToolRef.current = drawTool;
  }, [drawTool]);

  useEffect(() => {
    onPickPointRef.current = onPickPoint;
  }, [onPickPoint]);

  useEffect(() => {
    onSelectDisasterRef.current = onSelectDisaster;
  }, [onSelectDisaster]);

  const geoserverUrl = import.meta.env.VITE_GEOSERVER_URL || '';

  // 1. Initialize Leaflet Map once
  useEffect(() => {
    if (!containerRef.current || mapRef.current) return;

    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19,
    });

    const topoLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenTopoMap',
      maxZoom: 17,
    });

    const map = L.map(containerRef.current, {
      center: BANGLADESH_CENTER,
      zoom: 7,
      layers: [osmLayer],
    });

    L.control.layers(
      {
        'OpenStreetMap': osmLayer,
        'Topographic': topoLayer,
      },
      {},
      { position: 'topright' }
    ).addTo(map);

    if (geoserverUrl) {
      wmsLayerRef.current = L.tileLayer.wms(`${geoserverUrl}/idms/wms`, {
        layers: 'idms:admin_districts',
        format: 'image/png',
        transparent: true,
      });
    }

    const dataLayer = L.featureGroup().addTo(map);
    const overlayLayer = L.featureGroup().addTo(map);
    const drawLayer = L.featureGroup().addTo(map);

    dataLayerRef.current = dataLayer;
    overlayLayerRef.current = overlayLayer;
    drawLayerRef.current = drawLayer;

    map.on('click', (e) => {
      if (mode !== 'pick') return;
      const lat = parseFloat(e.latlng.lat.toFixed(6));
      const lng = parseFloat(e.latlng.lng.toFixed(6));

      if (drawToolRef.current === 'point' && onPickPointRef.current) {
        onPickPointRef.current({ latitude: lat, longitude: lng });
      } else if (drawToolRef.current === 'polygon') {
        setDraftVertices((prev) => [...prev, [lng, lat]]);
      }
    });

    mapRef.current = map;

    return () => {
      map.remove();
      mapRef.current = null;
      dataLayerRef.current = null;
      overlayLayerRef.current = null;
      drawLayerRef.current = null;
    };
  }, [geoserverUrl, mode]);

  // 2. Toggle optional GeoServer WMS
  useEffect(() => {
    const map = mapRef.current;
    const wms = wmsLayerRef.current;
    if (!map || !wms) return;
    if (showWms) {
      wms.addTo(map);
    } else if (map.hasLayer(wms)) {
      map.removeLayer(wms);
    }
  }, [showWms]);

  // 3. Render Explorer GeoJSON OR Picker point/polygon
  useEffect(() => {
    const map = mapRef.current;
    const layerGroup = dataLayerRef.current;
    if (!map || !layerGroup) return;

    layerGroup.clearLayers();

    if (mode === 'explore') {
      if (geojson && Array.isArray(geojson.features) && geojson.features.length > 0) {
        const geoLayer = L.geoJSON(geojson, {
          style: (feature) => {
            const sev = feature?.properties?.severity_level || 'WARNING';
            const color = SEVERITY_COLORS[sev] || '#dc3545';
            return {
              color,
              weight: 2.5,
              fillColor: color,
              fillOpacity: 0.22,
            };
          },
          pointToLayer: (feature, latlng) => {
            const sev = feature?.properties?.severity_level || 'WARNING';
            const color = SEVERITY_COLORS[sev] || '#dc3545';
            return L.circleMarker(latlng, {
              radius: 8,
              color: '#ffffff',
              weight: 2,
              fillColor: color,
              fillOpacity: 0.92,
            });
          },
          onEachFeature: (feature, layer) => {
            const p = feature.properties || {};
            const container = document.createElement('div');
            container.style.minWidth = '210px';
            container.innerHTML = `
              <div class="small">
                <div class="fw-bold text-primary font-monospace">${p.disaster_code || ''}</div>
                <div class="fw-bold mb-1">${p.name_bn || p.name_en || ''}</div>
                <div class="text-muted mb-1">${p.disaster_type || ''} • ${p.severity_level || ''} • ${p.lifecycle_phase || ''}</div>
                <div class="mb-2">Affected Pop: <strong>${Number(p.affected_population || 0).toLocaleString()}</strong></div>
              </div>
            `;

            if (onSelectDisasterRef.current && p.id) {
              const btn = document.createElement('button');
              btn.type = 'button';
              btn.className = 'btn btn-primary btn-sm w-100 py-1';
              btn.textContent = 'বিস্তারিত দেখুন / Open Details';
              btn.onclick = () => onSelectDisasterRef.current(p.id);
              container.appendChild(btn);
            }

            layer.bindPopup(container);
          },
        });

        geoLayer.addTo(layerGroup);
        const bounds = geoLayer.getBounds();
        if (bounds.isValid()) {
          map.fitBounds(bounds, { padding: [35, 35], maxZoom: 11 });
        }
      }
      return;
    }

    // Picker mode rendering
    if (pickerPolygonGeoJson) {
      try {
        L.geoJSON(
          { type: 'Feature', geometry: pickerPolygonGeoJson },
          {
            style: {
              color: '#dc3545',
              weight: 2.5,
              fillColor: '#dc3545',
              fillOpacity: 0.25,
            },
          }
        ).addTo(layerGroup);
      } catch {
        // ignore invalid geometry preview
      }
    }

    if (
      pickerLat !== null &&
      pickerLng !== null &&
      pickerLat !== '' &&
      pickerLng !== '' &&
      !Number.isNaN(Number(pickerLat)) &&
      !Number.isNaN(Number(pickerLng))
    ) {
      L.circleMarker([Number(pickerLat), Number(pickerLng)], {
        radius: 9,
        color: '#ffffff',
        weight: 2,
        fillColor: '#dc3545',
        fillOpacity: 0.95,
      })
        .bindTooltip(`Epicenter: ${pickerLat}, ${pickerLng}`)
        .addTo(layerGroup);
    }

    const bounds = layerGroup.getBounds();
    if (bounds.isValid()) {
      map.fitBounds(bounds, { padding: [35, 35], maxZoom: 11 });
    }
  }, [geojson, mode, pickerLat, pickerLng, pickerPolygonGeoJson]);

  // 4. Render in-progress polygon vertices in Picker mode
  useEffect(() => {
    const drawLayer = drawLayerRef.current;
    if (!drawLayer) return;
    drawLayer.clearLayers();

    if (draftVertices.length === 0) return;

    const latLngs = draftVertices.map(([lng, lat]) => [lat, lng]);
    latLngs.forEach((ll) => {
      L.circleMarker(ll, {
        radius: 5,
        color: '#0d6efd',
        fillColor: '#ffffff',
        fillOpacity: 1,
        weight: 2,
      }).addTo(drawLayer);
    });

    if (latLngs.length >= 2) {
      L.polyline(latLngs, { color: '#0d6efd', dashArray: '5, 5', weight: 2 }).addTo(drawLayer);
    }
  }, [draftVertices]);

  const handleFinishPolygon = () => {
    if (draftVertices.length < 3 || !onDrawPolygon) return;
    const ring = [...draftVertices, draftVertices[0]];
    onDrawPolygon({
      type: 'Polygon',
      coordinates: [ring],
    });
    setDraftVertices([]);
    setDrawTool('point');
  };

  const handleShapefileSelect = async (e) => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    setUploadError('');

    try {
      const parsed = await parseSpatialFile(file);

      if (mode === 'pick') {
        if (onDrawPolygon) onDrawPolygon(parsed.geometry);
        if (parsed.centroid && onPickPoint && (!pickerLat || !pickerLng)) {
          onPickPoint(parsed.centroid);
        }
        if (onShapefileParsed) onShapefileParsed(parsed, file);
      } else {
        // Explorer overlay mode
        const overlayLayer = overlayLayerRef.current;
        const map = mapRef.current;
        if (overlayLayer && map) {
          overlayLayer.clearLayers();
          const layer = L.geoJSON(parsed.featureCollection, {
            style: {
              color: '#0d6efd',
              weight: 3,
              dashArray: '6, 4',
              fillColor: '#0d6efd',
              fillOpacity: 0.15,
            },
          }).addTo(overlayLayer);
          const bounds = layer.getBounds();
          if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
          }
        }
        setUploadedOverlayInfo(parsed);
        if (onShapefileParsed) onShapefileParsed(parsed, file);
      }
    } catch (err) {
      setUploadError(err?.message || 'Failed to parse Shapefile / GeoJSON.');
    }
  };

  const handleClearOverlay = () => {
    overlayLayerRef.current?.clearLayers();
    setUploadedOverlayInfo(null);
  };

  const handleSearchExtent = () => {
    const map = mapRef.current;
    if (!map || !onBoundingBoxSearch) return;
    const b = map.getBounds();
    onBoundingBoxSearch(
      `${b.getWest().toFixed(4)},${b.getSouth().toFixed(4)},${b.getEast().toFixed(4)},${b.getNorth().toFixed(4)}`
    );
  };

  const featureCount = geojson?.features?.length || 0;

  return (
    <div className="position-relative border rounded overflow-hidden shadow-sm bg-light">
      {/* Toolbar */}
      <div className="d-flex flex-wrap justify-content-between align-items-center px-3 py-2 bg-white border-bottom gap-2 small">
        <div className="d-flex align-items-center gap-2">
          <i className="bi bi-geo-alt-fill text-danger"></i>
          <strong>
            {mode === 'pick'
              ? t('লিফলেট জিআইএস লোকেশন ও শেপফাইল পিকার', 'Leaflet GIS Location & Shapefile Picker')
              : t('দুর্যোগ জিআইএস মানচিত্র (Leaflet)', 'Disaster GIS Map (Leaflet)')}
          </strong>
          {isFetching && <span className="spinner-border spinner-border-sm text-primary"></span>}
          {mode === 'explore' && !isFetching && (
            <span className="badge bg-light text-dark border">
              {featureCount} {t('টি জিআইএস ফিচার', 'features')}
            </span>
          )}
        </div>

        <div className="d-flex flex-wrap gap-2 align-items-center">
          {/* Hidden spatial file input (.zip shapefile or .geojson) */}
          <input
            ref={fileInputRef}
            type="file"
            accept=".zip,.geojson,.json"
            className="d-none"
            onChange={handleShapefileSelect}
          />

          <button
            type="button"
            className="btn btn-outline-success btn-sm"
            onClick={() => fileInputRef.current?.click()}
            title={t('জিপ করা শেপফাইল (.zip) অথবা GeoJSON আপলোড করুন', 'Upload zipped Shapefile (.zip) or GeoJSON')}
          >
            <i className="bi bi-file-earmark-zip me-1"></i>
            {t('শেপফাইল আপলোড (.zip / GeoJSON)', 'Upload Shapefile (.zip / GeoJSON)')}
          </button>

          {mode === 'pick' && (
            <>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className={`btn ${drawTool === 'point' ? 'btn-primary' : 'btn-outline-primary'}`}
                  onClick={() => {
                    setDraftVertices([]);
                    setDrawTool('point');
                  }}
                >
                  <i className="bi bi-geo-alt me-1"></i>
                  {t('কেন্দ্রবিন্দু বাছাই', 'Pick Point')}
                </button>
                <button
                  type="button"
                  className={`btn ${drawTool === 'polygon' ? 'btn-primary' : 'btn-outline-primary'}`}
                  onClick={() => setDrawTool('polygon')}
                >
                  <i className="bi bi-bounding-box me-1"></i>
                  {t('এলাকা আঁকুন', 'Draw Polygon')}
                </button>
              </div>

              {drawTool === 'polygon' && draftVertices.length >= 3 && (
                <button type="button" className="btn btn-success btn-sm" onClick={handleFinishPolygon}>
                  <i className="bi bi-check2 me-1"></i>
                  {t('সীমানা সম্পন্ন করুন', 'Finish Polygon')} ({draftVertices.length})
                </button>
              )}

              {pickerPolygonGeoJson && onDrawPolygon && (
                <button
                  type="button"
                  className="btn btn-outline-danger btn-sm"
                  onClick={() => onDrawPolygon(null)}
                >
                  {t('সীমানা মুছুন', 'Clear Boundary')}
                </button>
              )}
            </>
          )}

          {mode === 'explore' && onBoundingBoxSearch && (
            <button type="button" className="btn btn-outline-primary btn-sm" onClick={handleSearchExtent}>
              <i className="bi bi-search me-1"></i>
              {t('মানচিত্র এলাকা ফিল্টার (BBox)', 'Filter Map Extent')}
            </button>
          )}

          {mode === 'explore' && uploadedOverlayInfo && (
            <>
              {onBoundingBoxSearch && uploadedOverlayInfo.bbox && (
                <button
                  type="button"
                  className="btn btn-info btn-sm text-white"
                  onClick={() => onBoundingBoxSearch(uploadedOverlayInfo.bbox)}
                >
                  {t('শেপফাইল এলাকায় খুঁজুন', 'Filter by Shapefile Area')}
                </button>
              )}
              <button type="button" className="btn btn-outline-secondary btn-sm" onClick={handleClearOverlay}>
                {t('ওভারলে মুছুন', 'Clear Shapefile')}
              </button>
            </>
          )}

          {geoserverUrl && (
            <div className="form-check form-switch mb-0">
              <input
                className="form-check-input"
                type="checkbox"
                id="toggleLeafletWms"
                checked={showWms}
                onChange={(e) => setShowWms(e.target.checked)}
              />
              <label className="form-check-label" htmlFor="toggleLeafletWms">
                GeoServer WMS
              </label>
            </div>
          )}
        </div>
      </div>

      {uploadError && (
        <div className="alert alert-danger rounded-0 py-1 px-3 mb-0 small">
          <i className="bi bi-exclamation-circle me-1"></i>
          {uploadError}
        </div>
      )}

      {uploadedOverlayInfo && mode === 'explore' && (
        <div className="alert alert-info rounded-0 py-1 px-3 mb-0 small d-flex justify-content-between align-items-center">
          <span>
            <i className="bi bi-layers-fill me-1"></i>
            {t('আপলোডকৃত শেপফাইল:', 'Loaded Shapefile:')} <strong>{uploadedOverlayInfo.fileName}</strong> (
            {uploadedOverlayInfo.featureCount} {t('টি পলিগন', 'polygons')})
          </span>
        </div>
      )}

      {/* Map Canvas */}
      <div ref={containerRef} style={{ width: '100%', height, zIndex: 1 }} />

      {/* Empty overlay message when no features match in explorer mode */}
      {mode === 'explore' && !isFetching && featureCount === 0 && (
        <div
          className="position-absolute start-50 translate-middle-x bg-white border shadow-sm rounded px-3 py-1 small text-muted"
          style={{ bottom: '36px', zIndex: 500 }}
        >
          <i className="bi bi-info-circle me-1"></i>
          {t(
            'বর্তমান ফিল্টার অনুযায়ী মানচিত্রে দেখানোর মতো কোনো দুর্যোগ পাওয়া যায়নি।',
            'No disaster locations or polygons match the current filters.'
          )}
        </div>
      )}

      {/* Legend */}
      <div className="d-flex flex-wrap gap-3 px-3 py-1 bg-white border-top small text-muted">
        {Object.entries(SEVERITY_COLORS).map(([lvl, color]) => (
          <span key={lvl} className="d-inline-flex align-items-center gap-1">
            <span style={{ width: 10, height: 10, borderRadius: '50%', backgroundColor: color, display: 'inline-block' }}></span>
            {lvl}
          </span>
        ))}
      </div>
    </div>
  );
};

export default DisasterGisMap;
