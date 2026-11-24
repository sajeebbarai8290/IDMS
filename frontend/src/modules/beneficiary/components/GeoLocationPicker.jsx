import React, { useState } from 'react';

export const GeoLocationPicker = ({ latitude, longitude, onChange, disabled }) => {
  const [gpsLoading, setGpsLoading] = useState(false);
  const [gpsError, setGpsError] = useState(null);

  const handleGetCurrentLocation = () => {
    if (!navigator.geolocation) {
      setGpsError('আপনার ব্রাউজারে জিপিএস সুবিধা সমর্থিত নয়।');
      return;
    }

    setGpsLoading(true);
    setGpsError(null);

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setGpsLoading(false);
        onChange({
          latitude: parseFloat(pos.coords.latitude.toFixed(6)),
          longitude: parseFloat(pos.coords.longitude.toFixed(6)),
        });
      },
      (err) => {
        setGpsLoading(false);
        setGpsError('অবস্থান শনাক্ত করা যায়নি: ' + err.message);
      },
      { timeout: 10000, enableHighAccuracy: true }
    );
  };

  return (
    <div className="card bg-light border-0 p-3 rounded mb-3">
      <div className="d-flex justify-content-between align-items-center mb-2">
        <label className="form-label fw-bold mb-0">
          <i className="bi bi-geo-alt-fill text-danger me-1"></i>
          বসতবাড়ির ভৌগোলিক স্থানাঙ্ক (GPS Coordinates)
        </label>
        <button
          type="button"
          className="btn btn-outline-primary btn-sm"
          onClick={handleGetCurrentLocation}
          disabled={disabled || gpsLoading}
        >
          {gpsLoading ? (
            <>
              <span className="spinner-border spinner-border-sm me-1"></span>
              সংগ্রহ হচ্ছে...
            </>
          ) : (
            <>
              <i className="bi bi-crosshair me-1"></i>
              বর্তমান অবস্থান নিন (Capture GPS)
            </>
          )}
        </button>
      </div>

      {gpsError && <div className="alert alert-danger py-1 mb-2 small">{gpsError}</div>}

      <div className="row g-2">
        <div className="col-md-6">
          <div className="input-group input-group-sm">
            <span className="input-group-text bg-white">অক্ষাংশ (Lat)</span>
            <input
              type="number"
              step="0.000001"
              className="form-control"
              placeholder="e.g. 22.602145"
              value={latitude || ''}
              onChange={(e) => onChange({ latitude: e.target.value ? parseFloat(e.target.value) : null, longitude })}
              disabled={disabled}
            />
          </div>
        </div>
        <div className="col-md-6">
          <div className="input-group input-group-sm">
            <span className="input-group-text bg-white">দ্রাঘিমাংশ (Lng)</span>
            <input
              type="number"
              step="0.000001"
              className="form-control"
              placeholder="e.g. 89.531201"
              value={longitude || ''}
              onChange={(e) => onChange({ latitude, longitude: e.target.value ? parseFloat(e.target.value) : null })}
              disabled={disabled}
            />
          </div>
        </div>
      </div>

      {latitude && longitude && (
        <div className="mt-2 text-muted small d-flex justify-content-between align-items-center">
          <span>
            <i className="bi bi-pin-map text-success me-1"></i>
            স্থানাঙ্ক সেট করা হয়েছে: {latitude}, {longitude}
          </span>
          <a
            href={`https://www.google.com/maps?q=${latitude},${longitude}`}
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary text-decoration-none"
          >
            <i className="bi bi-box-arrow-up-right me-1"></i>
            ম্যাপে দেখুন (View on Map)
          </a>
        </div>
      )}
    </div>
  );
};

export default GeoLocationPicker;
