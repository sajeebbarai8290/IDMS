import shp from 'shpjs';

/**
 * Recursively collect all Polygon coordinate arrays from any GeoJSON structure.
 */
const collectPolygons = (node, acc) => {
  if (!node || typeof node !== 'object') return;
  const type = node.type;

  if (type === 'Polygon' && Array.isArray(node.coordinates) && node.coordinates.length > 0) {
    acc.push(node.coordinates);
  } else if (type === 'MultiPolygon' && Array.isArray(node.coordinates)) {
    node.coordinates.forEach((poly) => {
      if (Array.isArray(poly) && poly.length > 0) acc.push(poly);
    });
  } else if (type === 'Feature' && node.geometry) {
    collectPolygons(node.geometry, acc);
  } else if (type === 'FeatureCollection' && Array.isArray(node.features)) {
    node.features.forEach((f) => collectPolygons(f, acc));
  } else if (type === 'GeometryCollection' && Array.isArray(node.geometries)) {
    node.geometries.forEach((g) => collectPolygons(g, acc));
  }
};

/**
 * Compute bounding box [minLng, minLat, maxLng, maxLat] and center [lat, lng] from polygon rings.
 */
const computeBoundsFromPolygons = (polygons) => {
  let minLng = Infinity;
  let minLat = Infinity;
  let maxLng = -Infinity;
  let maxLat = -Infinity;

  const visitCoord = (coord) => {
    if (!Array.isArray(coord) || coord.length < 2) return;
    if (typeof coord[0] === 'number' && typeof coord[1] === 'number') {
      minLng = Math.min(minLng, coord[0]);
      maxLng = Math.max(maxLng, coord[0]);
      minLat = Math.min(minLat, coord[1]);
      maxLat = Math.max(maxLat, coord[1]);
    } else {
      coord.forEach(visitCoord);
    }
  };

  polygons.forEach(visitCoord);

  if (!Number.isFinite(minLng)) return null;

  return {
    bbox: `${minLng.toFixed(4)},${minLat.toFixed(4)},${maxLng.toFixed(4)},${maxLat.toFixed(4)}`,
    centroid: {
      latitude: parseFloat(((minLat + maxLat) / 2).toFixed(6)),
      longitude: parseFloat(((minLng + maxLng) / 2).toFixed(6)),
    },
  };
};

/**
 * Parse a Shapefile (.zip containing .shp/.dbf/.prj) or GeoJSON (.geojson/.json) File
 * into normalized Polygon/MultiPolygon geometry and FeatureCollection for Leaflet + PostGIS.
 */
export const parseSpatialFile = async (file) => {
  if (!file) {
    throw new Error('No file selected.');
  }

  const name = (file.name || '').toLowerCase();
  let parsed;

  if (name.endsWith('.zip')) {
    const buffer = await file.arrayBuffer();
    const result = await shp(buffer);
    if (Array.isArray(result)) {
      parsed = {
        type: 'FeatureCollection',
        features: result.flatMap((fc) => fc?.features || []),
      };
    } else {
      parsed = result;
    }
  } else if (name.endsWith('.geojson') || name.endsWith('.json')) {
    const text = await file.text();
    parsed = JSON.parse(text);
  } else {
    throw new Error(
      'Unsupported spatial file format. Upload a zipped Shapefile (.zip) or GeoJSON (.geojson / .json).'
    );
  }

  const polygons = [];
  collectPolygons(parsed, polygons);

  if (polygons.length === 0) {
    throw new Error(
      'No Polygon or MultiPolygon features found inside the uploaded Shapefile / GeoJSON.'
    );
  }

  const geometry =
    polygons.length === 1
      ? { type: 'Polygon', coordinates: polygons[0] }
      : { type: 'MultiPolygon', coordinates: polygons };

  const boundsInfo = computeBoundsFromPolygons(polygons);

  return {
    fileName: file.name,
    geometry,
    featureCollection: {
      type: 'FeatureCollection',
      features: [
        {
          type: 'Feature',
          geometry,
          properties: { source: file.name },
        },
      ],
    },
    featureCount: polygons.length,
    bbox: boundsInfo?.bbox || null,
    centroid: boundsInfo?.centroid || null,
  };
};

export default parseSpatialFile;
