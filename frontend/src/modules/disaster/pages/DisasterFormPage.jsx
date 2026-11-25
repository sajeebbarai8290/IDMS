import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import disasterApi from '../../../api/disasterApi';
import beneficiaryApi from '../../../api/beneficiaryApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import { isNationalUser, LIFECYCLE_PHASES, SEVERITY_LEVELS } from '../utils/disasterPermissions';
import DisasterGisMap from '../components/DisasterGisMap';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';

export const DisasterFormPage = () => {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const { t } = useLanguage();

  const [formData, setFormData] = useState({
    disaster_code: '',
    disaster_type: 'FLOOD',
    subtype: '',
    name_en: '',
    name_bn: '',
    description_en: '',
    description_bn: '',
    severity_level: 'WARNING',
    lifecycle_phase: 'REPORTED',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
    epicenter_lat: 23.685,
    epicenter_lng: 90.3563,
    affected_areas_summary: '',
    affected_zone_geojson: null,
    is_national: false,
    is_emergency_declared: false,
    selected_unit_ids: [],
    impact: {
      affected_population: 0,
      affected_households: 0,
      deaths_count: 0,
      injured_count: 0,
      missing_count: 0,
      damaged_houses_fully: 0,
      damaged_houses_partially: 0,
      damaged_infrastructure_count: 0,
      agricultural_loss_acres: 0,
      agricultural_loss_bdt: 0,
      livestock_loss_count: 0,
      livestock_loss_bdt: 0,
      total_economic_loss_bdt: 0,
    },
  });

  const [unitToAdd, setUnitToAdd] = useState('');
  const [formError, setFormError] = useState('');

  const { data: categories = [] } = useQuery({
    queryKey: ['disasterCategories'],
    queryFn: async () => (await disasterApi.getCategories())?.data || [],
  });

  const { data: adminUnits = [] } = useQuery({
    queryKey: ['adminUnitsAll'],
    queryFn: async () => (await beneficiaryApi.getAdministrativeUnits())?.data || [],
  });

  const { data: existingEvent, isLoading: loadingEvent } = useQuery({
    queryKey: ['disasterEventEdit', id],
    enabled: isEdit,
    queryFn: async () => {
      const res = await disasterApi.getEvent(id);
      return res?.disaster || null;
    },
  });

  useEffect(() => {
    if (existingEvent) {
      setFormData({
        disaster_code: existingEvent.disaster_code || '',
        disaster_type: existingEvent.disaster_type || 'FLOOD',
        subtype: existingEvent.subtype || '',
        name_en: existingEvent.name_en || '',
        name_bn: existingEvent.name_bn || '',
        description_en: existingEvent.description_en || '',
        description_bn: existingEvent.description_bn || '',
        severity_level: existingEvent.severity_level || 'WARNING',
        lifecycle_phase: existingEvent.lifecycle_phase || 'REPORTED',
        start_date: existingEvent.start_date || '',
        end_date: existingEvent.end_date || '',
        epicenter_lat: existingEvent.epicenter_lat ?? '',
        epicenter_lng: existingEvent.epicenter_lng ?? '',
        affected_areas_summary: existingEvent.affected_areas_summary || '',
        affected_zone_geojson: existingEvent.affected_zone_geojson || null,
        is_national: Boolean(existingEvent.is_national),
        is_emergency_declared: Boolean(existingEvent.is_emergency_declared),
        selected_unit_ids: (existingEvent.locations || []).map((l) => l.administrative_unit_id),
        impact: {
          affected_population: existingEvent.impact?.affected_population ?? 0,
          affected_households: existingEvent.impact?.affected_households ?? 0,
          deaths_count: existingEvent.impact?.deaths_count ?? 0,
          injured_count: existingEvent.impact?.injured_count ?? 0,
          missing_count: existingEvent.impact?.missing_count ?? 0,
          damaged_houses_fully: existingEvent.impact?.damaged_houses_fully ?? 0,
          damaged_houses_partially: existingEvent.impact?.damaged_houses_partially ?? 0,
          damaged_infrastructure_count: existingEvent.impact?.damaged_infrastructure_count ?? 0,
          agricultural_loss_acres: existingEvent.impact?.agricultural_loss_acres ?? 0,
          agricultural_loss_bdt: existingEvent.impact?.agricultural_loss_bdt ?? 0,
          livestock_loss_count: existingEvent.impact?.livestock_loss_count ?? 0,
          livestock_loss_bdt: existingEvent.impact?.livestock_loss_bdt ?? 0,
          total_economic_loss_bdt: existingEvent.impact?.total_economic_loss_bdt ?? 0,
        },
      });
    }
  }, [existingEvent]);

  const mutation = useMutation({
    mutationFn: (payload) => (isEdit ? disasterApi.updateEvent(id, payload) : disasterApi.createEvent(payload)),
    onSuccess: (res) => {
      queryClient.invalidateQueries(['disasterDashboard']);
      queryClient.invalidateQueries(['disasterEvents']);
      if (isEdit) queryClient.invalidateQueries(['disasterEvent', id]);
      const targetId = res?.disaster?.id || id;
      navigate(`/disaster/events/${targetId}`);
    },
    onError: (err) => {
      const msg = err?.details ? Object.values(err.details).flat().join(' ') : err?.message;
      setFormError(msg || 'Failed to save disaster event.');
    },
  });

  if (isEdit && loadingEvent) {
    return <Loader message="দুর্যোগ তথ্য লোড হচ্ছে..." messageEn="Loading disaster profile..." />;
  }

  const activeCategory = categories.find((c) => c.code === formData.disaster_type);
  const subtypes = activeCategory?.subtypes || [];

  const handleAddUnit = () => {
    if (!unitToAdd || formData.selected_unit_ids.includes(unitToAdd)) return;
    setFormData((prev) => ({
      ...prev,
      selected_unit_ids: [...prev.selected_unit_ids, unitToAdd],
    }));
    setUnitToAdd('');
  };

  const handleRemoveUnit = (unitId) => {
    setFormData((prev) => ({
      ...prev,
      selected_unit_ids: prev.selected_unit_ids.filter((u) => u !== unitId),
    }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setFormError('');

    const payload = {
      ...(isEdit ? {} : { disaster_code: formData.disaster_code.trim() || undefined }),
      ...(isEdit ? {} : { lifecycle_phase: formData.lifecycle_phase }),
      disaster_type: formData.disaster_type,
      subtype: formData.subtype || null,
      name_en: formData.name_en.trim(),
      name_bn: formData.name_bn.trim(),
      description_en: formData.description_en.trim() || null,
      description_bn: formData.description_bn.trim() || null,
      severity_level: formData.severity_level,
      start_date: formData.start_date || null,
      end_date: formData.end_date || null,
      epicenter_lat: formData.epicenter_lat !== '' ? Number(formData.epicenter_lat) : null,
      epicenter_lng: formData.epicenter_lng !== '' ? Number(formData.epicenter_lng) : null,
      affected_areas_summary: formData.affected_areas_summary.trim() || null,
      affected_zone_geojson: formData.affected_zone_geojson,
      is_national: formData.is_national,
      is_emergency_declared: formData.is_emergency_declared,
      locations: formData.selected_unit_ids.map((uid) => ({ administrative_unit_id: uid })),
      impact: formData.impact,
    };

    mutation.mutate(payload);
  };

  return (
    <div className="container-fluid py-2" style={{ maxWidth: '1150px' }}>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-pencil-square text-danger me-2"></i>
            {isEdit
              ? t('দুর্যোগ তথ্য হালনাগাদ', 'Update Disaster Event')
              : t('নতুন দুর্যোগ নিবন্ধন', 'Register New Disaster Event')}
          </h4>
        </div>
        <Link to="/disaster/events" className="btn btn-outline-secondary btn-sm">
          {t('তালিকায় ফিরুন', 'Back to List')}
        </Link>
      </div>

      {formError && <AlertBanner type="danger" message={formError} />}

      <form onSubmit={handleSubmit}>
        {/* 1. Core Classification */}
        <div className="card shadow-sm border-0 mb-3">
          <div className="card-header bg-white fw-bold">
            1. {t('মৌলিক তথ্য ও শ্রেণিবিন্যাস', 'Core Identification & Classification')}
          </div>
          <div className="card-body">
            <div className="row g-3">
              <div className="col-md-4">
                <label className="form-label small fw-bold">{t('দুর্যোগ আইডি নম্বর', 'Disaster ID Code')}</label>
                <input
                  type="text"
                  className="form-control font-monospace"
                  placeholder={t('ফাঁকা রাখলে স্বয়ংক্রিয় তৈরি হবে', 'Auto-generated if blank')}
                  value={formData.disaster_code}
                  onChange={(e) => setFormData({ ...formData, disaster_code: e.target.value })}
                  disabled={isEdit}
                />
              </div>
              <div className="col-md-4">
                <label className="form-label small fw-bold">{t('দুর্যোগের ধরন *', 'Category *')}</label>
                <select
                  className="form-select"
                  value={formData.disaster_type}
                  onChange={(e) => setFormData({ ...formData, disaster_type: e.target.value, subtype: '' })}
                >
                  {categories.map((c) => (
                    <option key={c.id} value={c.code}>
                      {t(c.name_bn, c.name_en)} ({c.code})
                    </option>
                  ))}
                </select>
              </div>
              <div className="col-md-4">
                <label className="form-label small fw-bold">{t('উপ-ধরন (Subtype)', 'Subtype')}</label>
                <select
                  className="form-select"
                  value={formData.subtype}
                  onChange={(e) => setFormData({ ...formData, subtype: e.target.value })}
                >
                  <option value="">{t('-- বাছাই করুন --', '-- Select Subtype --')}</option>
                  {subtypes.map((st) => (
                    <option key={st.code} value={st.code}>
                      {t(st.name_bn, st.name_en)}
                    </option>
                  ))}
                </select>
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-bold">{t('দুর্যোগের শিরোনাম (বাংলা) *', 'Disaster Title (Bangla) *')}</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.name_bn}
                  onChange={(e) => setFormData({ ...formData, name_bn: e.target.value })}
                  required
                />
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-bold">{t('দুর্যোগের শিরোনাম (ইংরেজি) *', 'Disaster Title (English) *')}</label>
                <input
                  type="text"
                  className="form-control"
                  value={formData.name_en}
                  onChange={(e) => setFormData({ ...formData, name_en: e.target.value })}
                  required
                />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-bold">{t('তীব্রতার মাত্রা *', 'Severity Level *')}</label>
                <select
                  className="form-select"
                  value={formData.severity_level}
                  onChange={(e) => setFormData({ ...formData, severity_level: e.target.value })}
                >
                  {SEVERITY_LEVELS.map((s) => (
                    <option key={s.value} value={s.value}>
                      {t(s.bn, s.en)}
                    </option>
                  ))}
                </select>
              </div>

              {!isEdit && (
                <div className="col-md-3">
                  <label className="form-label small fw-bold">{t('প্রাথমিক অবস্থা', 'Initial Phase')}</label>
                  <select
                    className="form-select"
                    value={formData.lifecycle_phase}
                    onChange={(e) => setFormData({ ...formData, lifecycle_phase: e.target.value })}
                  >
                    {LIFECYCLE_PHASES.slice(0, 4).map((p) => (
                      <option key={p.value} value={p.value}>
                        {t(p.bn, p.en)}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <div className="col-md-3">
                <label className="form-label small fw-bold">{t('শুরুর তারিখ', 'Start Date')}</label>
                <input
                  type="date"
                  className="form-control"
                  value={formData.start_date}
                  onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                />
              </div>
              <div className="col-md-3">
                <label className="form-label small fw-bold">{t('সমাপ্তির তারিখ', 'End Date')}</label>
                <input
                  type="date"
                  className="form-control"
                  value={formData.end_date}
                  onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-bold">{t('বিবরণ (বাংলা)', 'Description (Bangla)')}</label>
                <textarea
                  className="form-control"
                  rows="2"
                  value={formData.description_bn}
                  onChange={(e) => setFormData({ ...formData, description_bn: e.target.value })}
                ></textarea>
              </div>
              <div className="col-md-6">
                <label className="form-label small fw-bold">{t('বিবরণ (ইংরেজি)', 'Description (English)')}</label>
                <textarea
                  className="form-control"
                  rows="2"
                  value={formData.description_en}
                  onChange={(e) => setFormData({ ...formData, description_en: e.target.value })}
                ></textarea>
              </div>

              <div className="col-12 d-flex flex-wrap gap-4">
                <div className="form-check">
                  <input
                    id="chkEmergency"
                    type="checkbox"
                    className="form-check-input"
                    checked={formData.is_emergency_declared}
                    onChange={(e) => setFormData({ ...formData, is_emergency_declared: e.target.checked })}
                  />
                  <label className="form-check-label small fw-semibold text-danger" htmlFor="chkEmergency">
                    {t('জরুরি অবস্থা ঘোষিত', 'Emergency Declared')}
                  </label>
                </div>

                {isNationalUser(user) && (
                  <div className="form-check">
                    <input
                      id="chkNational"
                      type="checkbox"
                      className="form-check-input"
                      checked={formData.is_national}
                      onChange={(e) => setFormData({ ...formData, is_national: e.target.checked })}
                    />
                    <label className="form-check-label small fw-semibold" htmlFor="chkNational">
                      {t('জাতীয় পর্যায়ের দুর্যোগ (সকল স্তরে দৃশ্যমান)', 'Nationwide Disaster (Visible across all tiers)')}
                    </label>
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* 2. Multi-Location & Interactive GIS Map */}
        <div className="card shadow-sm border-0 mb-3">
          <div className="card-header bg-white fw-bold">
            2. {t('অবস্থান ও জিআইএস সীমানা নির্ধারণ', 'Affected Geographical Areas & GIS Boundary')}
          </div>
          <div className="card-body">
            <div className="row g-3 mb-3">
              <div className="col-md-6">
                <label className="form-label small fw-bold">{t('প্রশাসনিক এলাকা যুক্ত করুন', 'Add Affected Division/District/Upazila/Union')}</label>
                <div className="input-group input-group-sm">
                  <select
                    className="form-select"
                    value={unitToAdd}
                    onChange={(e) => setUnitToAdd(e.target.value)}
                  >
                    <option value="">{t('-- প্রশাসনিক এলাকা নির্বাচন --', '-- Select Administrative Unit --')}</option>
                    {adminUnits.map((u) => (
                      <option key={u.id} value={u.id}>
                        [{u.tier_level}] {u.name_bn} ({u.name_en})
                      </option>
                    ))}
                  </select>
                  <button type="button" className="btn btn-outline-primary" onClick={handleAddUnit}>
                    {t('যুক্ত করুন', 'Add')}
                  </button>
                </div>
                <div className="d-flex flex-wrap gap-2 mt-2">
                  {formData.selected_unit_ids.map((uid) => {
                    const unit = adminUnits.find((a) => a.id === uid);
                    return (
                      <span key={uid} className="badge bg-primary-subtle text-primary border border-primary d-inline-flex align-items-center gap-1 py-1 px-2">
                        {unit ? `${unit.name_bn} (${unit.tier_level})` : uid.slice(0, 8)}
                        <button
                          type="button"
                          className="btn-close btn-close-sm"
                          style={{ fontSize: '0.6rem' }}
                          onClick={() => handleRemoveUnit(uid)}
                        ></button>
                      </span>
                    );
                  })}
                </div>
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-bold">{t('ক্ষতিগ্রস্ত এলাকার সারসংক্ষেপ', 'Affected Areas Summary')}</label>
                <input
                  type="text"
                  className="form-control form-control-sm mb-2"
                  placeholder={t('যেমন: দাকোপ ও কয়রা উপজেলা, খুলনা', 'e.g. Dacope and Koyra Upazilas, Khulna')}
                  value={formData.affected_areas_summary}
                  onChange={(e) => setFormData({ ...formData, affected_areas_summary: e.target.value })}
                />
                <div className="row g-2">
                  <div className="col-6">
                    <input
                      type="number"
                      step="0.000001"
                      className="form-control form-control-sm"
                      placeholder="Latitude"
                      value={formData.epicenter_lat}
                      onChange={(e) => setFormData({ ...formData, epicenter_lat: e.target.value })}
                    />
                  </div>
                  <div className="col-6">
                    <input
                      type="number"
                      step="0.000001"
                      className="form-control form-control-sm"
                      placeholder="Longitude"
                      value={formData.epicenter_lng}
                      onChange={(e) => setFormData({ ...formData, epicenter_lng: e.target.value })}
                    />
                  </div>
                </div>
              </div>
            </div>

            <DisasterGisMap
              mode="pick"
              height="360px"
              pickerLat={formData.epicenter_lat}
              pickerLng={formData.epicenter_lng}
              pickerPolygonGeoJson={formData.affected_zone_geojson}
              onPickPoint={({ latitude, longitude }) =>
                setFormData((prev) => ({ ...prev, epicenter_lat: latitude, epicenter_lng: longitude }))
              }
              onDrawPolygon={(geom) => setFormData((prev) => ({ ...prev, affected_zone_geojson: geom }))}
              onShapefileParsed={(parsed) =>
                setFormData((prev) => ({
                  ...prev,
                  affected_zone_geojson: parsed.geometry,
                  epicenter_lat: prev.epicenter_lat || parsed.centroid?.latitude || '',
                  epicenter_lng: prev.epicenter_lng || parsed.centroid?.longitude || '',
                }))
              }
            />
            {formData.affected_zone_geojson && (
              <div className="small text-success mt-2">
                <i className="bi bi-check-circle-fill me-1"></i>
                {t(
                  `প্রভাবিত এলাকার সীমানা (${formData.affected_zone_geojson.type}) মানচিত্রে যুক্ত হয়েছে।`,
                  `Affected boundary (${formData.affected_zone_geojson.type}) attached.`
                )}
              </div>
            )}
          </div>
        </div>

        {/* 3. Impact Summary */}
        <div className="card shadow-sm border-0 mb-3">
          <div className="card-header bg-white fw-bold">
            3. {t('ক্ষয়ক্ষতি ও প্রভাবের প্রাথমিক হিসাব', 'Humanitarian & Economic Impact Estimates')}
          </div>
          <div className="card-body">
            <div className="row g-2 small">
              {[
                ['affected_population', t('ক্ষতিগ্রস্ত জনসংখ্যা', 'Affected Population')],
                ['affected_households', t('ক্ষতিগ্রস্ত পরিবার', 'Affected Households')],
                ['deaths_count', t('মৃত্যু', 'Deaths')],
                ['injured_count', t('আহত', 'Injured')],
                ['missing_count', t('নিখোঁজ', 'Missing')],
                ['damaged_houses_fully', t('সম্পূর্ণ ক্ষতিগ্রস্ত ঘর', 'Houses Fully Damaged')],
                ['damaged_houses_partially', t('আংশিক ক্ষতিগ্রস্ত ঘর', 'Houses Partially Damaged')],
                ['damaged_infrastructure_count', t('ক্ষতিগ্রস্ত অবকাঠামো', 'Damaged Infrastructure')],
                ['agricultural_loss_acres', t('ফসলি জমি ক্ষতি (একর)', 'Crop Loss (Acres)')],
                ['agricultural_loss_bdt', t('কৃষি ক্ষতি (টাকা)', 'Agricultural Loss (BDT)')],
                ['livestock_loss_count', t('প্রাণিসম্পদ ক্ষতি (সংখ্যা)', 'Livestock Loss (Count)')],
                ['total_economic_loss_bdt', t('মোট অর্থনৈতিক ক্ষতি (টাকা)', 'Total Economic Loss (BDT)')],
              ].map(([key, label]) => (
                <div className="col-md-3 col-sm-6" key={key}>
                  <label className="form-label fw-semibold mb-1">{label}</label>
                  <input
                    type="number"
                    min="0"
                    step={key.includes('bdt') || key.includes('acres') ? '0.01' : '1'}
                    className="form-control form-control-sm"
                    value={formData.impact[key]}
                    onChange={(e) =>
                      setFormData((prev) => ({
                        ...prev,
                        impact: { ...prev.impact, [key]: e.target.value },
                      }))
                    }
                  />
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="d-flex justify-content-end gap-2 mb-4">
          <Link to="/disaster/events" className="btn btn-outline-secondary">
            {t('বাতিল', 'Cancel')}
          </Link>
          <button type="submit" className="btn btn-danger px-4" disabled={mutation.isPending}>
            {mutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : isEdit ? t('হালনাগাদ করুন', 'Update Event') : t('দুর্যোগ নিবন্ধন করুন', 'Register Disaster')}
          </button>
        </div>
      </form>
    </div>
  );
};

export default DisasterFormPage;
