import React, { useState, useEffect } from 'react';
import beneficiaryApi from '../../../api/beneficiaryApi';

export const BeneficiaryFilterBar = ({ filters, onFilterChange, onReset }) => {
  const [adminUnits, setAdminUnits] = useState([]);
  const [loadingUnits, setLoadingUnits] = useState(false);

  useEffect(() => {
    const fetchAdminUnits = async () => {
      setLoadingUnits(true);
      try {
        const res = await beneficiaryApi.getAdministrativeUnits();
        const list = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : res?.data?.data || [];
        setAdminUnits(list || []);
      } catch (err) {
        console.error('Failed to load administrative units:', err);
      } finally {
        setLoadingUnits(false);
      }
    };
    fetchAdminUnits();
  }, []);

  const handleChange = (key, value) => {
    onFilterChange({
      ...filters,
      [key]: value || undefined,
    });
  };

  return (
    <div className="card shadow-sm border-0 mb-4">
      <div className="card-body p-3">
        <div className="row g-2 align-items-center">
          {/* Search Input */}
          <div className="col-lg-3 col-md-6">
            <div className="input-group input-group-sm">
              <span className="input-group-text bg-white">
                <i className="bi bi-search text-muted"></i>
              </span>
              <input
                type="text"
                className="form-control"
                placeholder="নাম, কোড, এনআইডি বা ফোন দিয়ে খুঁজুন..."
                value={filters.search || ''}
                onChange={(e) => handleChange('search', e.target.value)}
              />
            </div>
          </div>

          {/* Verification Status */}
          <div className="col-lg-2 col-md-3 col-6">
            <select
              className="form-select form-select-sm"
              value={filters.verification_status || ''}
              onChange={(e) => handleChange('verification_status', e.target.value)}
            >
              <option value="">যাচাই অবস্থা (All Status)</option>
              <option value="DRAFT">খসড়া (Draft)</option>
              <option value="PENDING_VERIFICATION">অপেক্ষমান (Pending)</option>
              <option value="FIELD_VERIFIED">মাঠ যাচাইকৃত (Field Verified)</option>
              <option value="APPROVED">অনুমোদিত (Approved)</option>
              <option value="REJECTED">বাতিলকৃত (Rejected)</option>
            </select>
          </div>

          {/* Administrative Unit Filter */}
          <div className="col-lg-3 col-md-3 col-6">
            <select
              className="form-select form-select-sm"
              value={filters.administrative_unit_id || ''}
              onChange={(e) => handleChange('administrative_unit_id', e.target.value)}
              disabled={loadingUnits}
            >
              <option value="">সকল এলাকা (All Jurisdictions)</option>
              {adminUnits.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.tier_level === 'DIVISION' ? '━━ বিভাগ: ' : u.tier_level === 'DISTRICT' ? '  ━ জেলা: ' : u.tier_level === 'UPAZILA' ? '    উপজেলা: ' : '      ইউনিয়ন: '}
                  {u.name_bn || u.name_en} ({u.tier_level})
                </option>
              ))}
            </select>
          </div>

          {/* Vulnerability Minimum */}
          <div className="col-lg-2 col-md-4 col-6">
            <select
              className="form-select form-select-sm"
              value={filters.min_vulnerability || ''}
              onChange={(e) => handleChange('min_vulnerability', e.target.value)}
            >
              <option value="">ঝুঁকি স্তর (Vulnerability)</option>
              <option value="70">অতি ঝুঁকিপূর্ণ (&ge; ৭০ স্কোর)</option>
              <option value="40">মাঝারি ঝুঁকিপূর্ণ (&ge; ৪০ স্কোর)</option>
              <option value="1">যেকোনো স্কোর (&ge; ১)</option>
            </select>
          </div>

          {/* Gender */}
          <div className="col-lg-1 col-md-4 col-6">
            <select
              className="form-select form-select-sm"
              value={filters.hoh_gender || ''}
              onChange={(e) => handleChange('hoh_gender', e.target.value)}
            >
              <option value="">লিঙ্গ (Gender)</option>
              <option value="FEMALE">নারী (Female)</option>
              <option value="MALE">পুরুষ (Male)</option>
              <option value="OTHER">অন্যান্য (Other)</option>
            </select>
          </div>

          {/* Reset Action */}
          <div className="col-lg-1 col-md-4 col-12 text-end">
            <button
              type="button"
              className="btn btn-outline-secondary btn-sm w-100"
              onClick={onReset}
              title="ফিল্টার রিসেট করুন"
            >
              <i className="bi bi-arrow-counterclockwise me-1"></i>
              রিসেট
            </button>
          </div>
        </div>

        {/* Quick Checkbox Chips */}
        <div className="d-flex flex-wrap gap-2 mt-2 pt-2 border-top">
          <small className="text-muted align-self-center me-1">বিশেষ নির্দেশক:</small>
          <div className="form-check form-check-inline form-switch m-0">
            <input
              className="form-check-input"
              type="checkbox"
              id="chipFemaleHeaded"
              checked={Boolean(filters.female_headed_only)}
              onChange={(e) => handleChange('female_headed_only', e.target.checked ? true : undefined)}
            />
            <label className="form-check-label small" htmlFor="chipFemaleHeaded">
              নারী প্রধান পরিবার
            </label>
          </div>
          <div className="form-check form-check-inline form-switch m-0">
            <input
              className="form-check-input"
              type="checkbox"
              id="chipDisabled"
              checked={Boolean(filters.has_disabled_only)}
              onChange={(e) => handleChange('has_disabled_only', e.target.checked ? true : undefined)}
            />
            <label className="form-check-label small" htmlFor="chipDisabled">
              প্রতিবন্ধী ব্যক্তি রয়েছে
            </label>
          </div>
          <div className="form-check form-check-inline form-switch m-0">
            <input
              className="form-check-input"
              type="checkbox"
              id="chipElderly"
              checked={Boolean(filters.has_elderly_only)}
              onChange={(e) => handleChange('has_elderly_only', e.target.checked ? true : undefined)}
            />
            <label className="form-check-label small" htmlFor="chipElderly">
              বয়োবৃদ্ধ সদস্য (&ge; ৬০)
            </label>
          </div>
          <div className="form-check form-check-inline form-switch m-0">
            <input
              className="form-check-input"
              type="checkbox"
              id="chipDisplaced"
              checked={Boolean(filters.is_displaced_only)}
              onChange={(e) => handleChange('is_displaced_only', e.target.checked ? true : undefined)}
            />
            <label className="form-check-label small" htmlFor="chipDisplaced">
              বাস্তুচ্যুত পরিবার
            </label>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BeneficiaryFilterBar;
