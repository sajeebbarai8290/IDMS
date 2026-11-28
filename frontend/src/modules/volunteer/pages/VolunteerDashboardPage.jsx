import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { volunteerApi } from '../../../api/volunteerApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import VolunteerGisMap from '../components/VolunteerGisMap';
import {
  canDeployVolunteers,
  canExportVolunteerReports,
  canManageVolunteerCategories,
  canManageVolunteerSkills,
  canWriteVolunteers,
  formatVolunteerApiError,
  isVolunteerBlackout,
  triggerVolunteerCsvDownload,
} from '../utils/volunteerPermissions';

export const VolunteerDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [orgFilter, setOrgFilter] = useState('');
  const [disasterFilter, setDisasterFilter] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [showCatModal, setShowCatModal] = useState(false);
  const [showSkillModal, setShowSkillModal] = useState(false);
  const [catForm, setCatForm] = useState({ code: '', name_en: '', name_bn: '', color_hex: '#0d6efd', description: '' });
  const [skillForm, setSkillForm] = useState({
    skill_code: '',
    category_id: '',
    name_en: '',
    name_bn: '',
    requires_certification: true,
  });

  const { data: lookupsRes } = useQuery({
    queryKey: ['volunteer', 'lookups'],
    queryFn: () => volunteerApi.getLookups(),
  });

  const filterParams = {
    ...(orgFilter ? { organization_id: orgFilter } : {}),
    ...(disasterFilter ? { disaster_event_id: disasterFilter } : {}),
  };

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'dashboard', filterParams],
    queryFn: () => volunteerApi.getDashboard(filterParams),
  });

  const { data: geoRes } = useQuery({
    queryKey: ['volunteer', 'geojson', filterParams],
    queryFn: () => volunteerApi.getGeoJson(filterParams),
  });

  const createCatMutation = useMutation({
    mutationFn: (payload) => volunteerApi.createCategory(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setCatForm({ code: '', name_en: '', name_bn: '', color_hex: '#0d6efd', description: '' });
      setShowCatModal(false);
      setFeedback({ variant: 'success', message: t('নতুন স্বেচ্ছাসেবক ক্যাটাগরি যোগ করা হয়েছে।', 'Volunteer category created.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('সংরক্ষণ ব্যর্থ।', 'Save failed.')) }),
  });

  const createSkillMutation = useMutation({
    mutationFn: (payload) => volunteerApi.createSkill(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setSkillForm({ skill_code: '', category_id: '', name_en: '', name_bn: '', requires_certification: true });
      setShowSkillModal(false);
      setFeedback({ variant: 'success', message: t('নতুন দক্ষতা যোগ করা হয়েছে।', 'Skill added to catalog.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('সংরক্ষণ ব্যর্থ।', 'Save failed.')) }),
  });

  const handleExport = async () => {
    try {
      const blob = await volunteerApi.exportCsv({ report_type: 'register', ...filterParams });
      triggerVolunteerCsvDownload(blob, 'idms-volunteer-register.csv');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('এক্সপোর্ট ব্যর্থ।', 'Export failed.')) });
    }
  };

  if (isLoading) return <Loader />;
  if (error) return <AlertBanner variant="danger" message={formatVolunteerApiError(error, t('ড্যাশবোর্ড লোড ব্যর্থ।', 'Failed to load dashboard.'))} />;

  const dash = dashRes?.data || {};
  const kpis = dash.kpis || {};
  const lookups = lookupsRes?.data || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h3 className="mb-0">{t('স্বেচ্ছাসেবক ব্যবস্থাপনা ড্যাশবোর্ড (M10)', 'Volunteer Management Dashboard (M10)')}</h3>
          <small className="text-muted">
            {t('সিপিপি, রেড ক্রিসেন্ট ও কমিউনিটি স্বেচ্ছাসেবক নিবন্ধন, মোতায়েন ও প্রশিক্ষণ সমন্বয়', 'CPP, Red Crescent & community volunteer registry, deployment & training coordination')}
          </small>
        </div>
        <div className="d-flex flex-wrap gap-2">
          {canWriteVolunteers(user) && (
            <Link to="/volunteer/volunteers/new" className="btn btn-primary btn-sm">
              <i className="bi bi-person-plus-fill me-1"></i>
              {t('নতুন স্বেচ্ছাসেবক', 'Register Volunteer')}
            </Link>
          )}
          {canDeployVolunteers(user) && (
            <Link to="/volunteer/deployments" className="btn btn-outline-danger btn-sm">
              <i className="bi bi-send-fill me-1"></i>
              {t('মোতায়েন ও উপস্থিতি', 'Deployments & Attendance')}
            </Link>
          )}
          {canManageVolunteerCategories(user) && (
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => setShowCatModal(true)}>
              <i className="bi bi-tags me-1"></i>{t('নতুন ক্যাটাগরি', 'Add Category')}
            </button>
          )}
          {canManageVolunteerSkills(user) && (
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => setShowSkillModal(true)}>
              <i className="bi bi-award me-1"></i>{t('নতুন দক্ষতা', 'Add Skill')}
            </button>
          )}
          {canExportVolunteerReports(user) && (
            <button type="button" className="btn btn-outline-success btn-sm" onClick={handleExport}>
              <i className="bi bi-download me-1"></i>CSV
            </button>
          )}
        </div>
      </div>

      {isVolunteerBlackout(user) && (
        <AlertBanner
          variant="info"
          message={t(
            'সুপার অ্যাডমিন অপারেশনাল ব্ল্যাকআউট সক্রিয়: আপনি ড্যাশবোর্ড, রিপোর্ট এবং ক্যাটাগরি/দক্ষতা কনফিগারেশন দেখতে ও পরিচালনা করতে পারবেন, তবে স্বেচ্ছাসেবক অনুমোদন বা মোতায়েন করতে পারবেন না।',
            'Super Admin operational blackout active: you can view analytics/reports and manage categories/skills, while volunteer approvals and deployments are restricted.'
          )}
        />
      )}

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <div className="card shadow-sm mb-3">
        <div className="card-body py-2">
          <div className="row g-2 align-items-end">
            <div className="col-md-4">
              <label className="form-label small mb-1">{t('সংগঠন ফিল্টার', 'Organization Filter')}</label>
              <select className="form-select form-select-sm" value={orgFilter} onChange={(e) => setOrgFilter(e.target.value)}>
                <option value="">{t('সকল সংগঠন', 'All Organizations')}</option>
                {(lookups.organizations || []).map((o) => (
                  <option key={o.id} value={o.id}>{o.name_en}</option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label small mb-1">{t('দুর্যোগ ইভেন্ট', 'Disaster Event')}</label>
              <select className="form-select form-select-sm" value={disasterFilter} onChange={(e) => setDisasterFilter(e.target.value)}>
                <option value="">{t('সকল দুর্যোগ', 'All Disasters')}</option>
                {(lookups.disasters || []).map((d) => (
                  <option key={d.id} value={d.id}>{d.disaster_code} — {t(d.name_bn, d.name_en)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-4 text-md-end">
              <Link to="/volunteer/volunteers" className="btn btn-sm btn-outline-primary me-2">
                {t('স্বেচ্ছাসেবক তালিকা', 'Volunteer Roster')}
              </Link>
              <Link to="/volunteer/organizations" className="btn btn-sm btn-outline-info">
                {t('সংগঠনসমূহ', 'Organizations')}
              </Link>
            </div>
          </div>
        </div>
      </div>

      {/* KPI Row */}
      <div className="row g-3 mb-3">
        <div className="col-md-3 col-sm-6">
          <div className="card border-start border-4 border-primary shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small">{t('মোট নিবন্ধিত স্বেচ্ছাসেবক', 'Total Registered Volunteers')}</div>
              <div className="fs-3 fw-bold text-primary">{kpis.total_registered_volunteers ?? 0}</div>
              <small className="text-muted">{t('সংগঠন:', 'Organizations:')} {kpis.registered_organizations ?? 0}</small>
            </div>
          </div>
        </div>
        <div className="col-md-3 col-sm-6">
          <div className="card border-start border-4 border-success shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small">{t('যাচাইকৃত স্বেচ্ছাসেবক', 'Verified Volunteers')}</div>
              <div className="fs-3 fw-bold text-success">{kpis.verified_volunteers ?? 0}</div>
              <small className="text-muted">{t('অপেক্ষমাণ:', 'Pending:')} {kpis.pending_verification ?? 0}</small>
            </div>
          </div>
        </div>
        <div className="col-md-3 col-sm-6">
          <div className="card border-start border-4 border-info shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small">{t('মোতায়েনের জন্য উপলব্ধ', 'Available Volunteers')}</div>
              <div className="fs-3 fw-bold text-info">{kpis.available_volunteers ?? 0}</div>
              <small className="text-muted">{t('প্রশিক্ষণ সনদ:', 'Certificates:')} {kpis.certificates_issued ?? 0}</small>
            </div>
          </div>
        </div>
        <div className="col-md-3 col-sm-6">
          <div className="card border-start border-4 border-danger shadow-sm h-100">
            <div className="card-body">
              <div className="text-muted small">{t('সক্রিয় মোতায়েন', 'Active Deployments')}</div>
              <div className="fs-3 fw-bold text-danger">{kpis.active_deployments ?? 0}</div>
              <small className="text-muted">{t('সম্পন্ন মোতায়েন:', 'Completed:')} {kpis.completed_deployments ?? 0}</small>
            </div>
          </div>
        </div>
      </div>

      {/* Geographical & Skills Breakdown */}
      <div className="row g-3 mb-3">
        <div className="col-lg-6">
          <div className="card shadow-sm h-100">
            <div className="card-header bg-white fw-semibold">
              {t('ভৌগোলিক এলাকা অনুযায়ী স্বেচ্ছাসেবক', 'Volunteers by Geographical Area')}
            </div>
            <div className="table-responsive">
              <table className="table table-sm table-hover mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('প্রশাসনিক ইউনিট', 'Administrative Unit')}</th>
                    <th className="text-end">{t('মোট', 'Total')}</th>
                    <th className="text-end">{t('যাচাইকৃত', 'Verified')}</th>
                    <th className="text-end">{t('উপলব্ধ', 'Available')}</th>
                    <th className="text-end">{t('মোতায়েনকৃত', 'Deployed')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(dash.by_area || []).length === 0 ? (
                    <tr><td colSpan="5" className="text-center text-muted py-3">{t('কোনো তথ্য নেই', 'No area breakdown yet')}</td></tr>
                  ) : (
                    (dash.by_area || []).map((row) => (
                      <tr key={row.administrative_unit_id}>
                        <td>{t(row.name_bn, row.name_en)}</td>
                        <td className="text-end fw-semibold">{row.total}</td>
                        <td className="text-end text-success">{row.verified}</td>
                        <td className="text-end text-info">{row.available}</td>
                        <td className="text-end text-primary">{row.deployed}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="col-lg-6">
          <div className="card shadow-sm h-100">
            <div className="card-header bg-white fw-semibold">
              {t('দক্ষতা ও যোগ্যতার বিন্যাস', 'Skills & Qualifications Distribution')}
            </div>
            <div className="table-responsive">
              <table className="table table-sm table-hover mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('দক্ষতা', 'Skill')}</th>
                    <th className="text-end">{t('স্বেচ্ছাসেবক', 'Volunteers')}</th>
                    <th className="text-end">{t('যাচাইকৃত', 'Verified')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(dash.skills_distribution || []).length === 0 ? (
                    <tr><td colSpan="3" className="text-center text-muted py-3">{t('কোনো তথ্য নেই', 'No skills recorded yet')}</td></tr>
                  ) : (
                    (dash.skills_distribution || []).map((sk) => (
                      <tr key={sk.skill_id}>
                        <td>{t(sk.name_bn, sk.name_en)}</td>
                        <td className="text-end fw-semibold">{sk.volunteers_count}</td>
                        <td className="text-end text-success">{sk.verified_count}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      {/* Category Pills & GIS Map */}
      <div className="row g-3">
        <div className="col-lg-4">
          <div className="card shadow-sm h-100">
            <div className="card-header bg-white fw-semibold">{t('ক্যাটাগরি অনুযায়ী বণ্টন', 'Distribution by Category')}</div>
            <div className="card-body">
              {(dash.by_category || []).length === 0 ? (
                <p className="text-muted small mb-0">{t('তথ্য পাওয়া যায়নি।', 'No categories recorded.')}</p>
              ) : (
                <div className="d-flex flex-column gap-2">
                  {(dash.by_category || []).map((cat) => (
                    <div key={cat.code} className="d-flex justify-content-between align-items-center border-bottom pb-1">
                      <span>
                        <span className="badge me-2" style={{ backgroundColor: cat.color_hex || '#6c757d' }}>&nbsp;</span>
                        {t(cat.name_bn, cat.name_en)}
                      </span>
                      <span className="badge bg-light text-dark border">{cat.count}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
        <div className="col-lg-8">
          <div className="card shadow-sm">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between">
              <span>{t('স্বেচ্ছাসেবক ও সক্রিয় মোতায়েন মানচিত্র', 'Volunteers & Active Deployments GIS Map')}</span>
              <small className="text-muted">{t('নীল: সম্মতিপ্রাপ্ত বেস | লাল: সক্রিয় মোতায়েন', 'Blue: Consented Base | Red: Active Deployment')}</small>
            </div>
            <div className="card-body p-2">
              <VolunteerGisMap geojson={geoRes?.data} height="340px" />
            </div>
          </div>
        </div>
      </div>

      {/* Add Category Modal */}
      {showCatModal && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                createCatMutation.mutate(catForm);
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title">{t('নতুন স্বেচ্ছাসেবক ক্যাটাগরি', 'Add Volunteer Category')}</h5>
                <button type="button" className="btn-close" onClick={() => setShowCatModal(false)} />
              </div>
              <div className="modal-body">
                <div className="mb-2">
                  <label className="form-label small">{t('কোড (A-Z, _)', 'Code (A-Z, _)')} *</label>
                  <input className="form-control form-control-sm" required value={catForm.code} onChange={(e) => setCatForm({ ...catForm, code: e.target.value.toUpperCase() })} placeholder="LOGISTICS_SUPPORT" />
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('নাম (ইংরেজি)', 'Name (English)')} *</label>
                  <input className="form-control form-control-sm" required value={catForm.name_en} onChange={(e) => setCatForm({ ...catForm, name_en: e.target.value })} />
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('নাম (বাংলা)', 'Name (Bangla)')} *</label>
                  <input className="form-control form-control-sm" required value={catForm.name_bn} onChange={(e) => setCatForm({ ...catForm, name_bn: e.target.value })} />
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('রং', 'Color')}</label>
                  <input type="color" className="form-control form-control-color" value={catForm.color_hex} onChange={(e) => setCatForm({ ...catForm, color_hex: e.target.value })} />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setShowCatModal(false)}>{t('বাতিল', 'Cancel')}</button>
                <button type="submit" className="btn btn-primary btn-sm" disabled={createCatMutation.isPending}>{t('সংরক্ষণ', 'Save')}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Add Skill Modal */}
      {showSkillModal && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                createSkillMutation.mutate(skillForm);
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title">{t('নতুন দক্ষতা যোগ করুন', 'Add Skill to Catalog')}</h5>
                <button type="button" className="btn-close" onClick={() => setShowSkillModal(false)} />
              </div>
              <div className="modal-body">
                <div className="mb-2">
                  <label className="form-label small">{t('দক্ষতা কোড', 'Skill Code')} *</label>
                  <input className="form-control form-control-sm" required value={skillForm.skill_code} onChange={(e) => setSkillForm({ ...skillForm, skill_code: e.target.value.toUpperCase() })} placeholder="DRONE_SURVEY" />
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('ক্যাটাগরি', 'Category')}</label>
                  <select className="form-select form-select-sm" value={skillForm.category_id} onChange={(e) => setSkillForm({ ...skillForm, category_id: e.target.value })}>
                    <option value="">{t('নির্বাচন করুন', 'Select Category')}</option>
                    {(lookups.categories || []).map((c) => <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>)}
                  </select>
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('নাম (ইংরেজি)', 'Name (English)')} *</label>
                  <input className="form-control form-control-sm" required value={skillForm.name_en} onChange={(e) => setSkillForm({ ...skillForm, name_en: e.target.value })} />
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('নাম (বাংলা)', 'Name (Bangla)')} *</label>
                  <input className="form-control form-control-sm" required value={skillForm.name_bn} onChange={(e) => setSkillForm({ ...skillForm, name_bn: e.target.value })} />
                </div>
                <div className="form-check">
                  <input className="form-check-input" type="checkbox" checked={skillForm.requires_certification} onChange={(e) => setSkillForm({ ...skillForm, requires_certification: e.target.checked })} id="reqCertCheck" />
                  <label className="form-check-label small" htmlFor="reqCertCheck">{t('সনদপত্র প্রয়োজন', 'Requires Certification')}</label>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setShowSkillModal(false)}>{t('বাতিল', 'Cancel')}</button>
                <button type="submit" className="btn btn-primary btn-sm" disabled={createSkillMutation.isPending}>{t('সংরক্ষণ', 'Save')}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default VolunteerDashboardPage;
