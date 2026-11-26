import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { projectApi } from '../../../api/projectApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ProjectGisMap from '../components/ProjectGisMap';
import { formatProjectApiError } from '../utils/projectPermissions';

const getTodayIso = () => new Date().toISOString().slice(0, 10);

const ProjectFormFields = ({ isEdit, id, initialData, categories, disasters, adminUnits }) => {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState(() => ({
    category_id: initialData?.category_id || '',
    disaster_event_id: initialData?.disaster_event_id || '',
    administrative_unit_id: initialData?.administrative_unit_id || '',
    project_code: initialData?.project_code || '',
    reference_no: initialData?.reference_no || '',
    title_en: initialData?.title_en || '',
    title_bn: initialData?.title_bn || '',
    description: initialData?.description || '',
    objectives: initialData?.objectives || '',
    implementing_organization: initialData?.implementing_organization || 'Department of Disaster Management (DDM)',
    project_manager_name: initialData?.project_manager_name || '',
    project_manager_contact: initialData?.project_manager_contact || '',
    funding_source: initialData?.funding_source || 'GoB / MoDMR',
    approved_budget_bdt: initialData?.approved_budget_bdt ?? '',
    revised_budget_bdt: initialData?.revised_budget_bdt ?? '',
    start_date: initialData?.start_date || getTodayIso(),
    expected_end_date: initialData?.expected_end_date || '',
    ward_no: initialData?.ward_no || '',
    location_summary: initialData?.location_summary || '',
    latitude: initialData?.latitude ?? '',
    longitude: initialData?.longitude ?? '',
    boundary_geojson: initialData?.boundary_geojson || null,
    is_delayed: Boolean(initialData?.is_delayed),
    delay_days: initialData?.delay_days ?? 0,
    delay_reason: initialData?.delay_reason || '',
  }));

  const [locations, setLocations] = useState(() =>
    (initialData?.locations || []).map((l) => ({
      administrative_unit_id: l.administrative_unit_id || '',
      ward_no: l.ward_no || '',
      site_name_en: l.site_name_en || '',
      site_name_bn: l.site_name_bn || '',
      latitude: l.latitude ?? '',
      longitude: l.longitude ?? '',
    }))
  );

  const [errorMsg, setErrorMsg] = useState(null);

  const mutation = useMutation({
    mutationFn: (payload) =>
      isEdit ? projectApi.updateProject(id, payload) : projectApi.createProject(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['project'] });
      navigate(`/project/schemes/${res.data.id}`);
    },
    onError: (err) => {
      setErrorMsg(formatProjectApiError(err, t('প্রকল্প সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save project.')));
    },
  });

  const addLocationRow = () => {
    setLocations((prev) => [
      ...prev,
      { administrative_unit_id: '', ward_no: '', site_name_en: '', site_name_bn: '', latitude: '', longitude: '' },
    ]);
  };

  const updateLocationRow = (index, field, val) => {
    setLocations((prev) => prev.map((row, idx) => (idx === index ? { ...row, [field]: val } : row)));
  };

  const removeLocationRow = (index) => {
    setLocations((prev) => prev.filter((_, idx) => idx !== index));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setErrorMsg(null);

    const cleanedLocations = locations
      .filter((l) => l.administrative_unit_id)
      .map((l) => ({
        ...l,
        latitude: l.latitude !== '' ? Number(l.latitude) : null,
        longitude: l.longitude !== '' ? Number(l.longitude) : null,
      }));

    mutation.mutate({
      ...form,
      disaster_event_id: form.disaster_event_id || null,
      project_code: form.project_code || null,
      reference_no: form.reference_no || null,
      revised_budget_bdt: form.revised_budget_bdt !== '' ? Number(form.revised_budget_bdt) : null,
      approved_budget_bdt: Number(form.approved_budget_bdt),
      latitude: form.latitude !== '' ? Number(form.latitude) : null,
      longitude: form.longitude !== '' ? Number(form.longitude) : null,
      locations: cleanedLocations,
    });
  };

  return (
    <>
      {errorMsg && <AlertBanner variant="danger" message={errorMsg} onClose={() => setErrorMsg(null)} />}

      <form onSubmit={handleSubmit}>
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white fw-semibold">
            {t('১. প্রকল্পের মৌলিক তথ্য ও বাজেট', '1. Project Registration & Budget Details')}
          </div>
          <div className="card-body">
            <div className="row g-3">
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('প্রকল্পের ধরন *', 'Project Category *')}</label>
                <select
                  className="form-select"
                  required
                  value={form.category_id}
                  onChange={(e) => setForm((p) => ({ ...p, category_id: e.target.value }))}
                >
                  <option value="">{t('ধরন নির্বাচন করুন...', 'Select category...')}</option>
                  {categories.map((c) => (
                    <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('প্রাথমিক প্রশাসনিক এলাকা *', 'Primary Jurisdiction *')}</label>
                <select
                  className="form-select"
                  required
                  value={form.administrative_unit_id}
                  onChange={(e) => setForm((p) => ({ ...p, administrative_unit_id: e.target.value }))}
                >
                  <option value="">{t('বিভাগ/জেলা/উপজেলা/ইউনিয়ন নির্বাচন...', 'Select administrative unit...')}</option>
                  {adminUnits.map((u) => (
                    <option key={u.id} value={u.id}>
                      {t(u.name_bn || u.name_en, u.name_en)} ({u.tier_level})
                    </option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('সংশ্লিষ্ট দুর্যোগ (ঐচ্ছিক)', 'Linked Disaster (Optional)')}</label>
                <select
                  className="form-select"
                  value={form.disaster_event_id}
                  onChange={(e) => setForm((p) => ({ ...p, disaster_event_id: e.target.value }))}
                >
                  <option value="">{t('স্বতন্ত্র প্রকল্প (দুর্যোগহীন)', 'Standalone DRR / Development')}</option>
                  {disasters.map((d) => (
                    <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)} ({d.disaster_code})</option>
                  ))}
                </select>
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('প্রকল্প কোড', 'Project Code')}</label>
                <input
                  className="form-control"
                  placeholder={t('স্বয়ংক্রিয় বা কাস্টম কোড', 'Auto-generated if blank')}
                  disabled={isEdit}
                  value={form.project_code}
                  onChange={(e) => setForm((p) => ({ ...p, project_code: e.target.value }))}
                />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('স্মারক / রেফারেন্স নম্বর', 'Reference / Memo No')}</label>
                <input
                  className="form-control"
                  value={form.reference_no}
                  onChange={(e) => setForm((p) => ({ ...p, reference_no: e.target.value }))}
                />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('শুরুর তারিখ *', 'Start Date *')}</label>
                <input
                  type="date"
                  className="form-control"
                  required
                  value={form.start_date}
                  onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))}
                />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('সম্ভাব্য সমাপ্তির তারিখ *', 'Expected End Date *')}</label>
                <input
                  type="date"
                  className="form-control"
                  required
                  value={form.expected_end_date}
                  onChange={(e) => setForm((p) => ({ ...p, expected_end_date: e.target.value }))}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('প্রকল্পের শিরোনাম (বাংলা) *', 'Project Title (Bangla) *')}</label>
                <input
                  className="form-control"
                  required
                  value={form.title_bn}
                  onChange={(e) => setForm((p) => ({ ...p, title_bn: e.target.value }))}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('প্রকল্পের শিরোনাম (ইংরেজি) *', 'Project Title (English) *')}</label>
                <input
                  className="form-control"
                  required
                  value={form.title_en}
                  onChange={(e) => setForm((p) => ({ ...p, title_en: e.target.value }))}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('বাস্তবায়নকারী সংস্থা *', 'Implementing Organization *')}</label>
                <input
                  className="form-control"
                  required
                  value={form.implementing_organization}
                  onChange={(e) => setForm((p) => ({ ...p, implementing_organization: e.target.value }))}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('প্রকল্প ব্যবস্থাপক *', 'Project Manager *')}</label>
                <input
                  className="form-control"
                  required
                  value={form.project_manager_name}
                  onChange={(e) => setForm((p) => ({ ...p, project_manager_name: e.target.value }))}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('ব্যবস্থাপকের যোগাযোগ', 'Manager Contact')}</label>
                <input
                  className="form-control"
                  value={form.project_manager_contact}
                  onChange={(e) => setForm((p) => ({ ...p, project_manager_contact: e.target.value }))}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('তহবিলের উৎস *', 'Funding Source *')}</label>
                <input
                  className="form-control"
                  required
                  value={form.funding_source}
                  onChange={(e) => setForm((p) => ({ ...p, funding_source: e.target.value }))}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('অনুমোদিত বাজেট (টাকা) *', 'Approved Budget (BDT) *')}</label>
                <input
                  type="number"
                  step="0.01"
                  min="1"
                  className="form-control"
                  required
                  value={form.approved_budget_bdt}
                  onChange={(e) => setForm((p) => ({ ...p, approved_budget_bdt: e.target.value }))}
                />
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('সংশোধিত বাজেট (টাকা)', 'Revised Budget (BDT)')}</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  className="form-control"
                  value={form.revised_budget_bdt}
                  onChange={(e) => setForm((p) => ({ ...p, revised_budget_bdt: e.target.value }))}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('প্রকল্পের বিবরণ', 'Project Description')}</label>
                <textarea
                  className="form-control"
                  rows={3}
                  value={form.description}
                  onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))}
                />
              </div>

              <div className="col-md-6">
                <label className="form-label small fw-semibold">{t('উদ্দেশ্যসমূহ', 'Objectives')}</label>
                <textarea
                  className="form-control"
                  rows={3}
                  value={form.objectives}
                  onChange={(e) => setForm((p) => ({ ...p, objectives: e.target.value }))}
                />
              </div>
            </div>
          </div>
        </div>

        {/* Section 2: Geographical Coverage & GIS */}
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-header bg-white d-flex justify-content-between align-items-center">
            <span className="fw-semibold">{t('২. ভৌগোলিক বিস্তার, বহু-অবস্থান ও জিআইএস সীমানা', '2. Geographical Coverage, Multi-Locations & GIS')}</span>
            <button type="button" className="btn btn-sm btn-outline-primary" onClick={addLocationRow}>
              <i className="bi bi-plus-circle me-1"></i>
              {t('অতিরিক্ত অবস্থান যোগ করুন', 'Add Location')}
            </button>
          </div>
          <div className="card-body">
            <div className="row g-3 mb-3">
              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('ওয়ার্ড নং', 'Ward No')}</label>
                <input
                  className="form-control"
                  value={form.ward_no}
                  onChange={(e) => setForm((p) => ({ ...p, ward_no: e.target.value }))}
                />
              </div>
              <div className="col-md-5">
                <label className="form-label small fw-semibold">{t('অবস্থান সারসংক্ষেপ', 'Location Summary')}</label>
                <input
                  className="form-control"
                  value={form.location_summary}
                  onChange={(e) => setForm((p) => ({ ...p, location_summary: e.target.value }))}
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('অক্ষাংশ (Lat)', 'Latitude')}</label>
                <input
                  type="number"
                  step="0.000001"
                  className="form-control"
                  value={form.latitude}
                  onChange={(e) => setForm((p) => ({ ...p, latitude: e.target.value }))}
                />
              </div>
              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('দ্রাঘিমাংশ (Lng)', 'Longitude')}</label>
                <input
                  type="number"
                  step="0.000001"
                  className="form-control"
                  value={form.longitude}
                  onChange={(e) => setForm((p) => ({ ...p, longitude: e.target.value }))}
                />
              </div>
            </div>

            {locations.map((loc, idx) => (
              <div className="row g-2 align-items-end mb-2 p-2 bg-light rounded" key={idx}>
                <div className="col-md-3">
                  <label className="form-label small mb-1">{t('ইউনিট *', 'Admin Unit *')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={loc.administrative_unit_id}
                    onChange={(e) => updateLocationRow(idx, 'administrative_unit_id', e.target.value)}
                  >
                    <option value="">{t('নির্বাচন...', 'Select...')}</option>
                    {adminUnits.map((u) => (
                      <option key={u.id} value={u.id}>{t(u.name_bn || u.name_en, u.name_en)} ({u.tier_level})</option>
                    ))}
                  </select>
                </div>
                <div className="col-md-2">
                  <label className="form-label small mb-1">{t('ওয়ার্ড', 'Ward')}</label>
                  <input
                    className="form-control form-control-sm"
                    value={loc.ward_no}
                    onChange={(e) => updateLocationRow(idx, 'ward_no', e.target.value)}
                  />
                </div>
                <div className="col-md-3">
                  <label className="form-label small mb-1">{t('সাইটের নাম', 'Site Name')}</label>
                  <input
                    className="form-control form-control-sm"
                    value={loc.site_name_en}
                    onChange={(e) => updateLocationRow(idx, 'site_name_en', e.target.value)}
                  />
                </div>
                <div className="col-md-1">
                  <label className="form-label small mb-1">Lat</label>
                  <input
                    type="number"
                    step="0.0001"
                    className="form-control form-control-sm"
                    value={loc.latitude}
                    onChange={(e) => updateLocationRow(idx, 'latitude', e.target.value)}
                  />
                </div>
                <div className="col-md-2">
                  <label className="form-label small mb-1">Lng</label>
                  <input
                    type="number"
                    step="0.0001"
                    className="form-control form-control-sm"
                    value={loc.longitude}
                    onChange={(e) => updateLocationRow(idx, 'longitude', e.target.value)}
                  />
                </div>
                <div className="col-md-1 text-end">
                  <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => removeLocationRow(idx)}>
                    <i className="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            ))}

            <div className="mt-3">
              <ProjectGisMap
                mode="pick"
                height="340px"
                pickerLat={form.latitude !== '' ? form.latitude : null}
                pickerLng={form.longitude !== '' ? form.longitude : null}
                pickerBoundary={form.boundary_geojson}
                onPickPoint={(lat, lng) => setForm((p) => ({ ...p, latitude: lat, longitude: lng }))}
                onBoundaryParsed={(geojson) => setForm((p) => ({ ...p, boundary_geojson: geojson }))}
              />
            </div>
          </div>
        </div>

        <div className="d-flex justify-content-end gap-2">
          <Link to="/project/schemes" className="btn btn-outline-secondary">
            {t('বাতিল', 'Cancel')}
          </Link>
          <button type="submit" className="btn btn-primary" disabled={mutation.isPending}>
            {mutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('প্রকল্প সংরক্ষণ করুন', 'Save Project')}
          </button>
        </div>
      </form>
    </>
  );
};

export const ProjectFormPage = () => {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const { t } = useLanguage();

  const { data: lookupsRes, isLoading: loadingLookups } = useQuery({
    queryKey: ['project', 'lookups'],
    queryFn: () => projectApi.getLookups(),
  });

  const { data: projectRes, isLoading: loadingProject } = useQuery({
    queryKey: ['project', 'scheme', id],
    queryFn: () => projectApi.getProject(id),
    enabled: isEdit,
  });

  if (loadingLookups || (isEdit && loadingProject)) return <Loader />;

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h4 className="fw-bold mb-0">
          <i className="bi bi-kanban text-primary me-2"></i>
          {isEdit ? t('প্রকল্প তথ্য হালনাগাদ', 'Edit Project Scheme') : t('নতুন প্রকল্প নিবন্ধন', 'Register Disaster Management Project')}
        </h4>
        <Link to="/project/schemes" className="btn btn-sm btn-outline-secondary">
          {t('তালিকায় ফিরুন', 'Back to Projects')}
        </Link>
      </div>

      <ProjectFormFields
        key={id || 'new'}
        isEdit={isEdit}
        id={id}
        initialData={isEdit ? projectRes?.data : null}
        categories={lookupsRes?.data?.categories || []}
        disasters={lookupsRes?.data?.disasters || []}
        adminUnits={lookupsRes?.data?.administrative_units || []}
      />
    </div>
  );
};

export default ProjectFormPage;
