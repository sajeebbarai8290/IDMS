import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import beneficiaryApi from '../../../api/beneficiaryApi';
import GeoLocationPicker from '../components/GeoLocationPicker';
import Loader from '../../../components/common/Loader';

export const BeneficiaryEditPage = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [formData, setFormData] = useState({
    hoh_name_en: '',
    hoh_name_bn: '',
    hoh_dob: '',
    hoh_gender: 'MALE',
    hoh_mobile: '',
    hoh_occupation: '',
    monthly_income_bdt: 0,
    present_address: '',
    permanent_address: '',
    village_ward_name: '',
    is_female_headed: false,
    has_elderly: false,
    has_disabled: false,
    has_children_infants: false,
    has_pregnant_lactating: false,
    is_displaced: false,
    latitude: null,
    longitude: null,
  });

  const { data, isLoading, error } = useQuery({
    queryKey: ['household', id],
    queryFn: async () => {
      const res = await beneficiaryApi.getHousehold(id);
      return res?.household || res?.data?.household || res?.data || res || null;
    },
  });

  useEffect(() => {
    if (data) {
      setFormData({
        hoh_name_en: data.hoh_name_en || '',
        hoh_name_bn: data.hoh_name_bn || '',
        hoh_dob: data.hoh_dob || '',
        hoh_gender: data.hoh_gender || 'MALE',
        hoh_mobile: '',
        hoh_occupation: data.hoh_occupation || '',
        monthly_income_bdt: data.monthly_income_bdt || 0,
        present_address: data.present_address || '',
        permanent_address: data.permanent_address || '',
        village_ward_name: data.village_ward_name || '',
        is_female_headed: Boolean(data.vulnerability?.is_female_headed),
        has_elderly: Boolean(data.vulnerability?.has_elderly),
        has_disabled: Boolean(data.vulnerability?.has_disabled),
        has_children_infants: Boolean(data.vulnerability?.has_children_infants),
        has_pregnant_lactating: Boolean(data.vulnerability?.has_pregnant_lactating),
        is_displaced: Boolean(data.vulnerability?.is_displaced),
        latitude: data.coordinates?.latitude || null,
        longitude: data.coordinates?.longitude || null,
      });
    }
  }, [data]);

  const updateMutation = useMutation({
    mutationFn: (payload) => beneficiaryApi.updateHousehold(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['household', id]);
      navigate(`/beneficiary/households/${id}`);
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const payload = {
      ...formData,
      monthly_income_bdt: parseFloat(formData.monthly_income_bdt || 0),
      latitude: formData.latitude ? parseFloat(formData.latitude) : null,
      longitude: formData.longitude ? parseFloat(formData.longitude) : null,
    };
    if (!payload.hoh_mobile) {
      delete payload.hoh_mobile;
    }
    updateMutation.mutate(payload);
  };

  if (isLoading) {
    return <Loader message="তথ্য লোড হচ্ছে..." />;
  }

  if (error || !data) {
    return (
      <div className="container py-4 text-center text-danger">
        পরিবারের তথ্য লোড করা যায়নি।
      </div>
    );
  }

  return (
    <div className="container py-2" style={{ maxWidth: '850px' }}>
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-pencil-square text-primary me-2"></i>
            উপকারভোগীর তথ্য সম্পাদন (Edit Household)
          </h4>
          <p className="text-muted small mb-0 font-monospace">পরিবার কোড: {data.household_code}</p>
        </div>
        <Link to={`/beneficiary/households/${id}`} className="btn btn-outline-secondary btn-sm">
          বাতিল
        </Link>
      </div>

      <div className="card shadow-sm border-0">
        <form onSubmit={handleSubmit} className="card-body p-4">
          <div className="row g-3">
            <div className="col-md-6">
              <label className="form-label fw-bold">প্রধানের নাম (ইংরেজি) *</label>
              <input
                type="text"
                className="form-control"
                value={formData.hoh_name_en}
                onChange={(e) => setFormData({ ...formData, hoh_name_en: e.target.value })}
                required
              />
            </div>
            <div className="col-md-6">
              <label className="form-label fw-bold">প্রধানের নাম (বাংলা) *</label>
              <input
                type="text"
                className="form-control"
                value={formData.hoh_name_bn}
                onChange={(e) => setFormData({ ...formData, hoh_name_bn: e.target.value })}
                required
              />
            </div>

            <div className="col-md-4">
              <label className="form-label fw-bold">লিঙ্গ</label>
              <select
                className="form-select"
                value={formData.hoh_gender}
                onChange={(e) => setFormData({ ...formData, hoh_gender: e.target.value })}
              >
                <option value="MALE">পুরুষ</option>
                <option value="FEMALE">নারী</option>
                <option value="OTHER">অন্যান্য</option>
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label fw-bold">জন্ম তারিখ</label>
              <input
                type="date"
                className="form-control"
                value={formData.hoh_dob}
                onChange={(e) => setFormData({ ...formData, hoh_dob: e.target.value })}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label fw-bold">নতুন মোবাইল নম্বর</label>
              <input
                type="text"
                className="form-control font-monospace"
                placeholder={data.hoh_mobile_masked || '017XXXXXXXX'}
                value={formData.hoh_mobile}
                onChange={(e) => setFormData({ ...formData, hoh_mobile: e.target.value })}
              />
            </div>

            <div className="col-md-6">
              <label className="form-label fw-bold">পেশা</label>
              <input
                type="text"
                className="form-control"
                value={formData.hoh_occupation}
                onChange={(e) => setFormData({ ...formData, hoh_occupation: e.target.value })}
              />
            </div>
            <div className="col-md-6">
              <label className="form-label fw-bold">মাসিক গড় আয় (টাকা)</label>
              <input
                type="number"
                className="form-control"
                value={formData.monthly_income_bdt}
                onChange={(e) => setFormData({ ...formData, monthly_income_bdt: e.target.value })}
              />
            </div>

            <div className="col-12">
              <label className="form-label fw-bold">বর্তমান ঠিকানা *</label>
              <textarea
                className="form-control"
                rows="2"
                value={formData.present_address}
                onChange={(e) => setFormData({ ...formData, present_address: e.target.value })}
                required
              ></textarea>
            </div>

            <div className="col-md-6">
              <label className="form-label fw-bold">গ্রাম / পাড়া / ওয়ার্ড</label>
              <input
                type="text"
                className="form-control"
                value={formData.village_ward_name}
                onChange={(e) => setFormData({ ...formData, village_ward_name: e.target.value })}
              />
            </div>

            <div className="col-12 mt-4">
              <h6 className="fw-bold border-bottom pb-2">সুরক্ষা ও ঝুঁকি নির্দেশকসমূহ</h6>
              <div className="row g-2 mt-1">
                <div className="col-md-4">
                  <div className="form-check form-switch">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id="edFemale"
                      checked={formData.is_female_headed}
                      onChange={(e) => setFormData({ ...formData, is_female_headed: e.target.checked })}
                    />
                    <label className="form-check-label small" htmlFor="edFemale">
                      নারী প্রধান পরিবার
                    </label>
                  </div>
                </div>
                <div className="col-md-4">
                  <div className="form-check form-switch">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id="edDisab"
                      checked={formData.has_disabled}
                      onChange={(e) => setFormData({ ...formData, has_disabled: e.target.checked })}
                    />
                    <label className="form-check-label small" htmlFor="edDisab">
                      প্রতিবন্ধী সদস্য রয়েছে
                    </label>
                  </div>
                </div>
                <div className="col-md-4">
                  <div className="form-check form-switch">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id="edElderly"
                      checked={formData.has_elderly}
                      onChange={(e) => setFormData({ ...formData, has_elderly: e.target.checked })}
                    />
                    <label className="form-check-label small" htmlFor="edElderly">
                      প্রবীণ সদস্য রয়েছে (&ge; ৬০)
                    </label>
                  </div>
                </div>
                <div className="col-md-4">
                  <div className="form-check form-switch">
                    <input
                      className="form-check-input"
                      type="checkbox"
                      id="edDispl"
                      checked={formData.is_displaced}
                      onChange={(e) => setFormData({ ...formData, is_displaced: e.target.checked })}
                    />
                    <label className="form-check-label small" htmlFor="edDispl">
                      বাস্তুচ্যুত পরিবার
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <div className="col-12 mt-4">
              <GeoLocationPicker
                latitude={formData.latitude}
                longitude={formData.longitude}
                onChange={({ latitude, longitude }) => setFormData({ ...formData, latitude, longitude })}
              />
            </div>
          </div>

          <div className="d-flex justify-content-end gap-2 mt-4">
            <Link to={`/beneficiary/households/${id}`} className="btn btn-secondary">
              বাতিল
            </Link>
            <button type="submit" className="btn btn-primary" disabled={updateMutation.isLoading}>
              {updateMutation.isLoading ? 'সংরক্ষণ হচ্ছে...' : 'হালনাগাদ নিশ্চিত করুন'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default BeneficiaryEditPage;
