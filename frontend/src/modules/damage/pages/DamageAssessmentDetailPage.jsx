import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { damageApi } from '../../../api/damageApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import DamageGisMap from '../components/DamageGisMap';
import {
  canApproveDamage,
  canSubmitDamage,
  canUpdateDamage,
  canVerifyDamage,
  formatBdt,
  formatDamageApiError,
  triggerBlobDownload,
} from '../utils/damagePermissions';
import { DamageSeverityBadge, DamageStatusBadge, DamageTypeLabel } from '../components/DamageStatusBadges';

export const DamageAssessmentDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [actionNotes, setActionNotes] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [uploadFile, setUploadFile] = useState(null);
  const [uploadTitle, setUploadTitle] = useState('');

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['damage', 'assessment', id],
    queryFn: () => damageApi.getAssessment(id),
  });

  const runActionMutation = useMutation({
    mutationFn: ({ action }) => {
      if (action === 'submit') return damageApi.submitAssessment(id, { notes: actionNotes });
      if (action === 'start') return damageApi.startVerification(id, { notes: actionNotes });
      if (action === 'correction') return damageApi.requestCorrection(id, { notes: actionNotes });
      if (action === 'verify') return damageApi.verifyAssessment(id, { notes: actionNotes });
      if (action === 'reject') return damageApi.rejectAssessment(id, { reason: actionNotes });
      if (action === 'approve') return damageApi.approveAssessment(id, { notes: actionNotes });
      throw new Error('Unknown action');
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['damage'] });
      setActionNotes('');
      setFeedback({ variant: 'success', message: t('অবস্থা সফলভাবে হালনাগাদ হয়েছে।', 'Workflow status updated successfully.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDamageApiError(err, t('কার্যক্রম ব্যর্থ হয়েছে।', 'Action failed.')) });
    },
  });

  const uploadMutation = useMutation({
    mutationFn: async () => {
      const fd = new FormData();
      fd.append('file', uploadFile);
      if (uploadTitle) fd.append('title', uploadTitle);
      return damageApi.uploadAttachment(id, fd);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['damage', 'assessment', id] });
      setUploadFile(null);
      setUploadTitle('');
      setFeedback({ variant: 'success', message: t('সংযুক্তি আপলোড হয়েছে।', 'Attachment uploaded.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatDamageApiError(err, t('আপলোড ব্যর্থ।', 'Upload failed.')) });
    },
  });

  const handleDownloadAttachment = async (att) => {
    try {
      const blobRes = await damageApi.downloadAttachment(id, att.id);
      triggerBlobDownload(blobRes.data, att.original_name || 'attachment');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatDamageApiError(err, t('ডাউনলোড ব্যর্থ।', 'Download failed.')) });
    }
  };

  if (isLoading) return <Loader />;
  if (error) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner variant="danger" message={formatDamageApiError(error, t('তথ্য লোড ব্যর্থ।', 'Failed to load assessment.'))} />
      </div>
    );
  }

  const assessment = res?.data;
  if (!assessment) return null;

  const isCreatorOrSubmitter =
    assessment.created_by === user?.id ||
    (assessment.submitted_by && assessment.submitted_by === user?.id);

  const singleGeoJson = {
    type: 'FeatureCollection',
    features: [
      ...(assessment.boundary_geojson
        ? [
            {
              type: 'Feature',
              geometry: assessment.boundary_geojson,
              properties: {
                id: assessment.id,
                d_form_code: assessment.d_form_code,
                verification_status: assessment.verification_status,
                severity_level: assessment.severity_level,
                affected_households: assessment.affected_households,
                total_estimated_loss_bdt: assessment.total_estimated_loss_bdt,
              },
            },
          ]
        : []),
      ...(assessment.latitude != null && assessment.longitude != null
        ? [
            {
              type: 'Feature',
              geometry: {
                type: 'Point',
                coordinates: [Number(assessment.longitude), Number(assessment.latitude)],
              },
              properties: {
                id: assessment.id,
                d_form_code: assessment.d_form_code,
                verification_status: assessment.verification_status,
                severity_level: assessment.severity_level,
                affected_households: assessment.affected_households,
                total_estimated_loss_bdt: assessment.total_estimated_loss_bdt,
              },
            },
          ]
        : []),
    ],
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <div className="d-flex align-items-center gap-2 mb-1">
            <h4 className="fw-bold mb-0">{assessment.d_form_code}</h4>
            <DamageStatusBadge status={assessment.verification_status} />
            <DamageSeverityBadge severity={assessment.severity_level} />
          </div>
          <div className="text-muted small">
            {t(assessment.disaster_event?.name_bn, assessment.disaster_event?.name_en)} |{' '}
            {t(assessment.administrative_unit?.name_bn, assessment.administrative_unit?.name_en)} |{' '}
            <DamageTypeLabel type={assessment.assessment_type} />
          </div>
        </div>

        <div className="d-flex gap-2">
          {assessment.is_editable && canUpdateDamage(user) && (
            <Link to={`/damage/assessments/${id}/edit`} className="btn btn-sm btn-outline-primary">
              <i className="bi bi-pencil me-1"></i>
              {t('সম্পাদনা', 'Edit D-Form')}
            </Link>
          )}
          <Link to="/damage/assessments" className="btn btn-sm btn-outline-secondary">
            <i className="bi bi-arrow-left me-1"></i>
            {t('তালিকায় ফিরুন', 'Back to Registry')}
          </Link>
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {assessment.correction_notes && (
        <AlertBanner
          variant="warning"
          message={`${t('সংশোধনের নির্দেশনা:', 'Correction Requested:')} ${assessment.correction_notes}`}
        />
      )}

      {assessment.rejection_reason && (
        <AlertBanner
          variant="danger"
          message={`${t('প্রত্যাখ্যানের কারণ:', 'Rejection Reason:')} ${assessment.rejection_reason}`}
        />
      )}

      {/* Summary KPI Row */}
      <div className="row g-3 mb-3">
        <div className="col-md-3">
          <div className="card border-0 shadow-sm p-3">
            <div className="small text-muted">{t('ক্ষতিগ্রস্ত পরিবার / জনসংখ্যা', 'Affected HH / Pop')}</div>
            <div className="fs-5 fw-bold text-primary">
              {Number(assessment.affected_households || 0).toLocaleString()} /{' '}
              {Number(assessment.affected_population || 0).toLocaleString()}
            </div>
          </div>
        </div>
        <div className="col-md-3">
          <div className="card border-0 shadow-sm p-3">
            <div className="small text-muted">{t('ক্ষয়ক্ষতির ইউনিট (পূর্ণ/আংশিক/সামান্য)', 'Units (Full/Part/Minor)')}</div>
            <div className="fs-5 fw-bold">
              {assessment.total_fully_damaged} / {assessment.total_partially_damaged} / {assessment.total_minor_damaged}
            </div>
          </div>
        </div>
        <div className="col-md-3">
          <div className="card border-0 shadow-sm p-3">
            <div className="small text-muted">{t('আনুমানিক আর্থিক ক্ষতি', 'Estimated Financial Loss')}</div>
            <div className="fs-5 fw-bold text-danger">{formatBdt(assessment.total_estimated_loss_bdt)}</div>
          </div>
        </div>
        <div className="col-md-3">
          <div className="card border-0 shadow-sm p-3">
            <div className="small text-muted">{t('মানবিক হতাহত (মৃত/আহত/নিখোঁজ)', 'Casualties (D/I/M)')}</div>
            <div className="fs-5 fw-bold">
              {assessment.deaths_count} / {assessment.injured_count} / {assessment.missing_count}
            </div>
          </div>
        </div>
      </div>

      {/* Verification Workflow Controls */}
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-header bg-white fw-semibold">
          {t('যাচাইকরণ ও অনুমোদন কর্মপ্রবাহ (Verification Workflow)', 'Verification & Approval Workflow')}
        </div>
        <div className="card-body">
          <div className="row g-2 align-items-end">
            <div className="col-md-6">
              <label className="form-label small fw-semibold">
                {t('মন্তব্য / সংশোধন বা প্রত্যাখ্যানের কারণ', 'Review Notes / Correction Instructions / Reason')}
              </label>
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('মন্তব্য লিখুন...', 'Enter review remarks or correction notes...')}
                value={actionNotes}
                onChange={(e) => setActionNotes(e.target.value)}
              />
            </div>

            <div className="col-md-6 d-flex flex-wrap gap-2">
              {assessment.is_editable && canSubmitDamage(user) && (
                <button
                  type="button"
                  className="btn btn-sm btn-primary"
                  disabled={runActionMutation.isPending}
                  onClick={() => runActionMutation.mutate({ action: 'submit' })}
                >
                  <i className="bi bi-send me-1"></i>
                  {t('দাখিল করুন (Submit)', 'Submit')}
                </button>
              )}

              {assessment.verification_status === 'SUBMITTED' && canVerifyDamage(user) && !isCreatorOrSubmitter && (
                <button
                  type="button"
                  className="btn btn-sm btn-outline-info"
                  disabled={runActionMutation.isPending}
                  onClick={() => runActionMutation.mutate({ action: 'start' })}
                >
                  {t('যাচাই শুরু করুন', 'Start Verification')}
                </button>
              )}

              {['SUBMITTED', 'UNDER_VERIFICATION'].includes(assessment.verification_status) &&
                canVerifyDamage(user) &&
                !isCreatorOrSubmitter && (
                  <>
                    <button
                      type="button"
                      className="btn btn-sm btn-warning"
                      disabled={runActionMutation.isPending || !actionNotes.trim()}
                      onClick={() => runActionMutation.mutate({ action: 'correction' })}
                    >
                      {t('সংশোধন চান', 'Request Correction')}
                    </button>
                    <button
                      type="button"
                      className="btn btn-sm btn-success"
                      disabled={runActionMutation.isPending}
                      onClick={() => runActionMutation.mutate({ action: 'verify' })}
                    >
                      <i className="bi bi-check2-circle me-1"></i>
                      {t('যাচাই সম্পন্ন (Verify)', 'Verify')}
                    </button>
                    <button
                      type="button"
                      className="btn btn-sm btn-danger"
                      disabled={runActionMutation.isPending || !actionNotes.trim()}
                      onClick={() => runActionMutation.mutate({ action: 'reject' })}
                    >
                      {t('প্রত্যাখ্যান (Reject)', 'Reject')}
                    </button>
                  </>
                )}

              {assessment.verification_status === 'VERIFIED' &&
                canApproveDamage(user) &&
                !isCreatorOrSubmitter && (
                  <button
                    type="button"
                    className="btn btn-sm btn-dark"
                    disabled={runActionMutation.isPending}
                    onClick={() => runActionMutation.mutate({ action: 'approve' })}
                  >
                    <i className="bi bi-patch-check-fill me-1"></i>
                    {t('চূড়ান্ত অনুমোদন (Approve)', 'Final Approve')}
                  </button>
                )}
            </div>
          </div>
          {isCreatorOrSubmitter && ['SUBMITTED', 'UNDER_VERIFICATION', 'VERIFIED'].includes(assessment.verification_status) && (
            <div className="small text-muted mt-2">
              <i className="bi bi-shield-lock me-1"></i>
              {t(
                '৪-চক্ষু নীতি (Separation of Duties): আপনি নিজের দাখিলকৃত ফর্ম নিজে যাচাই বা অনুমোদন করতে পারবেন না।',
                '4-Eye Separation of Duties: Creator/submitter cannot verify or approve their own assessment.'
              )}
            </div>
          )}
        </div>
      </div>

      <div className="row g-3">
        <div className="col-lg-7">
          {/* Sectoral Table */}
          <div className="card border-0 shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold">
              {t('খাতভিত্তিক ক্ষয়ক্ষতির বিবরণ', 'Sectoral Damage Breakdown')}
            </div>
            <div className="table-responsive">
              <table className="table table-sm table-hover mb-0 align-middle">
                <thead className="table-light">
                  <tr>
                    <th>{t('খাত ও বিবরণ', 'Category & Item')}</th>
                    <th className="text-end">{t('সম্পূর্ণ', 'Full')}</th>
                    <th className="text-end">{t('আংশিক', 'Partial')}</th>
                    <th className="text-end">{t('সামান্য', 'Minor')}</th>
                    <th className="text-end">{t('আর্থিক ক্ষতি', 'Est. Loss')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(assessment.sector_items || []).map((item) => (
                    <tr key={item.id}>
                      <td>
                        <div className="fw-medium">{t(item.category?.name_bn, item.category?.name_en)}</div>
                        <div className="small text-muted">{item.item_title || item.remarks || ''}</div>
                      </td>
                      <td className="text-end">{item.fully_damaged_count}</td>
                      <td className="text-end">{item.partially_damaged_count}</td>
                      <td className="text-end">{item.minor_damaged_count}</td>
                      <td className="text-end fw-semibold">{formatBdt(item.estimated_loss_bdt)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Field Observations & Metadata */}
          <div className="card border-0 shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold">
              {t('সংস্থা, দল ও মাঠ পর্যবেক্ষণ', 'Organization, Team & Observations')}
            </div>
            <div className="card-body small">
              <div className="row g-2 mb-2">
                <div className="col-md-6">
                  <strong>{t('সংস্থা', 'Organization')}:</strong> {assessment.assessing_organization}
                </div>
                <div className="col-md-6">
                  <strong>{t('দল', 'Team')}:</strong> {assessment.team_name || '—'}
                </div>
                <div className="col-md-6">
                  <strong>{t('মূল্যায়নকারী', 'Assessor')}:</strong> {assessment.assessor_name}{' '}
                  {assessment.assessor_designation ? `(${assessment.assessor_designation})` : ''}
                </div>
                <div className="col-md-6">
                  <strong>{t('তারিখ ও সময়', 'Date & Time')}:</strong> {assessment.assessment_date}
                </div>
              </div>
              <div className="p-2 bg-light rounded">
                <strong>{t('মাঠ পর্যবেক্ষণ', 'Field Observations')}:</strong>{' '}
                {assessment.field_observations || t('কোনো পর্যবেক্ষণ উল্লেখ নেই।', 'No observations recorded.')}
              </div>
            </div>
          </div>

          {/* Attachments */}
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white fw-semibold">
              {t('সংযুক্ত ছবি ও নথিপত্র (SHA-256 Verified)', 'Supporting Photographs & Documents')}
            </div>
            <div className="card-body">
              {assessment.is_editable && canUpdateDamage(user) && (
                <div className="row g-2 align-items-end mb-3 pb-2 border-bottom">
                  <div className="col-md-5">
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder={t('শিরোনাম (ঐচ্ছিক)', 'Title (optional)')}
                      value={uploadTitle}
                      onChange={(e) => setUploadTitle(e.target.value)}
                    />
                  </div>
                  <div className="col-md-5">
                    <input
                      type="file"
                      accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv"
                      className="form-control form-control-sm"
                      onChange={(e) => setUploadFile(e.target.files?.[0] || null)}
                    />
                  </div>
                  <div className="col-md-2">
                    <button
                      type="button"
                      className="btn btn-sm btn-outline-primary w-100"
                      disabled={!uploadFile || uploadMutation.isPending}
                      onClick={() => uploadMutation.mutate()}
                    >
                      {t('আপলোড', 'Upload')}
                    </button>
                  </div>
                </div>
              )}

              {(assessment.attachments || []).length === 0 ? (
                <div className="text-muted small">{t('কোনো সংযুক্তি নেই।', 'No attachments uploaded.')}</div>
              ) : (
                <ul className="list-group list-group-flush">
                  {assessment.attachments.map((att) => (
                    <li key={att.id} className="list-group-item px-0 d-flex justify-content-between align-items-center">
                      <div>
                        <span className="badge bg-light text-dark border me-2">{att.attachment_kind}</span>
                        <span className="fw-medium">{att.title || att.original_name}</span>
                        <div className="small text-muted">
                          SHA-256: <code>{att.sha256_hash?.slice(0, 16)}...</code>
                        </div>
                      </div>
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-success"
                        onClick={() => handleDownloadAttachment(att)}
                      >
                        <i className="bi bi-download me-1"></i>
                        {t('ডাউনলোড', 'Download')}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-5">
          {/* GIS Map */}
          <div className="card border-0 shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold">
              {t('অবস্থান ও জিআইএস মানচিত্র', 'GIS Location & Footprint')}
            </div>
            <div className="card-body">
              <DamageGisMap geojson={singleGeoJson} showHeatmap={false} showAreaSeverity={false} height="280px" />
            </div>
          </div>

          {/* Audit / Verification History */}
          <div className="card border-0 shadow-sm">
            <div className="card-header bg-white fw-semibold">
              {t('যাচাইকরণ ইতিহাস (Audit Timeline)', 'Verification Audit History')}
            </div>
            <div className="card-body">
              {(assessment.verification_logs || []).length === 0 ? (
                <div className="text-muted small">{t('কোনো ইতিহাস পাওয়া যায়নি।', 'No history entries yet.')}</div>
              ) : (
                <div className="d-flex flex-column gap-2">
                  {assessment.verification_logs.map((log) => (
                    <div key={log.id} className="border-start border-3 border-primary ps-2 py-1 small">
                      <div className="fw-semibold">
                        {log.action} ({log.from_status ? `${log.from_status} → ` : ''}{log.to_status})
                      </div>
                      <div className="text-muted">
                        {log.actor_name} ({log.actor_role}) — {log.created_at?.slice(0, 19).replace('T', ' ')}
                      </div>
                      {log.notes && <div className="mt-1">{log.notes}</div>}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DamageAssessmentDetailPage;
