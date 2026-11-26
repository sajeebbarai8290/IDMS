import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { WarehouseGisMap } from '../components/WarehouseGisMap';
import { formatWarehouseApiError } from '../utils/warehousePermissions';

const buildInitialForm = (wh) => ({
  administrative_unit_id: wh?.administrative_unit_id || '',
  warehouse_code: wh?.warehouse_code || '',
  warehouse_name: wh?.warehouse_name || '',
  warehouse_name_bn: wh?.warehouse_name_bn || '',
  tier: wh?.tier || 'DISTRICT_GODOWN',
  warehouse_type: wh?.warehouse_type || 'RELIEF_GODOWN',
  owning_organization: wh?.owning_organization || '',
  responsible_officer_name: wh?.responsible_officer_name || '',
  responsible_officer_contact: wh?.responsible_officer_contact || '',
  address: wh?.address || '',
  contact_phone: wh?.contact_phone || '',
  contact_email: wh?.contact_email || '',
  capacity_mt: wh?.capacity_mt ?? '',
  latitude: wh?.latitude ?? '',
  longitude: wh?.longitude ?? '',
  is_active: wh?.is_active ?? true,
});

const WarehouseFacilityFormInner = ({ id, isEdit, initialFacility, lookups }) => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { t } = useLanguage();

  const [form, setForm] = useState(() => buildInitialForm(initialFacility));
  const [feedback, setFeedback] = useState(null);

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      isEdit ? warehouseApi.updateFacility(id, payload) : warehouseApi.createFacility(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      navigate('/warehouse/facilities');
    },
    onError: (err) => {
      setFeedback({
        variant: 'danger',
        message: formatWarehouseApiError(err, t('গুদাম সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save warehouse facility.')),
      });
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    saveMutation.mutate({
      ...form,
      capacity_mt: Number(form.capacity_mt),
      latitude: form.latitude === '' ? null : Number(form.latitude),
      longitude: form.longitude === '' ? null : Number(form.longitude),
    });
  };

  const adminUnits = lookups.administrative_units || [];
  const tiers = lookups.tiers || ['CENTRAL_CSD', 'REGIONAL_LSD', 'DISTRICT_GODOWN', 'UPAZILA_STORE'];
  const types = lookups.warehouse_types || ['RELIEF_GODOWN', 'COLD_STORAGE', 'FOOD_SILO', 'EMERGENCY_HUB', 'MEDICAL_DEPOT'];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h4 className="fw-bold mb-0">
          <i className="bi bi-building-add text-primary me-2"></i>
          {isEdit ? t('গুদাম তথ্য সম্পাদনা', 'Edit Warehouse Facility') : t('নতুন গুদাম নিবন্ধন', 'Register New Warehouse')}
        </h4>
        <Link to="/warehouse/facilities" className="btn btn-outline-secondary btn-sm">
          {t('ফিরে যান', 'Back to Facilities')}
        </Link>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <form onSubmit={handleSubmit}>
        <div className="row g-3">
          <div className="col-12 col-lg-7">
            <div className="card border-0 shadow-sm">
              <div className="card-body">
                <div className="row g-3">
                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('গুদাম কোড *', 'Warehouse Code *')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      required
                      value={form.warehouse_code}
                      onChange={(e) => setForm({ ...form, warehouse_code: e.target.value })}
                      placeholder="WH-KHL-02"
                    />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('স্তর (Tier) *', 'Warehouse Tier *')}</label>
                    <select
                      className="form-select form-select-sm"
                      required
                      value={form.tier}
                      onChange={(e) => setForm({ ...form, tier: e.target.value })}
                    >
                      {tiers.map((tr) => <option key={tr} value={tr}>{tr}</option>)}
                    </select>
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('গুদামের ধরন', 'Facility Type')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={form.warehouse_type}
                      onChange={(e) => setForm({ ...form, warehouse_type: e.target.value })}
                    >
                      {types.map((tp) => <option key={tp} value={tp}>{tp}</option>)}
                    </select>
                  </div>

                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('নাম (ইংরেজি) *', 'Warehouse Name (EN) *')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      required
                      value={form.warehouse_name}
                      onChange={(e) => setForm({ ...form, warehouse_name: e.target.value })}
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('নাম (বাংলা)', 'Warehouse Name (BN)')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={form.warehouse_name_bn}
                      onChange={(e) => setForm({ ...form, warehouse_name_bn: e.target.value })}
                    />
                  </div>

                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('প্রশাসনিক এলাকা *', 'Jurisdiction Unit *')}</label>
                    <select
                      className="form-select form-select-sm"
                      required
                      value={form.administrative_unit_id}
                      onChange={(e) => setForm({ ...form, administrative_unit_id: e.target.value })}
                    >
                      <option value="">{t('নির্বাচন করুন...', 'Select jurisdiction...')}</option>
                      {adminUnits.map((u) => (
                        <option key={u.id} value={u.id}>
                          [{u.tier_level}] {t(u.name_bn, u.name_en)}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('মালিকানাধীন সংস্থা', 'Owning Organization')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={form.owning_organization}
                      onChange={(e) => setForm({ ...form, owning_organization: e.target.value })}
                      placeholder="DDM / DRRO / PIO"
                    />
                  </div>

                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('ধারণক্ষমতা (MT) *', 'Capacity (MT) *')}</label>
                    <input
                      type="number"
                      step="0.01"
                      min="0.01"
                      className="form-control form-control-sm"
                      required
                      value={form.capacity_mt}
                      onChange={(e) => setForm({ ...form, capacity_mt: e.target.value })}
                    />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('দায়িত্বপ্রাপ্ত কর্মকর্তা', 'Responsible Officer')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={form.responsible_officer_name}
                      onChange={(e) => setForm({ ...form, responsible_officer_name: e.target.value })}
                    />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small fw-semibold">{t('কর্মকর্তার মোবাইল', 'Officer Contact')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={form.responsible_officer_contact}
                      onChange={(e) => setForm({ ...form, responsible_officer_contact: e.target.value })}
                    />
                  </div>

                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('গুদামের ফোন', 'Facility Phone')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={form.contact_phone}
                      onChange={(e) => setForm({ ...form, contact_phone: e.target.value })}
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small fw-semibold">{t('ইমেইল', 'Facility Email')}</label>
                    <input
                      type="email"
                      className="form-control form-control-sm"
                      value={form.contact_email}
                      onChange={(e) => setForm({ ...form, contact_email: e.target.value })}
                    />
                  </div>

                  <div className="col-12">
                    <label className="form-label small fw-semibold">{t('ঠিকানা', 'Physical Address')}</label>
                    <textarea
                      rows="2"
                      className="form-control form-control-sm"
                      value={form.address}
                      onChange={(e) => setForm({ ...form, address: e.target.value })}
                    />
                  </div>

                  <div className="col-md-5">
                    <label className="form-label small fw-semibold">{t('অক্ষাংশ (Latitude)', 'Latitude')}</label>
                    <input
                      type="number"
                      step="0.000001"
                      className="form-control form-control-sm"
                      value={form.latitude}
                      onChange={(e) => setForm({ ...form, latitude: e.target.value })}
                    />
                  </div>
                  <div className="col-md-5">
                    <label className="form-label small fw-semibold">{t('দ্রাঘিমাংশ (Longitude)', 'Longitude')}</label>
                    <input
                      type="number"
                      step="0.000001"
                      className="form-control form-control-sm"
                      value={form.longitude}
                      onChange={(e) => setForm({ ...form, longitude: e.target.value })}
                    />
                  </div>
                  <div className="col-md-2 d-flex align-items-end">
                    <div className="form-check">
                      <input
                        id="whActiveChk"
                        type="checkbox"
                        className="form-check-input"
                        checked={form.is_active}
                        onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                      />
                      <label htmlFor="whActiveChk" className="form-check-label small">{t('সক্রিয়', 'Active')}</label>
                    </div>
                  </div>
                </div>

                <div className="mt-4 d-flex justify-content-end gap-2">
                  <Link to="/warehouse/facilities" className="btn btn-light btn-sm">{t('বাতিল', 'Cancel')}</Link>
                  <button type="submit" className="btn btn-primary btn-sm" disabled={saveMutation.isPending}>
                    {saveMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('সংরক্ষণ করুন', 'Save Warehouse')}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-5">
            <WarehouseGisMap
              mode="pick"
              height="430px"
              pickerLat={form.latitude}
              pickerLng={form.longitude}
              onPickPoint={(lat, lng) => setForm((prev) => ({ ...prev, latitude: lat, longitude: lng }))}
            />
          </div>
        </div>
      </form>
    </div>
  );
};

export const WarehouseFacilityFormPage = () => {
  const { id } = useParams();
  const isEdit = Boolean(id);

  const { data: lookupsRes } = useQuery({
    queryKey: ['warehouse', 'lookups'],
    queryFn: () => warehouseApi.getLookups(),
  });

  const { data: detailRes, isLoading: loadingDetail } = useQuery({
    queryKey: ['warehouse', 'facility', id],
    queryFn: () => warehouseApi.getFacility(id),
    enabled: isEdit,
  });

  if (isEdit && loadingDetail) return <Loader />;

  return (
    <WarehouseFacilityFormInner
      key={detailRes?.data?.id || 'new'}
      id={id}
      isEdit={isEdit}
      initialFacility={detailRes?.data || null}
      lookups={lookupsRes?.data || {}}
    />
  );
};
