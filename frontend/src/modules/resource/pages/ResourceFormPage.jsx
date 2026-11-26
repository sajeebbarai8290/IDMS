import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { resourceApi } from '../../../api/resourceApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ResourceGisMap from '../components/ResourceGisMap';
import { formatResourceApiError, RESOURCE_STATUS_META } from '../utils/resourcePermissions';

export const ResourceFormPage = () => {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [feedback, setFeedback] = useState(null);
  const [form, setForm] = useState({
    category_id: '',
    administrative_unit_id: '',
    asset_code: '',
    resource_type: '',
    name_en: '',
    name_bn: '',
    description: '',
    registration_no: '',
    capacity_info: '',
    specifications_text: '',
    owning_organization: '',
    current_location: '',
    responsible_officer_name: '',
    responsible_officer_contact: '',
    acquisition_date: '',
    acquisition_value_bdt: '',
    next_maintenance_due_at: '',
    status: 'AVAILABLE',
    latitude: '',
    longitude: '',
  });

  const { data: lookupsRes } = useQuery({
    queryKey: ['resource', 'lookups'],
    queryFn: () => resourceApi.getLookups(),
  });

  const { data: assetRes, isLoading: loadingAsset } = useQuery({
    queryKey: ['resource', 'assets', id],
    queryFn: () => resourceApi.getAsset(id),
    enabled: isEdit,
  });

  useEffect(() => {
    if (isEdit && assetRes?.data) {
      const a = assetRes.data;
      setForm({
        category_id: a.category_id || '',
        administrative_unit_id: a.administrative_unit_id || '',
        asset_code: a.asset_code || '',
        resource_type: a.resource_type || a.asset_type || '',
        name_en: a.name_en || '',
        name_bn: a.name_bn || '',
        description: a.description || '',
        registration_no: a.registration_no || '',
        capacity_info: a.capacity_info || '',
        specifications_text: a.specifications && Object.keys(a.specifications).length > 0 ? JSON.stringify(a.specifications, null, 2) : '',
        owning_organization: a.owning_organization || '',
        current_location: a.current_location || '',
        responsible_officer_name: a.responsible_officer_name || '',
        responsible_officer_contact: a.responsible_officer_contact || '',
        acquisition_date: a.acquisition_date || '',
        acquisition_value_bdt: a.acquisition_value_bdt ?? '',
        next_maintenance_due_at: a.next_maintenance_due_at || '',
        status: a.status || 'AVAILABLE',
        latitude: a.latitude ?? '',
        longitude: a.longitude ?? '',
      });
    }
  }, [isEdit, assetRes]);

  const mutation = useMutation({
    mutationFn: (payload) => (isEdit ? resourceApi.updateAsset(id, payload) : resourceApi.createAsset(payload)),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['resource'] });
      navigate(`/resource/assets/${res.data.id}`);
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatResourceApiError(err, t('সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save resource.')) });
    },
  });

  if (isEdit && loadingAsset) return <Loader />;

  const categories = lookupsRes?.data?.categories || [];
  const adminUnits = lookupsRes?.data?.administrative_units || [];

  const handleSubmit = (e) => {
    e.preventDefault();
    let parsedSpecs = null;
    if (form.specifications_text.trim()) {
      try {
        parsedSpecs = JSON.parse(form.specifications_text);
      } catch {
        parsedSpecs = { details: form.specifications_text.trim() };
      }
    }

    const payload = {
      category_id: form.category_id,
      administrative_unit_id: form.administrative_unit_id,
      asset_code: form.asset_code,
      resource_type: form.resource_type,
      name_en: form.name_en,
      name_bn: form.name_bn,
      description: form.description || null,
      registration_no: form.registration_no || null,
      capacity_info: form.capacity_info || null,
      specifications: parsedSpecs,
      owning_organization: form.owning_organization,
      current_location: form.current_location,
      responsible_officer_name: form.responsible_officer_name,
      responsible_officer_contact: form.responsible_officer_contact || null,
      acquisition_date: form.acquisition_date || null,
      acquisition_value_bdt: form.acquisition_value_bdt !== '' ? Number(form.acquisition_value_bdt) : null,
      next_maintenance_due_at: form.next_maintenance_due_at || null,
      ...(isEdit ? { status: form.status } : {}),
      latitude: form.latitude !== '' ? Number(form.latitude) : null,
      longitude: form.longitude !== '' ? Number(form.longitude) : null,
    };

    mutation.mutate(payload);
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h4 className="fw-bold mb-0">
          <i className="bi bi-pencil-square text-primary me-2"></i>
          {isEdit ? t('সম্পদ তথ্য হালনাগাদ', 'Update Resource Asset') : t('নতুন সম্পদ নিবন্ধন', 'Register Disaster Resource')}
        </h4>
        <Link to="/resource/assets" className="btn btn-sm btn-outline-secondary">
          {t('তালিকায় ফিরুন', 'Back to Register')}
        </Link>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <form onSubmit={handleSubmit} className="row g-3">
        <div className="col-lg-7">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white fw-bold">{t('মৌলিক ও প্রযুক্তিগত তথ্য', 'Registration & Specification Details')}</div>
            <div className="card-body row g-3">
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('অনন্য সম্পদ কোড *', 'Unique Asset Code *')}</label>
                <input
                  className="form-control form-control-sm"
                  required
                  placeholder="e.g. RES-BOAT-KHL-05"
                  value={form.asset_code}
                  onChange={(e) => setForm((p) => ({ ...p, asset_code: e.target.value.toUpperCase() }))}
                />
              </div>
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('শ্রেণি *', 'Category *')}</label>
                <select className="form-select form-select-sm" required value={form.category_id} onChange={(e) => setForm((p) => ({ ...p, category_id: e.target.value }))}>
                  <option value="">{t('নির্বাচন করুন...', 'Select category...')}</option>
                  {categories.map((c) => (
                    <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
                  ))}
                </select>
              </div>
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('সম্পদের ধরন *', 'Resource Type *')}</label>
                <input
                  className="form-control form-control-sm"
                  required
                  placeholder="e.g. SPEEDBOAT, 4WD_TRUCK"
                  value={form.resource_type}
                  onChange={(e) => setForm((p) => ({ ...p, resource_type: e.target.value }))}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('ইংরেজি নাম *', 'Name (English) *')}</label>
                <input className="form-control form-control-sm" required value={form.name_en} onChange={(e) => setForm((p) => ({ ...p, name_en: e.target.value }))} />
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('বাংলা নাম *', 'Name (Bangla) *')}</label>
                <input className="form-control form-control-sm" required value={form.name_bn} onChange={(e) => setForm((p) => ({ ...p, name_bn: e.target.value }))} />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('মালিকানাধীন সংস্থা *', 'Owning Organization *')}</label>
                <input className="form-control form-control-sm" required value={form.owning_organization} onChange={(e) => setForm((p) => ({ ...p, owning_organization: e.target.value }))} />
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('প্রশাসনিক অধিক্ষেত্র *', 'Administrative Jurisdiction *')}</label>
                <select className="form-select form-select-sm" required value={form.administrative_unit_id} onChange={(e) => setForm((p) => ({ ...p, administrative_unit_id: e.target.value }))}>
                  <option value="">{t('অধিক্ষেত্র নির্বাচন করুন...', 'Select administrative unit...')}</option>
                  {adminUnits.map((u) => (
                    <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)} ({u.tier_level})</option>
                  ))}
                </select>
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('বর্তমান অবস্থান *', 'Current Location *')}</label>
                <input className="form-control form-control-sm" required value={form.current_location} onChange={(e) => setForm((p) => ({ ...p, current_location: e.target.value }))} />
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('নিবন্ধন নম্বর', 'Registration No.')}</label>
                <input className="form-control form-control-sm" value={form.registration_no} onChange={(e) => setForm((p) => ({ ...p, registration_no: e.target.value }))} />
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('ধারণক্ষমতা', 'Capacity Info')}</label>
                <input className="form-control form-control-sm" value={form.capacity_info} onChange={(e) => setForm((p) => ({ ...p, capacity_info: e.target.value }))} />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('দায়িত্বপ্রাপ্ত কর্মকর্তা *', 'Responsible Officer *')}</label>
                <input className="form-control form-control-sm" required value={form.responsible_officer_name} onChange={(e) => setForm((p) => ({ ...p, responsible_officer_name: e.target.value }))} />
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('যোগাযোগ নম্বর', 'Officer Contact')}</label>
                <input className="form-control form-control-sm" value={form.responsible_officer_contact} onChange={(e) => setForm((p) => ({ ...p, responsible_officer_contact: e.target.value }))} />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('সংগ্রহের তারিখ', 'Acquisition Date')}</label>
                <input type="date" className="form-control form-control-sm" value={form.acquisition_date} onChange={(e) => setForm((p) => ({ ...p, acquisition_date: e.target.value }))} />
              </div>
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('মূল্য (টাকা)', 'Acquisition Value (BDT)')}</label>
                <input type="number" min="0" step="0.01" className="form-control form-control-sm" value={form.acquisition_value_bdt} onChange={(e) => setForm((p) => ({ ...p, acquisition_value_bdt: e.target.value }))} />
              </div>
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('পরবর্তী রক্ষণাবেক্ষণ তারিখ', 'Next Maintenance Due')}</label>
                <input type="date" className="form-control form-control-sm" value={form.next_maintenance_due_at} onChange={(e) => setForm((p) => ({ ...p, next_maintenance_due_at: e.target.value }))} />
              </div>

              {isEdit && (
                <div className="col-md-4">
                  <label className="form-label small fw-semibold">{t('প্রাপ্যতা অবস্থা', 'Availability Status')}</label>
                  <select className="form-select form-select-sm" value={form.status} onChange={(e) => setForm((p) => ({ ...p, status: e.target.value }))}>
                    {['AVAILABLE', 'ASSIGNED', 'IN_USE', 'UNDER_MAINTENANCE', 'DAMAGED', 'RETIRED'].map((s) => (
                      <option key={s} value={s}>{t(RESOURCE_STATUS_META[s]?.bn || s, RESOURCE_STATUS_META[s]?.en || s)}</option>
                    ))}
                  </select>
                </div>
              )}

              <div className="col-12">
                <label className="form-label small fw-semibold">{t('বিবরণ', 'Description')}</label>
                <textarea className="form-control form-control-sm" rows={2} value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} />
              </div>

              <div className="col-12">
                <label className="form-label small fw-semibold">{t('কারিগরি স্পেসিফিকেশন (JSON বা টেক্সট)', 'Specifications (JSON or text)')}</label>
                <textarea
                  className="form-control form-control-sm font-monospace"
                  rows={3}
                  placeholder='{"horsepower": 150, "fuel": "Diesel"}'
                  value={form.specifications_text}
                  onChange={(e) => setForm((p) => ({ ...p, specifications_text: e.target.value }))}
                />
              </div>
            </div>
            <div className="card-footer bg-white text-end">
              <button type="submit" className="btn btn-primary" disabled={mutation.isPending}>
                <i className="bi bi-check-circle me-1"></i>
                {isEdit ? t('হালনাগাদ করুন', 'Update Resource') : t('নিবন্ধন সম্পন্ন করুন', 'Register Resource')}
              </button>
            </div>
          </div>
        </div>

        <div className="col-lg-5">
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white fw-bold">{t('জিপিএস অবস্থান নির্বাচন', 'GPS Location Picker')}</div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                <div className="col-6">
                  <label className="form-label small">{t('অক্ষাংশ (Latitude)', 'Latitude')}</label>
                  <input type="number" step="0.000001" className="form-control form-control-sm" value={form.latitude} onChange={(e) => setForm((p) => ({ ...p, latitude: e.target.value }))} />
                </div>
                <div className="col-6">
                  <label className="form-label small">{t('দ্রাঘিমাংশ (Longitude)', 'Longitude')}</label>
                  <input type="number" step="0.000001" className="form-control form-control-sm" value={form.longitude} onChange={(e) => setForm((p) => ({ ...p, longitude: e.target.value }))} />
                </div>
              </div>
              <ResourceGisMap
                mode="pick"
                height="360px"
                pickerLat={form.latitude}
                pickerLng={form.longitude}
                onPickPoint={(lat, lng) => setForm((p) => ({ ...p, latitude: lat, longitude: lng }))}
              />
            </div>
          </div>
        </div>
      </form>
    </div>
  );
};

export default ResourceFormPage;
