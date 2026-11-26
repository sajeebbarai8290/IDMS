import React, { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { damageApi } from '../../../api/damageApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import DamageGisMap from '../components/DamageGisMap';
import {
  canSubmitDamage,
  DAMAGE_SEVERITY_META,
  DAMAGE_TYPE_META,
  formatBdt,
  formatDamageApiError,
} from '../utils/damagePermissions';

const emptyItem = (defaultCatId = '') => ({
  category_id: defaultCatId,
  item_title: '',
  unit_of_measure: 'UNITS',
  fully_damaged_count: 0,
  partially_damaged_count: 0,
  minor_damaged_count: 0,
  estimated_loss_bdt: 0,
  affected_households: 0,
  remarks: '',
});

const emptyLocation = () => ({
  administrative_unit_id: '',
  ward_no: '',
  locality_name_en: '',
  locality_name_bn: '',
  affected_households: 0,
  affected_population: 0,
  severity_level: 'MODERATE',
  latitude: '',
  longitude: '',
});

export const DamageAssessmentFormPage = () => {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const { t } = useLanguage();

  const [form, setForm] = useState({
    disaster_event_id: '',
    administrative_unit_id: user?.administrative_unit_id || '',
    d_form_code: '',
    reference_no: '',
    assessment_type: 'PRELIMINARY_D_FORM',
    assessment_date: new Date().toISOString().slice(0, 10),
    assessed_at: new Date().toISOString().slice(0, 16),
    assessing_organization: '',
    team_name: '',
    team_lead_name: user?.name || user?.full_name_en || '',
    assessor_name: user?.name || user?.full_name_en || '',
    assessor_designation: user?.administrative_profile?.designation || '',
    assessor_contact: '',
    ward_no: '',
    location_summary: '',
    latitude: '',
    longitude: '',
    boundary_geojson: null,
    severity_level: 'MODERATE',
    affected_households: 0,
    affected_population: 0,
    displaced_population: 0,
    deaths_count: 0,
    injured_count: 0,
    missing_count: 0,
    field_observations: '',
    sync_version: 1,
  });

  const [sectorItems, setSectorItems] = useState([emptyItem()]);
  const [locations, setLocations] = useState([]);
  const [pendingFiles, setPendingFiles] = useState([]);
  const [feedback, setFeedback] = useState(null);
  const [gpsLoading, setGpsLoading] = useState(false);

  const { data: lookupsRes, isLoading: loadingLookups } = useQuery({
    queryKey: ['damage', 'lookups'],
    queryFn: () => damageApi.getLookups(),
  });

  const { data: existingRes, isLoading: loadingExisting } = useQuery({
    queryKey: ['damage', 'assessment', id],
    queryFn: () => damageApi.getAssessment(id),
    enabled: isEdit,
  });

  const categories = useMemo(() => lookupsRes?.data?.categories || [], [lookupsRes]);
  const disasters = lookupsRes?.data?.disasters || [];
  const adminUnits = lookupsRes?.data?.administrative_units || [];

  useEffect(() => {
    if (!isEdit && categories.length > 0) {
      setSectorItems((prev) =>
        prev.length === 1 && !prev[0].category_id ? [emptyItem(categories[0].id)] : prev
      );
    }
  }, [isEdit, categories]);

  useEffect(() => {
    if (isEdit && existingRes?.data) {
      const a = existingRes.data;
      setForm({
        disaster_event_id: a.disaster_event_id || '',
        administrative_unit_id: a.administrative_unit_id || '',
        d_form_code: a.d_form_code || '',
        reference_no: a.reference_no || '',
        assessment_type: a.assessment_type || 'PRELIMINARY_D_FORM',
        assessment_date: a.assessment_date || new Date().toISOString().slice(0, 10),
        assessed_at: a.assessed_at ? a.assessed_at.slice(0, 16) : new Date().toISOString().slice(0, 16),
        assessing_organization: a.assessing_organization || '',
        team_name: a.team_name || '',
        team_lead_name: a.team_lead_name || '',
        assessor_name: a.assessor_name || '',
        assessor_designation: a.assessor_designation || '',
        assessor_contact: a.assessor_contact || '',
        ward_no: a.ward_no || '',
        location_summary: a.location_summary || '',
        latitude: a.latitude ?? '',
        longitude: a.longitude ?? '',
        boundary_geojson: a.boundary_geojson || null,
        severity_level: a.severity_level || 'MODERATE',
        affected_households: a.affected_households ?? 0,
        affected_population: a.affected_population ?? 0,
        displaced_population: a.displaced_population ?? 0,
        deaths_count: a.deaths_count ?? 0,
        injured_count: a.injured_count ?? 0,
        missing_count: a.missing_count ?? 0,
        field_observations: a.field_observations || '',
        sync_version: a.sync_version || 1,
      });

      if (Array.isArray(a.sector_items) && a.sector_items.length > 0) {
        setSectorItems(
          a.sector_items.map((item) => ({
            category_id: item.category_id,
            item_title: item.item_title || '',
            unit_of_measure: item.unit_of_measure || 'UNITS',
            fully_damaged_count: item.fully_damaged_count ?? 0,
            partially_damaged_count: item.partially_damaged_count ?? 0,
            minor_damaged_count: item.minor_damaged_count ?? 0,
            estimated_loss_bdt: item.estimated_loss_bdt ?? 0,
            affected_households: item.affected_households ?? 0,
            remarks: item.remarks || '',
          }))
        );
      }

      if (Array.isArray(a.locations) && a.locations.length > 0) {
        setLocations(
          a.locations.map((loc) => ({
            administrative_unit_id: loc.administrative_unit_id || '',
            ward_no: loc.ward_no || '',
            locality_name_en: loc.locality_name_en || '',
            locality_name_bn: loc.locality_name_bn || '',
            affected_households: loc.affected_households ?? 0,
            affected_population: loc.affected_population ?? 0,
            severity_level: loc.severity_level || 'MODERATE',
            latitude: loc.latitude ?? '',
            longitude: loc.longitude ?? '',
          }))
        );
      }
    }
  }, [isEdit, existingRes]);

  const saveMutation = useMutation({
    mutationFn: async ({ submitImmediately }) => {
      const payload = {
        ...form,
        latitude: form.latitude === '' ? null : Number(form.latitude),
        longitude: form.longitude === '' ? null : Number(form.longitude),
        affected_households: Number(form.affected_households || 0),
        affected_population: Number(form.affected_population || 0),
        displaced_population: Number(form.displaced_population || 0),
        deaths_count: Number(form.deaths_count || 0),
        injured_count: Number(form.injured_count || 0),
        missing_count: Number(form.missing_count || 0),
        submit_immediately: submitImmediately,
        locations: locations
          .filter((l) => l.administrative_unit_id)
          .map((l) => ({
            ...l,
            affected_households: Number(l.affected_households || 0),
            affected_population: Number(l.affected_population || 0),
            latitude: l.latitude === '' ? null : Number(l.latitude),
            longitude: l.longitude === '' ? null : Number(l.longitude),
          })),
        sector_items: sectorItems.map((item) => ({
          ...item,
          fully_damaged_count: Number(item.fully_damaged_count || 0),
          partially_damaged_count: Number(item.partially_damaged_count || 0),
          minor_damaged_count: Number(item.minor_damaged_count || 0),
          estimated_loss_bdt: Number(item.estimated_loss_bdt || 0),
          affected_households: Number(item.affected_households || 0),
        })),
      };

      const res = isEdit
        ? await damageApi.updateAssessment(id, payload)
        : await damageApi.createAssessment(payload);

      const savedId = res?.data?.id || id;

      if (savedId && pendingFiles.length > 0) {
        for (const file of pendingFiles) {
          const fd = new FormData();
          fd.append('file', file);
          if (payload.latitude !== null) fd.append('latitude', String(payload.latitude));
          if (payload.longitude !== null) fd.append('longitude', String(payload.longitude));
          await damageApi.uploadAttachment(savedId, fd);
        }
      }

      if (isEdit && submitImmediately) {
        await damageApi.submitAssessment(savedId, { notes: 'Submitted after revision.' });
      }

      return savedId;
    },
    onSuccess: (savedId) => {
      queryClient.invalidateQueries({ queryKey: ['damage'] });
      navigate(`/damage/assessments/${savedId}`);
    },
    onError: (err) => {
      setFeedback({
        variant: 'danger',
        message: formatDamageApiError(err, t('ডি-ফর্ম সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save damage assessment.')),
      });
    },
  });

  const captureBrowserGps = () => {
    if (!navigator.geolocation) {
      setFeedback({ variant: 'warning', message: t('ব্রাউজার জিপিএস সমর্থন করে না।', 'Browser GPS not supported.') });
      return;
    }
    setGpsLoading(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setGpsLoading(false);
        setForm((prev) => ({
          ...prev,
          latitude: Number(pos.coords.latitude.toFixed(6)),
          longitude: Number(pos.coords.longitude.toFixed(6)),
        }));
      },
      (err) => {
        setGpsLoading(false);
        setFeedback({ variant: 'warning', message: err.message });
      },
      { timeout: 10000, enableHighAccuracy: true }
    );
  };

  if (loadingLookups || (isEdit && loadingExisting)) return <Loader />;

  const computedTotalLoss = sectorItems.reduce((sum, it) => sum + Number(it.estimated_loss_bdt || 0), 0);
  const computedFull = sectorItems.reduce((sum, it) => sum + Number(it.fully_damaged_count || 0), 0);
  const computedPartial = sectorItems.reduce((sum, it) => sum + Number(it.partially_damaged_count || 0), 0);
  const computedMinor = sectorItems.reduce((sum, it) => sum + Number(it.minor_damaged_count || 0), 0);

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-file-earmark-medical text-danger me-2"></i>
            {isEdit
              ? t(`ডি-ফর্ম সম্পাদনা (${form.d_form_code})`, `Edit D-Form (${form.d_form_code})`)
              : t('নতুন ক্ষয়ক্ষতি নিরূপণ (ডি-ফর্ম নিবন্ধন)', 'Register New Damage Assessment (D-Form)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'বাংলাদেশ সরকারের স্ট্যান্ডার্ড ডি-ফর্ম অনুযায়ী মাঠ পর্যায়ের তথ্য, জিপিএস ও খাতভিত্তিক ক্ষতি নিবন্ধন',
              'Official D-Form field assessment with GPS coordinates, sectoral damages, and evidence'
            )}
          </p>
        </div>
        <Link to="/damage/assessments" className="btn btn-sm btn-outline-secondary">
          <i className="bi bi-arrow-left me-1"></i>
          {t('তালিকায় ফিরুন', 'Back to List')}
        </Link>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      <form
        onSubmit={(e) => {
          e.preventDefault();
          saveMutation.mutate({ submitImmediately: false });
        }}
      >
        {/* Section 1: Registration Metadata */}
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white fw-semibold">
            {t('১. নিরূপণ নিবন্ধন ও দুর্যোগ সংযোগ', '1. Assessment Registration & Linked Disaster')}
          </div>
          <div className="card-body">
            <div className="row g-3">
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('সংশ্লিষ্ট দুর্যোগ', 'Linked Disaster')} *</label>
                <select
                  className="form-select form-select-sm"
                  value={form.disaster_event_id}
                  onChange={(e) => setForm({ ...form, disaster_event_id: e.target.value })}
                  required
                >
                  <option value="">{t('দুর্যোগ নির্বাচন করুন...', 'Select disaster...')}</option>
                  {disasters.map((d) => (
                    <option key={d.id} value={d.id}>
                      {t(d.name_bn, d.name_en)} ({d.disaster_code})
                    </option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('প্রাথমিক প্রশাসনিক এলাকা', 'Primary Jurisdiction')} *</label>
                <select
                  className="form-select form-select-sm"
                  value={form.administrative_unit_id}
                  onChange={(e) => setForm({ ...form, administrative_unit_id: e.target.value })}
                  required
                >
                  <option value="">{t('এলাকা নির্বাচন করুন...', 'Select administrative unit...')}</option>
                  {adminUnits.map((u) => (
                    <option key={u.id} value={u.id}>
                      {t(u.name_bn, u.name_en)} ({u.tier_level})
                    </option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('নিরূপণের ধরন', 'Assessment Type')} *</label>
                <select
                  className="form-select form-select-sm"
                  value={form.assessment_type}
                  onChange={(e) => setForm({ ...form, assessment_type: e.target.value })}
                  required
                >
                  {Object.entries(DAMAGE_TYPE_META).map(([k, v]) => (
                    <option key={k} value={k}>{t(v.bn, v.en)}</option>
                  ))}
                </select>
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('ডি-ফর্ম কোড (ঐচ্ছিক)', 'D-Form Reference Code')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  placeholder={t('স্বয়ংক্রিয়ভাবে তৈরি হবে', 'Auto-generated if blank')}
                  value={form.d_form_code}
                  onChange={(e) => setForm({ ...form, d_form_code: e.target.value })}
                  disabled={isEdit}
                />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('নিরূপণের তারিখ', 'Assessment Date')} *</label>
                <input
                  type="date"
                  className="form-control form-control-sm"
                  value={form.assessment_date}
                  onChange={(e) => setForm({ ...form, assessment_date: e.target.value })}
                  required
                />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('মাঠ পরিদর্শনের সময়', 'Collection Date & Time')}</label>
                <input
                  type="datetime-local"
                  className="form-control form-control-sm"
                  value={form.assessed_at}
                  onChange={(e) => setForm({ ...form, assessed_at: e.target.value })}
                />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('তীব্রতার মাত্রা', 'Severity Level')}</label>
                <select
                  className="form-select form-select-sm"
                  value={form.severity_level}
                  onChange={(e) => setForm({ ...form, severity_level: e.target.value })}
                >
                  {Object.entries(DAMAGE_SEVERITY_META).map(([k, v]) => (
                    <option key={k} value={k}>{t(v.bn, v.en)}</option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('নিরূপণকারী সংস্থা', 'Assessing Organization')} *</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  placeholder={t('যেমন: উপজেলা দুর্যোগ ব্যবস্থাপনা কমিটি', 'e.g. Upazila DMC / PIO Office')}
                  value={form.assessing_organization}
                  onChange={(e) => setForm({ ...form, assessing_organization: e.target.value })}
                  required
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('দায়িত্বপ্রাপ্ত দল', 'Assigned Assessment Team')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  placeholder={t('দলের নাম', 'e.g. Coastal Rapid Survey Team-1')}
                  value={form.team_name}
                  onChange={(e) => setForm({ ...form, team_name: e.target.value })}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('তথ্য সংগ্রহকারী / মূল্যায়নকারী', 'Assessor Name')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  value={form.assessor_name}
                  onChange={(e) => setForm({ ...form, assessor_name: e.target.value })}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Section 2: Affected Population & Geographical Coverage */}
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span>{t('২. ক্ষতিগ্রস্ত জনসংখ্যা, ভৌগোলিক বিস্তার ও জিআইএস অবস্থান', '2. Affected Population & GIS Location')}</span>
            <button
              type="button"
              className="btn btn-sm btn-outline-primary"
              onClick={captureBrowserGps}
              disabled={gpsLoading}
            >
              <i className="bi bi-crosshair me-1"></i>
              {gpsLoading ? t('জিপিএস সংগ্রহ হচ্ছে...', 'Capturing GPS...') : t('বর্তমান জিপিএস নিন', 'Capture GPS')}
            </button>
          </div>
          <div className="card-body">
            <div className="row g-3 mb-3">
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('ক্ষতিগ্রস্ত পরিবার', 'Affected HH')} *</label>
                <input
                  type="number"
                  min="0"
                  className="form-control form-control-sm"
                  value={form.affected_households}
                  onChange={(e) => setForm({ ...form, affected_households: e.target.value })}
                  required
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('ক্ষতিগ্রস্ত জনসংখ্যা', 'Affected Pop.')} *</label>
                <input
                  type="number"
                  min="0"
                  className="form-control form-control-sm"
                  value={form.affected_population}
                  onChange={(e) => setForm({ ...form, affected_population: e.target.value })}
                  required
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('বাস্তুচ্যুত জনসংখ্যা', 'Displaced Pop.')}</label>
                <input
                  type="number"
                  min="0"
                  className="form-control form-control-sm"
                  value={form.displaced_population}
                  onChange={(e) => setForm({ ...form, displaced_population: e.target.value })}
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('মৃত', 'Deaths')}</label>
                <input
                  type="number"
                  min="0"
                  className="form-control form-control-sm"
                  value={form.deaths_count}
                  onChange={(e) => setForm({ ...form, deaths_count: e.target.value })}
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('আহত', 'Injured')}</label>
                <input
                  type="number"
                  min="0"
                  className="form-control form-control-sm"
                  value={form.injured_count}
                  onChange={(e) => setForm({ ...form, injured_count: e.target.value })}
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('নিখোঁজ', 'Missing')}</label>
                <input
                  type="number"
                  min="0"
                  className="form-control form-control-sm"
                  value={form.missing_count}
                  onChange={(e) => setForm({ ...form, missing_count: e.target.value })}
                />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('ওয়ার্ড নং', 'Ward No')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  value={form.ward_no}
                  onChange={(e) => setForm({ ...form, ward_no: e.target.value })}
                />
              </div>
              <div className="col-md-5">
                <label className="form-label small fw-semibold">{t('এলাকার বিবরণ', 'Location Summary')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm"
                  value={form.location_summary}
                  onChange={(e) => setForm({ ...form, location_summary: e.target.value })}
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('অক্ষাংশ (Lat)', 'Latitude')}</label>
                <input
                  type="number"
                  step="0.000001"
                  className="form-control form-control-sm"
                  value={form.latitude}
                  onChange={(e) => setForm({ ...form, latitude: e.target.value })}
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('দ্রাঘিমাংশ (Lng)', 'Longitude')}</label>
                <input
                  type="number"
                  step="0.000001"
                  className="form-control form-control-sm"
                  value={form.longitude}
                  onChange={(e) => setForm({ ...form, longitude: e.target.value })}
                />
              </div>
            </div>

            <DamageGisMap
              mode="pick"
              height="320px"
              pickerLat={form.latitude}
              pickerLng={form.longitude}
              pickerBoundary={form.boundary_geojson}
              onPickPoint={(lat, lng) => setForm((prev) => ({ ...prev, latitude: lat, longitude: lng }))}
              onBoundaryParsed={(geom, centroid) =>
                setForm((prev) => ({
                  ...prev,
                  boundary_geojson: geom,
                  latitude: prev.latitude || centroid?.latitude || '',
                  longitude: prev.longitude || centroid?.longitude || '',
                }))
              }
            />

            {/* Multi-location coverage */}
            <div className="mt-3">
              <div className="d-flex justify-content-between align-items-center mb-2">
                <span className="small fw-semibold">
                  {t('অতিরিক্ত আওতাভুক্ত এলাকা (ঐচ্ছিক)', 'Additional Geographical Coverage Locations')}
                </span>
                <button
                  type="button"
                  className="btn btn-sm btn-outline-secondary"
                  onClick={() => setLocations([...locations, emptyLocation()])}
                >
                  <i className="bi bi-plus me-1"></i>
                  {t('এলাকা যোগ করুন', 'Add Area')}
                </button>
              </div>
              {locations.map((loc, idx) => (
                <div key={idx} className="row g-2 align-items-end mb-2 border rounded p-2 bg-light">
                  <div className="col-md-4">
                    <label className="form-label small mb-1">{t('প্রশাসনিক ইউনিট', 'Admin Unit')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={loc.administrative_unit_id}
                      onChange={(e) => {
                        const copy = [...locations];
                        copy[idx].administrative_unit_id = e.target.value;
                        setLocations(copy);
                      }}
                    >
                      <option value="">{t('নির্বাচন...', 'Select...')}</option>
                      {adminUnits.map((u) => (
                        <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)}</option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-3">
                    <label className="form-label small mb-1">{t('এলাকা/গ্রাম', 'Locality')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={loc.locality_name_en}
                      onChange={(e) => {
                        const copy = [...locations];
                        copy[idx].locality_name_en = e.target.value;
                        setLocations(copy);
                      }}
                    />
                  </div>
                  <div className="col-md-2">
                    <label className="form-label small mb-1">{t('পরিবার', 'Households')}</label>
                    <input
                      type="number"
                      min="0"
                      className="form-control form-control-sm"
                      value={loc.affected_households}
                      onChange={(e) => {
                        const copy = [...locations];
                        copy[idx].affected_households = e.target.value;
                        setLocations(copy);
                      }}
                    />
                  </div>
                  <div className="col-md-2">
                    <label className="form-label small mb-1">{t('তীব্রতা', 'Severity')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={loc.severity_level}
                      onChange={(e) => {
                        const copy = [...locations];
                        copy[idx].severity_level = e.target.value;
                        setLocations(copy);
                      }}
                    >
                      {Object.keys(DAMAGE_SEVERITY_META).map((sv) => (
                        <option key={sv} value={sv}>{sv}</option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-1 text-end">
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-danger"
                      onClick={() => setLocations(locations.filter((_, i) => i !== idx))}
                    >
                      <i className="bi bi-x"></i>
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Section 3: Sectoral Damage Details */}
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span>{t('৩. খাতভিত্তিক ক্ষয়ক্ষতির বিবরণ (Sectoral Breakdown)', '3. Sectoral Damage Breakdown')}</span>
            <button
              type="button"
              className="btn btn-sm btn-outline-primary"
              onClick={() => setSectorItems([...sectorItems, emptyItem(categories[0]?.id || '')])}
            >
              <i className="bi bi-plus-circle me-1"></i>
              {t('খাত যোগ করুন', 'Add Category Row')}
            </button>
          </div>
          <div className="card-body">
            {sectorItems.map((item, idx) => (
              <div key={idx} className="border rounded p-2 mb-2 bg-light">
                <div className="row g-2 align-items-end">
                  <div className="col-md-3">
                    <label className="form-label small fw-semibold mb-1">{t('ক্ষয়ক্ষতির খাত', 'Damage Category')} *</label>
                    <select
                      className="form-select form-select-sm"
                      value={item.category_id}
                      onChange={(e) => {
                        const copy = [...sectorItems];
                        const cat = categories.find((c) => c.id === e.target.value);
                        copy[idx].category_id = e.target.value;
                        if (cat?.default_unit) copy[idx].unit_of_measure = cat.default_unit;
                        setSectorItems(copy);
                      }}
                      required
                    >
                      <option value="">{t('খাত নির্বাচন...', 'Select category...')}</option>
                      {categories.map((c) => (
                        <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
                      ))}
                    </select>
                  </div>

                  <div className="col-md-3">
                    <label className="form-label small fw-semibold mb-1">{t('অবকাঠামো/আইটেম বিবরণ', 'Asset / Item Title')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder={t('যেমন: কাঁচা ঘর, প্রাথমিক বিদ্যালয়', 'e.g. Katcha houses, Primary School')}
                      value={item.item_title}
                      onChange={(e) => {
                        const copy = [...sectorItems];
                        copy[idx].item_title = e.target.value;
                        setSectorItems(copy);
                      }}
                    />
                  </div>

                  <div className="col-md-1">
                    <label className="form-label small fw-semibold mb-1">{t('সম্পূর্ণ', 'Full')} *</label>
                    <input
                      type="number"
                      min="0"
                      className="form-control form-control-sm"
                      value={item.fully_damaged_count}
                      onChange={(e) => {
                        const copy = [...sectorItems];
                        copy[idx].fully_damaged_count = e.target.value;
                        setSectorItems(copy);
                      }}
                      required
                    />
                  </div>

                  <div className="col-md-1">
                    <label className="form-label small fw-semibold mb-1">{t('আংশিক', 'Partial')} *</label>
                    <input
                      type="number"
                      min="0"
                      className="form-control form-control-sm"
                      value={item.partially_damaged_count}
                      onChange={(e) => {
                        const copy = [...sectorItems];
                        copy[idx].partially_damaged_count = e.target.value;
                        setSectorItems(copy);
                      }}
                      required
                    />
                  </div>

                  <div className="col-md-1">
                    <label className="form-label small fw-semibold mb-1">{t('সামান্য', 'Minor')}</label>
                    <input
                      type="number"
                      min="0"
                      className="form-control form-control-sm"
                      value={item.minor_damaged_count}
                      onChange={(e) => {
                        const copy = [...sectorItems];
                        copy[idx].minor_damaged_count = e.target.value;
                        setSectorItems(copy);
                      }}
                    />
                  </div>

                  <div className="col-md-2">
                    <label className="form-label small fw-semibold mb-1">{t('মেরামত/ক্ষতি (টাকা)', 'Est. Loss (BDT)')} *</label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      className="form-control form-control-sm"
                      value={item.estimated_loss_bdt}
                      onChange={(e) => {
                        const copy = [...sectorItems];
                        copy[idx].estimated_loss_bdt = e.target.value;
                        setSectorItems(copy);
                      }}
                      required
                    />
                  </div>

                  <div className="col-md-1 text-end">
                    {sectorItems.length > 1 && (
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-danger"
                        onClick={() => setSectorItems(sectorItems.filter((_, i) => i !== idx))}
                      >
                        <i className="bi bi-trash"></i>
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}

            <div className="d-flex flex-wrap justify-content-end gap-4 mt-3 pt-2 border-top small">
              <span>{t('মোট সম্পূর্ণ ক্ষতিগ্রস্ত', 'Total Full')}: <strong>{computedFull}</strong></span>
              <span>{t('মোট আংশিক ক্ষতিগ্রস্ত', 'Total Partial')}: <strong>{computedPartial}</strong></span>
              <span>{t('মোট সামান্য ক্ষতি', 'Total Minor')}: <strong>{computedMinor}</strong></span>
              <span className="text-danger fs-6">
                {t('সর্বমোট আনুমানিক ক্ষতি', 'Total Estimated Loss')}: <strong>{formatBdt(computedTotalLoss)}</strong>
              </span>
            </div>
          </div>
        </div>

        {/* Section 4: Field Observations & Photos/Documents */}
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white fw-semibold">
            {t('৪. মাঠ পর্যায়ের পর্যবেক্ষণ ও সংযুক্তি (ছবি/নথি)', '4. Field Observations & Supporting Media')}
          </div>
          <div className="card-body">
            <div className="mb-3">
              <label className="form-label small fw-semibold">{t('মাঠ পর্যায়ের পর্যবেক্ষণ', 'Field Observations')}</label>
              <textarea
                className="form-control form-control-sm"
                rows="3"
                value={form.field_observations}
                onChange={(e) => setForm({ ...form, field_observations: e.target.value })}
              />
            </div>

            <div>
              <label className="form-label small fw-semibold">
                {t('ক্ষয়ক্ষতির ছবি বা নথি সংযুক্ত করুন (JPG, PNG, PDF)', 'Attach Photographs or Supporting Documents')}
              </label>
              <input
                type="file"
                multiple
                accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"
                className="form-control form-control-sm"
                onChange={(e) => setPendingFiles(Array.from(e.target.files || []))}
              />
              {pendingFiles.length > 0 && (
                <div className="small text-muted mt-1">
                  {pendingFiles.length} {t('টি ফাইল আপলোডের জন্য নির্বাচিত হয়েছে।', 'file(s) queued for upload.')}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="d-flex justify-content-end gap-2">
          <button type="submit" className="btn btn-outline-primary" disabled={saveMutation.isPending}>
            <i className="bi bi-save me-1"></i>
            {saveMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('খসড়া হিসেবে সংরক্ষণ করুন', 'Save as Draft')}
          </button>

          {canSubmitDamage(user) && (
            <button
              type="button"
              className="btn btn-danger"
              disabled={saveMutation.isPending}
              onClick={() => saveMutation.mutate({ submitImmediately: true })}
            >
              <i className="bi bi-send-check me-1"></i>
              {t('সংরক্ষণ ও যাচাইয়ের জন্য দাখিল করুন', 'Save & Submit for Verification')}
            </button>
          )}
        </div>
      </form>
    </div>
  );
};

export default DamageAssessmentFormPage;
