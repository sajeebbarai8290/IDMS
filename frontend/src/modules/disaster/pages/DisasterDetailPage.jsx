import React, { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import disasterApi from '../../../api/disasterApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  canBroadcastWarning,
  canUpdateDisaster,
  canVerifyDisaster,
  LIFECYCLE_PHASES,
} from '../utils/disasterPermissions';
import { DisasterPhaseBadge, DisasterSeverityBadge } from '../components/DisasterStatusBadges';
import DisasterGisMap from '../components/DisasterGisMap';
import { parseSpatialFile } from '../utils/shapefileParser';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';

export const DisasterDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [lifecycleModal, setLifecycleModal] = useState(false);
  const [targetPhase, setTargetPhase] = useState('VERIFIED');
  const [phaseNotes, setPhaseNotes] = useState('');

  const [warningModal, setWarningModal] = useState(false);
  const [warningDraft, setWarningDraft] = useState({
    warning_level: 'WARNING',
    title_en: '',
    title_bn: '',
    message_en: '',
    message_bn: '',
    issuing_authority: 'NEOC / MoDMR',
    expires_at: '',
  });

  const [timelineDraft, setTimelineDraft] = useState({
    title_en: '',
    title_bn: '',
    notes: '',
    file: null,
  });

  const [boundaryModal, setBoundaryModal] = useState(false);
  const [boundaryState, setBoundaryState] = useState({
    parsed: null,
    file: null,
    notes: '',
    error: '',
  });

  const { data: d, isLoading, error } = useQuery({
    queryKey: ['disasterEvent', id],
    queryFn: async () => {
      const res = await disasterApi.getEvent(id);
      return res?.disaster || null;
    },
  });

  const lifecycleMutation = useMutation({
    mutationFn: (payload) => disasterApi.transitionLifecycle(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['disasterEvent', id]);
      queryClient.invalidateQueries(['disasterDashboard']);
      setLifecycleModal(false);
      setPhaseNotes('');
    },
  });

  const warningMutation = useMutation({
    mutationFn: (payload) => disasterApi.issueWarning(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries(['disasterEvent', id]);
      queryClient.invalidateQueries(['disasterDashboard']);
      setWarningModal(false);
      setWarningDraft({
        warning_level: 'WARNING',
        title_en: '',
        title_bn: '',
        message_en: '',
        message_bn: '',
        issuing_authority: 'NEOC / MoDMR',
        expires_at: '',
      });
    },
  });

  const timelineMutation = useMutation({
    mutationFn: (formData) => disasterApi.addTimeline(id, formData),
    onSuccess: () => {
      queryClient.invalidateQueries(['disasterEvent', id]);
      setTimelineDraft({ title_en: '', title_bn: '', notes: '', file: null });
    },
  });

  const boundaryMutation = useMutation({
    mutationFn: (formData) => disasterApi.uploadBoundary(id, formData),
    onSuccess: () => {
      queryClient.invalidateQueries(['disasterEvent', id]);
      queryClient.invalidateQueries(['disasterGisExplorer']);
      queryClient.invalidateQueries(['disasterDashboardGeojson']);
      setBoundaryModal(false);
      setBoundaryState({ parsed: null, file: null, notes: '', error: '' });
    },
    onError: (err) => {
      setBoundaryState((prev) => ({
        ...prev,
        error: err?.message || 'Failed to save boundary.',
      }));
    },
  });

  if (isLoading) {
    return <Loader message="দুর্যোগের বিস্তারিত তথ্য লোড হচ্ছে..." messageEn="Loading disaster details..." />;
  }

  if (error || !d) {
    return (
      <div className="container py-3">
        <AlertBanner
          type="danger"
          message={t('দুর্যোগ তথ্য পাওয়া যায়নি: ', 'Disaster record not found: ')}
          details={error?.message}
        />
        <Link to="/disaster/events" className="btn btn-secondary btn-sm mt-2">
          {t('তালিকায় ফিরুন', 'Back to Disasters')}
        </Link>
      </div>
    );
  }

  const singleGeoJson = {
    type: 'FeatureCollection',
    features: [
      ...(d.epicenter_lat !== null && d.epicenter_lng !== null
        ? [
            {
              type: 'Feature',
              id: `${d.id}-point`,
              geometry: { type: 'Point', coordinates: [Number(d.epicenter_lng), Number(d.epicenter_lat)] },
              properties: {
                id: d.id,
                disaster_code: d.disaster_code,
                name_en: d.name_en,
                name_bn: d.name_bn,
                disaster_type: d.disaster_type,
                severity_level: d.severity_level,
                lifecycle_phase: d.lifecycle_phase,
                affected_population: d.impact?.affected_population || 0,
              },
            },
          ]
        : []),
      ...(d.affected_zone_geojson
        ? [
            {
              type: 'Feature',
              id: `${d.id}-zone`,
              geometry: d.affected_zone_geojson,
              properties: {
                id: d.id,
                disaster_code: d.disaster_code,
                name_en: d.name_en,
                name_bn: d.name_bn,
                disaster_type: d.disaster_type,
                severity_level: d.severity_level,
                lifecycle_phase: d.lifecycle_phase,
                affected_population: d.impact?.affected_population || 0,
              },
            },
          ]
        : []),
    ],
  };

  const handleTimelineSubmit = (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('title_en', timelineDraft.title_en);
    if (timelineDraft.title_bn) fd.append('title_bn', timelineDraft.title_bn);
    if (timelineDraft.notes) fd.append('notes', timelineDraft.notes);
    if (timelineDraft.file) fd.append('attachment', timelineDraft.file);
    timelineMutation.mutate(fd);
  };

  const handleDownloadAttachment = async (timeline) => {
    try {
      const blob = await disasterApi.downloadAttachmentBlob(d.id, timeline.id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = timeline.attachment_original_name || 'attachment';
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      alert(err?.message || 'Download failed.');
    }
  };

  const imp = d.impact || {};
  const availableTransitions = LIFECYCLE_PHASES.filter((p) => {
    if (p.value === d.lifecycle_phase) return false;
    if (['VERIFIED', 'ACTIVE', 'CLOSED'].includes(p.value)) {
      return canVerifyDisaster(user);
    }
    return canUpdateDisaster(user);
  });

  return (
    <div className="container-fluid py-2" style={{ maxWidth: '1200px' }}>
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <nav aria-label="breadcrumb">
            <ol className="breadcrumb mb-1 small">
              <li className="breadcrumb-item">
                <Link to="/disaster/dashboard">{t('দুর্যোগ ড্যাশবোর্ড', 'Disaster Dashboard')}</Link>
              </li>
              <li className="breadcrumb-item">
                <Link to="/disaster/events">{t('তালিকা', 'Events')}</Link>
              </li>
              <li className="breadcrumb-item active font-monospace">{d.disaster_code}</li>
            </ol>
          </nav>
          <h4 className="fw-bold mb-1">
            {t(d.name_bn, d.name_en)}{' '}
            <span className="text-muted fs-6 font-monospace">({d.disaster_code})</span>
          </h4>
          <div className="d-flex flex-wrap gap-2 align-items-center">
            <DisasterSeverityBadge severity={d.severity_level} />
            <DisasterPhaseBadge phase={d.lifecycle_phase} />
            <span className="badge bg-light text-dark border">{d.disaster_type}</span>
            {d.is_emergency_declared && <span className="badge bg-danger">EMERGENCY DECLARED</span>}
          </div>
        </div>

        <div className="d-flex flex-wrap gap-2">
          {canUpdateDisaster(user) && (
            <Link to={`/disaster/events/${d.id}/edit`} className="btn btn-outline-primary btn-sm">
              <i className="bi bi-pencil me-1"></i>
              {t('তথ্য ও ক্ষয়ক্ষতি সম্পাদনা', 'Edit Event & Impact')}
            </Link>
          )}
          {availableTransitions.length > 0 && (
            <button
              type="button"
              className="btn btn-outline-success btn-sm"
              onClick={() => {
                setTargetPhase(availableTransitions[0].value);
                setLifecycleModal(true);
              }}
            >
              <i className="bi bi-arrow-right-circle me-1"></i>
              {t('অবস্থা পরিবর্তন', 'Change Lifecycle')}
            </button>
          )}
          {canBroadcastWarning(user) && (
            <button
              type="button"
              className="btn btn-warning btn-sm fw-semibold"
              onClick={() => setWarningModal(true)}
            >
              <i className="bi bi-broadcast me-1"></i>
              {t('সতর্কবার্তা জারি', 'Issue Early Warning')}
            </button>
          )}
        </div>
      </div>

      {/* Overview & GIS Map */}
      <div className="row g-3 mb-4">
        <div className="col-lg-6">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-bold">
              {t('দুর্যোগ সারসংক্ষেপ ও প্রভাবিত এলাকা', 'Overview & Impact Summary')}
            </div>
            <div className="card-body small">
              <p className="mb-3">{t(d.description_bn || d.description_en, d.description_en || d.description_bn) || '—'}</p>

              <div className="row g-2 mb-3">
                <div className="col-6">
                  <div className="text-muted">{t('শুরুর তারিখ', 'Start Date')}</div>
                  <div className="fw-bold">{d.start_date || '—'}</div>
                </div>
                <div className="col-6">
                  <div className="text-muted">{t('সমাপ্তির তারিখ', 'End Date')}</div>
                  <div className="fw-bold">{d.end_date || t('চলমান', 'Ongoing')}</div>
                </div>
                <div className="col-12">
                  <div className="text-muted">{t('প্রভাবিত এলাকা', 'Affected Areas')}</div>
                  <div className="fw-bold">{d.affected_areas_summary || '—'}</div>
                  <div className="d-flex flex-wrap gap-1 mt-1">
                    {(d.locations || []).map((loc) => (
                      <span key={loc.id} className="badge bg-light text-dark border">
                        {t(loc.administrative_unit?.name_bn, loc.administrative_unit?.name_en)} (
                        {loc.administrative_unit?.tier_level})
                      </span>
                    ))}
                  </div>
                </div>
              </div>

              <hr />
              <div className="row g-2 text-center">
                <div className="col-4">
                  <div className="p-2 bg-light rounded">
                    <div className="text-muted">{t('ক্ষতিগ্রস্ত জনসংখ্যা', 'Affected Pop')}</div>
                    <div className="fw-bold fs-6 text-primary">{Number(imp.affected_population || 0).toLocaleString()}</div>
                  </div>
                </div>
                <div className="col-4">
                  <div className="p-2 bg-light rounded">
                    <div className="text-muted">{t('মৃত্যু / আহত', 'Deaths / Injured')}</div>
                    <div className="fw-bold fs-6 text-danger">
                      {imp.deaths_count ?? 0} / {imp.injured_count ?? 0}
                    </div>
                  </div>
                </div>
                <div className="col-4">
                  <div className="p-2 bg-light rounded">
                    <div className="text-muted">{t('অর্থনৈতিক ক্ষতি (৳)', 'Economic Loss')}</div>
                    <div className="fw-bold fs-6">{Number(imp.total_economic_loss_bdt || 0).toLocaleString()}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="col-lg-6">
          <div className="d-flex justify-content-between align-items-center mb-2">
            <span className="small fw-bold text-muted">
              <i className="bi bi-map me-1"></i>
              {t('দুর্যোগ জিআইএস অবস্থান ও সীমানা', 'GIS Epicenter & Affected Boundary')}
            </span>
            {canUpdateDisaster(user) && (
              <button
                type="button"
                className="btn btn-outline-success btn-sm"
                onClick={() => setBoundaryModal(true)}
              >
                <i className="bi bi-file-earmark-zip me-1"></i>
                {t('শেপফাইল / সীমানা আপলোড', 'Upload Shapefile Boundary')}
              </button>
            )}
          </div>
          <DisasterGisMap geojson={singleGeoJson} height="320px" mode="explore" />
        </div>
      </div>

      {/* Early Warnings */}
      <div className="card shadow-sm border-0 mb-4">
        <div className="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
          <span>
            <i className="bi bi-broadcast text-warning me-2"></i>
            {t('প্রাথমিক সতর্কবার্তা ও বুলেটিন', 'Early Warning Bulletins')} ({(d.warnings || []).length})
          </span>
        </div>
        <div className="card-body">
          {(d.warnings || []).length === 0 ? (
            <p className="text-muted small mb-0">{t('কোনো সতর্কবার্তা জারি করা হয়নি।', 'No early warnings issued yet.')}</p>
          ) : (
            <div className="list-group list-group-flush">
              {d.warnings.map((w) => (
                <div key={w.id} className="list-group-item px-0">
                  <div className="d-flex justify-content-between align-items-center mb-1">
                    <div>
                      <span className="badge bg-danger me-2">{w.warning_level}</span>
                      <strong>{t(w.title_bn || w.title_en, w.title_en)}</strong>
                    </div>
                    <span className="badge bg-light text-dark border font-monospace">{w.warning_code}</span>
                  </div>
                  <p className="small mb-1">{t(w.message_bn || w.message_en, w.message_en)}</p>
                  <div className="small text-muted">
                    {t('কর্তৃপক্ষ:', 'Authority:')} <strong>{w.issuing_authority}</strong> •{' '}
                    {t('প্রেরণ:', 'Dispatch:')} <span className="badge bg-info-subtle text-info-emphasis">{w.notification_dispatch_status}</span> •{' '}
                    {w.issued_at ? new Date(w.issued_at).toLocaleString() : ''}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Timeline & Attachments */}
      <div className="card shadow-sm border-0">
        <div className="card-header bg-white fw-bold">
          <i className="bi bi-clock-history text-primary me-2"></i>
          {t('দুর্যোগ টাইমলাইন, হালনাগাদ ও সংযুক্তি', 'Disaster Timeline, Updates & Attachments')}
        </div>
        <div className="card-body">
          {canUpdateDisaster(user) && (
            <form className="bg-light p-3 rounded mb-3" onSubmit={handleTimelineSubmit}>
              <div className="row g-2">
                <div className="col-md-4">
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder={t('আপডেটের শিরোনাম (ইংরেজি) *', 'Update title (English) *')}
                    value={timelineDraft.title_en}
                    onChange={(e) => setTimelineDraft({ ...timelineDraft, title_en: e.target.value })}
                    required
                  />
                </div>
                <div className="col-md-4">
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder={t('আপডেটের শিরোনাম (বাংলা)', 'Update title (Bangla)')}
                    value={timelineDraft.title_bn}
                    onChange={(e) => setTimelineDraft({ ...timelineDraft, title_bn: e.target.value })}
                  />
                </div>
                <div className="col-md-4">
                  <input
                    type="file"
                    className="form-control form-control-sm"
                    onChange={(e) => setTimelineDraft({ ...timelineDraft, file: e.target.files?.[0] || null })}
                  />
                </div>
                <div className="col-md-10">
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder={t('বিস্তারিত নোট বা মাঠ পর্যায়ের প্রতিবেদন...', 'Detailed notes...')}
                    value={timelineDraft.notes}
                    onChange={(e) => setTimelineDraft({ ...timelineDraft, notes: e.target.value })}
                  />
                </div>
                <div className="col-md-2 d-grid">
                  <button type="submit" className="btn btn-primary btn-sm" disabled={timelineMutation.isPending}>
                    {timelineMutation.isPending ? t('যোগ হচ্ছে...', 'Saving...') : t('হালনাগাদ যোগ করুন', 'Add Entry')}
                  </button>
                </div>
              </div>
            </form>
          )}

          <div className="list-group list-group-flush">
            {(d.timelines || []).map((tl) => (
              <div key={tl.id} className="list-group-item px-0 small">
                <div className="d-flex justify-content-between align-items-center">
                  <div>
                    <span className="badge bg-secondary-subtle text-secondary me-2">{tl.event_type}</span>
                    <strong>{t(tl.title_bn || tl.title_en, tl.title_en)}</strong>
                  </div>
                  <span className="text-muted">
                    {tl.occurred_at ? new Date(tl.occurred_at).toLocaleString() : ''}
                  </span>
                </div>
                {tl.notes && <div className="text-dark mt-1">{tl.notes}</div>}
                <div className="d-flex justify-content-between align-items-center mt-1 text-muted">
                  <span>{t('রেকর্ডকারী:', 'Recorded by:')} {tl.recorded_by}</span>
                  {tl.has_attachment && (
                    <button
                      type="button"
                      className="btn btn-outline-secondary btn-sm py-0 px-2"
                      onClick={() => handleDownloadAttachment(tl)}
                    >
                      <i className="bi bi-paperclip me-1"></i>
                      {tl.attachment_original_name}
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Modal: Lifecycle Transition */}
      {lifecycleModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg">
              <div className="modal-header">
                <h5 className="modal-title fw-bold">{t('দুর্যোগের অবস্থা পরিবর্তন', 'Transition Disaster Lifecycle')}</h5>
                <button type="button" className="btn-close" onClick={() => setLifecycleModal(false)}></button>
              </div>
              <div className="modal-body">
                <div className="mb-3">
                  <label className="form-label small fw-bold">{t('নতুন অবস্থা', 'Target Phase')}</label>
                  <select className="form-select form-select-sm" value={targetPhase} onChange={(e) => setTargetPhase(e.target.value)}>
                    {availableTransitions.map((p) => (
                      <option key={p.value} value={p.value}>
                        {t(p.bn, p.en)} ({p.value})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-bold">{t('মন্তব্য', 'Notes')}</label>
                  <textarea
                    className="form-control form-control-sm"
                    rows="3"
                    value={phaseNotes}
                    onChange={(e) => setPhaseNotes(e.target.value)}
                  ></textarea>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setLifecycleModal(false)}>
                  {t('বাতিল', 'Cancel')}
                </button>
                <button
                  type="button"
                  className="btn btn-primary btn-sm"
                  disabled={lifecycleMutation.isPending}
                  onClick={() => lifecycleMutation.mutate({ lifecycle_phase: targetPhase, notes: phaseNotes })}
                >
                  {t('নিশ্চিত করুন', 'Confirm Transition')}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Modal: Issue Early Warning */}
      {warningModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-lg modal-dialog-centered">
            <form
              className="modal-content shadow-lg"
              onSubmit={(e) => {
                e.preventDefault();
                warningMutation.mutate({
                  ...warningDraft,
                  expires_at: warningDraft.expires_at || undefined,
                });
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title fw-bold">{t('প্রাথমিক সতর্কবার্তা জারি করুন', 'Issue Early Warning Bulletin')}</h5>
                <button type="button" className="btn-close" onClick={() => setWarningModal(false)}></button>
              </div>
              <div className="modal-body small">
                <div className="row g-2">
                  <div className="col-md-4">
                    <label className="form-label fw-bold">{t('সতর্কতা স্তর *', 'Warning Level *')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={warningDraft.warning_level}
                      onChange={(e) => setWarningDraft({ ...warningDraft, warning_level: e.target.value })}
                    >
                      {['ADVISORY', 'WATCH', 'WARNING', 'DANGER', 'GREAT_DANGER', 'EVACUATION_ORDER'].map((lvl) => (
                        <option key={lvl} value={lvl}>
                          {lvl}
                        </option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-4">
                    <label className="form-label fw-bold">{t('জারিকারী কর্তৃপক্ষ *', 'Issuing Authority *')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={warningDraft.issuing_authority}
                      onChange={(e) => setWarningDraft({ ...warningDraft, issuing_authority: e.target.value })}
                      required
                    />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label fw-bold">{t('মেয়াদ শেষ', 'Expires At')}</label>
                    <input
                      type="datetime-local"
                      className="form-control form-control-sm"
                      value={warningDraft.expires_at}
                      onChange={(e) => setWarningDraft({ ...warningDraft, expires_at: e.target.value })}
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label fw-bold">{t('শিরোনাম (ইংরেজি) *', 'Title (English) *')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={warningDraft.title_en}
                      onChange={(e) => setWarningDraft({ ...warningDraft, title_en: e.target.value })}
                      required
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label fw-bold">{t('শিরোনাম (বাংলা)', 'Title (Bangla)')}</label>
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      value={warningDraft.title_bn}
                      onChange={(e) => setWarningDraft({ ...warningDraft, title_bn: e.target.value })}
                    />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label fw-bold">{t('বার্তা (ইংরেজি) *', 'Message (English) *')}</label>
                    <textarea
                      className="form-control form-control-sm"
                      rows="3"
                      value={warningDraft.message_en}
                      onChange={(e) => setWarningDraft({ ...warningDraft, message_en: e.target.value })}
                      required
                    ></textarea>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label fw-bold">{t('বার্তা (বাংলা)', 'Message (Bangla)')}</label>
                    <textarea
                      className="form-control form-control-sm"
                      rows="3"
                      value={warningDraft.message_bn}
                      onChange={(e) => setWarningDraft({ ...warningDraft, message_bn: e.target.value })}
                    ></textarea>
                  </div>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setWarningModal(false)}>
                  {t('বাতিল', 'Cancel')}
                </button>
                <button type="submit" className="btn btn-warning btn-sm fw-bold" disabled={warningMutation.isPending}>
                  {t('সতর্কবার্তা পাঠান', 'Dispatch Warning')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: Upload Shapefile / GeoJSON Boundary */}
      {boundaryModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <form
              className="modal-content shadow-lg"
              onSubmit={(e) => {
                e.preventDefault();
                if (!boundaryState.parsed) return;
                const fd = new FormData();
                fd.append('affected_zone_geojson', JSON.stringify(boundaryState.parsed.geometry));
                if (boundaryState.parsed.centroid) {
                  fd.append('epicenter_lat', String(boundaryState.parsed.centroid.latitude));
                  fd.append('epicenter_lng', String(boundaryState.parsed.centroid.longitude));
                }
                fd.append('source_filename', boundaryState.parsed.fileName);
                if (boundaryState.file) fd.append('shapefile_archive', boundaryState.file);
                if (boundaryState.notes) fd.append('notes', boundaryState.notes);
                boundaryMutation.mutate(fd);
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title fw-bold">
                  <i className="bi bi-file-earmark-zip text-success me-2"></i>
                  {t('শেপফাইল (.zip) / GeoJSON আপলোড', 'Upload Shapefile (.zip) / GeoJSON')}
                </h5>
                <button type="button" className="btn-close" onClick={() => setBoundaryModal(false)}></button>
              </div>
              <div className="modal-body small">
                {boundaryState.error && <div className="alert alert-danger py-2">{boundaryState.error}</div>}
                <div className="mb-3">
                  <label className="form-label fw-bold">
                    {t('জিপ করা শেপফাইল (.zip) অথবা GeoJSON (.geojson) নির্বাচন করুন *', 'Select Zipped Shapefile (.zip) or GeoJSON *')}
                  </label>
                  <input
                    type="file"
                    accept=".zip,.geojson,.json"
                    className="form-control form-control-sm"
                    onChange={async (e) => {
                      const file = e.target.files?.[0];
                      if (!file) return;
                      try {
                        const parsed = await parseSpatialFile(file);
                        setBoundaryState((prev) => ({ ...prev, parsed, file, error: '' }));
                      } catch (err) {
                        setBoundaryState((prev) => ({
                          ...prev,
                          parsed: null,
                          file: null,
                          error: err?.message || 'Invalid shapefile.',
                        }));
                      }
                    }}
                    required
                  />
                </div>
                {boundaryState.parsed && (
                  <div className="alert alert-success py-2">
                    <strong>{boundaryState.parsed.fileName}</strong> — {boundaryState.parsed.geometry.type} (
                    {boundaryState.parsed.featureCount} {t('টি পলিগন', 'polygons')})
                  </div>
                )}
                <div className="mb-2">
                  <label className="form-label fw-bold">{t('মন্তব্য', 'Notes')}</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder={t('সীমানা উৎসের বিবরণ...', 'Boundary source description...')}
                    value={boundaryState.notes}
                    onChange={(e) => setBoundaryState((prev) => ({ ...prev, notes: e.target.value }))}
                  />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setBoundaryModal(false)}>
                  {t('বাতিল', 'Cancel')}
                </button>
                <button
                  type="submit"
                  className="btn btn-success btn-sm"
                  disabled={!boundaryState.parsed || boundaryMutation.isPending}
                >
                  {boundaryMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Uploading...') : t('সীমানা সংরক্ষণ করুন', 'Save Boundary')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default DisasterDetailPage;
