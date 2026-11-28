import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { volunteerApi } from '../../../api/volunteerApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { formatVolunteerApiError } from '../utils/volunteerPermissions';

export const VolunteerFormPage = () => {
  const { id } = useParams();
  const isEdit = !!id;
  const navigate = useNavigate();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [form, setForm] = useState({
    administrative_unit_id: '',
    organization_id: '',
    primary_category_id: '',
    full_name_en: '',
    full_name_bn: '',
    gender: 'MALE',
    date_of_birth: '',
    blood_group: '',
    nid_number: '',
    mobile_number: '',
    email: '',
    present_address: '',
    permanent_address: '',
    ward_village: '',
    emergency_contact_name: '',
    emergency_contact_relation: '',
    emergency_contact_phone: '',
    availability_status: 'AVAILABLE',
    location_consent_given: false,
    latitude: '',
    longitude: '',
    notes: '',
    submit_for_verification: true,
    initiate_nid_check: false,
    skills: [],
  });
  const [photoFile, setPhotoFile] = useState(null);
  const [errorMsg, setErrorMsg] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['volunteer', 'lookups'],
    queryFn: () => volunteerApi.getLookups(),
  });

  const { data: detailRes, isLoading: loadingDetail } = useQuery({
    queryKey: ['volunteer', 'detail', id],
    queryFn: () => volunteerApi.getVolunteer(id),
    enabled: isEdit,
  });

  useEffect(() => {
    const v = detailRes?.data;
    if (!v) return;
    setForm((prev) => ({
      ...prev,
      administrative_unit_id: v.administrative_unit_id || '',
      organization_id: v.organization_id || '',
      primary_category_id: v.primary_category_id || '',
      full_name_en: v.full_name_en || '',
      full_name_bn: v.full_name_bn || '',
      gender: v.gender || 'MALE',
      date_of_birth: v.date_of_birth || '',
      blood_group: v.blood_group || '',
      present_address: v.present_address || '',
      permanent_address: v.permanent_address || '',
      ward_village: v.ward_village || '',
      emergency_contact_name: v.emergency_contact_name || '',
      emergency_contact_relation: v.emergency_contact_relation || '',
      availability_status: v.availability_status || 'AVAILABLE',
      location_consent_given: !!v.location_consent_given,
      latitude: v.latitude ?? '',
      longitude: v.longitude ?? '',
      notes: v.notes || '',
      skills: (v.skills || []).map((s) => ({
        skill_id: s.skill_id,
        proficiency_level: s.proficiency_level || 'BASIC',
        certification_level: s.certification_level || '',
      })),
    }));
  }, [detailRes]);

  const saveMutation = useMutation({
    mutationFn: async (payload) => {
      const res = isEdit
        ? await volunteerApi.updateVolunteer(id, payload)
        : await volunteerApi.createVolunteer(payload);
      const savedId = res?.data?.id || id;
      if (photoFile && savedId) {
        const fd = new FormData();
        fd.append('photo', photoFile);
        await volunteerApi.uploadPhoto(savedId, fd);
      }
      return savedId;
    },
    onSuccess: (savedId) => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      navigate(`/volunteer/volunteers/${savedId}`);
    },
    onError: (err) => setErrorMsg(formatVolunteerApiError(err, t('সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save volunteer.'))),
  });

  const handleSkillToggle = (skillId) => {
    const exists = form.skills.some((s) => s.skill_id === skillId);
    setForm({
      ...form,
      skills: exists
        ? form.skills.filter((s) => s.skill_id !== skillId)
        : [...form.skills, { skill_id: skillId, proficiency_level: 'BASIC', certification_level: '' }],
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setErrorMsg(null);
    const payload = {
      ...form,
      organization_id: form.organization_id || null,
      primary_category_id: form.primary_category_id || null,
      date_of_birth: form.date_of_birth || null,
      nid_number: form.nid_number || undefined,
      mobile_number: form.mobile_number || undefined,
      emergency_contact_phone: form.emergency_contact_phone || undefined,
      latitude: form.location_consent_given && form.latitude !== '' ? Number(form.latitude) : null,
      longitude: form.location_consent_given && form.longitude !== '' ? Number(form.longitude) : null,
    };
    saveMutation.mutate(payload);
  };

  if (isEdit && loadingDetail) return <Loader />;
  const lookups = lookupsRes?.data || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h3 className="mb-0">
          {isEdit ? t('স্বেচ্ছাসেবক তথ্য হালনাগাদ', 'Update Volunteer Profile') : t('নতুন স্বেচ্ছাসেবক নিবন্ধন', 'Register New Volunteer')}
        </h3>
        <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => navigate(-1)}>
          {t('ফিরে যান', 'Back')}
        </button>
      </div>

      {errorMsg && <AlertBanner variant="danger" message={errorMsg} onClose={() => setErrorMsg(null)} />}

      <form onSubmit={handleSubmit} className="card shadow-sm">
        <div className="card-body">
          <div className="row g-3">
            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('পূর্ণ নাম (বাংলা)', 'Full Name (Bangla)')} *</label>
              <input className="form-control" required value={form.full_name_bn} onChange={(e) => setForm({ ...form, full_name_bn: e.target.value })} />
            </div>
            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('পূর্ণ নাম (ইংরেজি)', 'Full Name (English)')} *</label>
              <input className="form-control" required value={form.full_name_en} onChange={(e) => setForm({ ...form, full_name_en: e.target.value })} />
            </div>

            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('প্রশাসনিক ইউনিট', 'Administrative Unit')} *</label>
              <select className="form-select" required value={form.administrative_unit_id} onChange={(e) => setForm({ ...form, administrative_unit_id: e.target.value })}>
                <option value="">{t('নির্বাচন করুন', 'Select Unit')}</option>
                {(lookups.administrative_units || []).map((u) => (
                  <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)} ({u.tier_level})</option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('সংগঠন', 'Organization Affiliation')}</label>
              <select className="form-select" value={form.organization_id} onChange={(e) => setForm({ ...form, organization_id: e.target.value })}>
                <option value="">{t('স্বাধীন / কমিউনিটি', 'Independent / Community')}</option>
                {(lookups.organizations || []).map((o) => (
                  <option key={o.id} value={o.id}>{o.name_en} ({o.org_type})</option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('মূল ক্যাটাগরি', 'Primary Category')}</label>
              <select className="form-select" value={form.primary_category_id} onChange={(e) => setForm({ ...form, primary_category_id: e.target.value })}>
                <option value="">{t('নির্বাচন করুন', 'Select Category')}</option>
                {(lookups.categories || []).map((c) => (
                  <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
                ))}
              </select>
            </div>

            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('লিঙ্গ', 'Gender')}</label>
              <select className="form-select" value={form.gender} onChange={(e) => setForm({ ...form, gender: e.target.value })}>
                {(lookups.genders || ['MALE', 'FEMALE', 'OTHER']).map((g) => <option key={g} value={g}>{g}</option>)}
              </select>
            </div>
            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('জন্ম তারিখ', 'Date of Birth')}</label>
              <input type="date" className="form-control" value={form.date_of_birth} onChange={(e) => setForm({ ...form, date_of_birth: e.target.value })} />
            </div>
            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('রক্তের গ্রুপ', 'Blood Group')}</label>
              <input className="form-control" value={form.blood_group} onChange={(e) => setForm({ ...form, blood_group: e.target.value })} placeholder="O+" />
            </div>
            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('প্রাপ্যতা অবস্থা', 'Availability Status')}</label>
              <select className="form-select" value={form.availability_status} onChange={(e) => setForm({ ...form, availability_status: e.target.value })}>
                {(lookups.availability_statuses || ['AVAILABLE', 'STANDBY']).map((s) => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>

            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('জাতীয় পরিচয়পত্র (NID)', 'National ID (NID)')}</label>
              <input
                className="form-control"
                value={form.nid_number}
                onChange={(e) => setForm({ ...form, nid_number: e.target.value })}
                placeholder={isEdit ? t('পরিবর্তন করতে চাইলে লিখুন', 'Leave blank to keep existing') : '10/13/17 digits'}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('মোবাইল নম্বর', 'Mobile Number')} {!isEdit && '*'}</label>
              <input
                className="form-control"
                required={!isEdit}
                value={form.mobile_number}
                onChange={(e) => setForm({ ...form, mobile_number: e.target.value })}
                placeholder="017XXXXXXXX"
              />
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('ইমেইল', 'Email')}</label>
              <input type="email" className="form-control" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
            </div>

            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('বর্তমান ঠিকানা', 'Present Address')}</label>
              <input className="form-control" value={form.present_address} onChange={(e) => setForm({ ...form, present_address: e.target.value })} />
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('স্থায়ী ঠিকানা', 'Permanent Address')}</label>
              <input className="form-control" value={form.permanent_address} onChange={(e) => setForm({ ...form, permanent_address: e.target.value })} />
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('ওয়ার্ড / গ্রাম', 'Ward / Village')}</label>
              <input className="form-control" value={form.ward_village} onChange={(e) => setForm({ ...form, ward_village: e.target.value })} />
            </div>

            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('জরুরি যোগাযোগের নাম', 'Emergency Contact Name')} *</label>
              <input className="form-control" required value={form.emergency_contact_name} onChange={(e) => setForm({ ...form, emergency_contact_name: e.target.value })} />
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('সম্পর্ক', 'Relationship')}</label>
              <input className="form-control" value={form.emergency_contact_relation} onChange={(e) => setForm({ ...form, emergency_contact_relation: e.target.value })} />
            </div>
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('জরুরি যোগাযোগ নম্বর', 'Emergency Contact Phone')} {!isEdit && '*'}</label>
              <input className="form-control" required={!isEdit} value={form.emergency_contact_phone} onChange={(e) => setForm({ ...form, emergency_contact_phone: e.target.value })} />
            </div>

            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('স্বেচ্ছাসেবকের ছবি (প্রাইভেট স্টোরেজ)', 'Photograph (Private Secure Upload)')}</label>
              <input type="file" accept="image/jpeg,image/png,image/webp" className="form-control" onChange={(e) => setPhotoFile(e.target.files?.[0] || null)} />
            </div>

            <div className="col-md-6">
              <div className="form-check mt-4">
                <input
                  id="volConsentCheck"
                  className="form-check-input"
                  type="checkbox"
                  checked={form.location_consent_given}
                  onChange={(e) => setForm({ ...form, location_consent_given: e.target.checked })}
                />
                <label className="form-check-label small" htmlFor="volConsentCheck">
                  {t('স্বেচ্ছাসেবকের সম্মতিতে বেস অবস্থান (GPS) সংরক্ষণ করুন', 'Store volunteer base GPS coordinates with explicit consent')}
                </label>
              </div>
              {form.location_consent_given && (
                <div className="row g-2 mt-1">
                  <div className="col-6">
                    <input type="number" step="0.000001" className="form-control form-control-sm" placeholder="Latitude" value={form.latitude} onChange={(e) => setForm({ ...form, latitude: e.target.value })} />
                  </div>
                  <div className="col-6">
                    <input type="number" step="0.000001" className="form-control form-control-sm" placeholder="Longitude" value={form.longitude} onChange={(e) => setForm({ ...form, longitude: e.target.value })} />
                  </div>
                </div>
              )}
            </div>

            <div className="col-12">
              <label className="form-label small fw-semibold">{t('দক্ষতা ও যোগ্যতাসমূহ', 'Skills & Qualifications')}</label>
              <div className="d-flex flex-wrap gap-2">
                {(lookups.skills || []).map((sk) => {
                  const selected = form.skills.some((s) => s.skill_id === sk.id);
                  return (
                    <button
                      key={sk.id}
                      type="button"
                      className={`btn btn-sm ${selected ? 'btn-primary' : 'btn-outline-secondary'}`}
                      onClick={() => handleSkillToggle(sk.id)}
                    >
                      {selected && <i className="bi bi-check2 me-1"></i>}
                      {t(sk.name_bn, sk.name_en)}
                    </button>
                  );
                })}
              </div>
            </div>

            {!isEdit && (
              <div className="col-12 d-flex flex-wrap gap-4">
                <div className="form-check">
                  <input id="submitVerifyCheck" className="form-check-input" type="checkbox" checked={form.submit_for_verification} onChange={(e) => setForm({ ...form, submit_for_verification: e.target.checked })} />
                  <label className="form-check-label small" htmlFor="submitVerifyCheck">{t('নিবন্ধনের সাথে সাথে যাচাইয়ের জন্য জমা দিন', 'Submit for 4-eye verification immediately')}</label>
                </div>
                <div className="form-check">
                  <input id="nidCheckToggle" className="form-check-input" type="checkbox" checked={form.initiate_nid_check} onChange={(e) => setForm({ ...form, initiate_nid_check: e.target.checked })} />
                  <label className="form-check-label small" htmlFor="nidCheckToggle">{t('এনআইডি যাচাই অ্যাডাপ্টার (M02) চালান', 'Run NID verification adapter (M02) if NID provided')}</label>
                </div>
              </div>
            )}
          </div>
        </div>
        <div className="card-footer bg-white text-end">
          <button type="submit" className="btn btn-primary" disabled={saveMutation.isPending}>
            {saveMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('সংরক্ষণ করুন', 'Save Volunteer')}
          </button>
        </div>
      </form>
    </div>
  );
};

export default VolunteerFormPage;
