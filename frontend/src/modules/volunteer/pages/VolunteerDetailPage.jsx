import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { volunteerApi } from '../../../api/volunteerApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  DeploymentStatusBadge,
  VolunteerAvailabilityBadge,
  VolunteerVerificationBadge,
} from '../components/VolunteerStatusBadges';
import {
  canDecryptVolunteerPii,
  canVerifyVolunteers,
  canWriteVolunteers,
  formatVolunteerApiError,
} from '../utils/volunteerPermissions';

export const VolunteerDetailPage = () => {
  const { id } = useParams();
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [revealedPii, setRevealedPii] = useState(null);
  const [photoUrl, setPhotoUrl] = useState(null);
  const [remarks, setRemarks] = useState('');
  const [rejectReason, setRejectReason] = useState('');
  const [feedback, setFeedback] = useState(null);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'detail', id],
    queryFn: () => volunteerApi.getVolunteer(id),
  });

  const volunteer = res?.data;

  useEffect(() => {
    let active = true;
    let objectUrl = null;
    if (volunteer?.has_photo) {
      volunteerApi.downloadPhoto(volunteer.id)
        .then((blob) => {
          if (!active) return;
          objectUrl = URL.createObjectURL(blob);
          setPhotoUrl(objectUrl);
        })
        .catch(() => {});
    }
    return () => {
      active = false;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [volunteer?.id, volunteer?.has_photo]);

  const pillarMutation = useMutation({
    mutationFn: (payload) => volunteerApi.verifyPillar(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setFeedback({ variant: 'success', message: t('যাচাই স্তম্ভ হালনাগাদ করা হয়েছে।', 'Verification pillar updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('যাচাই ব্যর্থ।', 'Verification failed.')) }),
  });

  const approveMutation = useMutation({
    mutationFn: () => volunteerApi.approveVolunteer(id, { remarks }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setFeedback({ variant: 'success', message: t('স্বেচ্ছাসেবক চূড়ান্তভাবে অনুমোদিত হয়েছে।', 'Volunteer approved successfully.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('অনুমোদন ব্যর্থ।', 'Approval failed.')) }),
  });

  const rejectMutation = useMutation({
    mutationFn: () => volunteerApi.rejectVolunteer(id, { reason: rejectReason }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setRejectReason('');
      setFeedback({ variant: 'warning', message: t('স্বেচ্ছাসেবক প্রত্যাখ্যাত হয়েছে।', 'Volunteer rejected.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('প্রত্যাখ্যান ব্যর্থ।', 'Rejection failed.')) }),
  });

  const submitMutation = useMutation({
    mutationFn: () => volunteerApi.submitVolunteer(id, { remarks }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setFeedback({ variant: 'info', message: t('যাচাইয়ের জন্য জমা দেওয়া হয়েছে।', 'Submitted for verification.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('জমা ব্যর্থ।', 'Submit failed.')) }),
  });

  const revealMutation = useMutation({
    mutationFn: () => volunteerApi.revealPii(id),
    onSuccess: (r) => {
      setRevealedPii(r.data);
      setFeedback({ variant: 'info', message: t('ব্যক্তিগত তথ্য অডিট লগসহ প্রদর্শিত হয়েছে।', 'PII revealed and logged to immutable audit trail.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('তথ্য প্রদর্শন অনুমোদিত নয়।', 'PII reveal denied.')) }),
  });

  if (isLoading) return <Loader />;
  if (error || !volunteer) return <AlertBanner variant="danger" message={formatVolunteerApiError(error, t('তথ্য লোড করা যায়নি।', 'Failed to load volunteer.'))} />;

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h3 className="mb-0">
            {t(volunteer.full_name_bn, volunteer.full_name_en)}{' '}
            <small className="text-muted fs-6">({volunteer.volunteer_code})</small>
          </h3>
          <div className="mt-1 d-flex gap-2">
            <VolunteerVerificationBadge status={volunteer.overall_verification_status} />
            <VolunteerAvailabilityBadge status={volunteer.availability_status} />
          </div>
        </div>
        <div className="d-flex gap-2">
          {canWriteVolunteers(user) && (
            <Link to={`/volunteer/volunteers/${id}/edit`} className="btn btn-outline-primary btn-sm">
              {t('সম্পাদনা', 'Edit Profile')}
            </Link>
          )}
          <Link to="/volunteer/volunteers" className="btn btn-outline-secondary btn-sm">
            {t('রোস্টারে ফিরুন', 'Back to Roster')}
          </Link>
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <div className="row g-3">
        {/* Profile & PII */}
        <div className="col-lg-5">
          <div className="card shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span>{t('ব্যক্তিগত ও যোগাযোগ তথ্য', 'Profile & Protected PII')}</span>
              {canDecryptVolunteerPii(user) && !revealedPii && (
                <button type="button" className="btn btn-outline-warning btn-sm" onClick={() => revealMutation.mutate()} disabled={revealMutation.isPending}>
                  <i className="bi bi-eye me-1"></i>{t('মূল তথ্য দেখুন (অডিটেড)', 'Reveal PII (Audited)')}
                </button>
              )}
            </div>
            <div className="card-body">
              {photoUrl && (
                <div className="mb-3 text-center">
                  <img src={photoUrl} alt="Volunteer" className="rounded border" style={{ maxHeight: 160, objectFit: 'cover' }} />
                </div>
              )}
              <dl className="row mb-0 small">
                <dt className="col-5">{t('সংগঠন', 'Organization')}</dt>
                <dd className="col-7">{volunteer.organization?.name_en || '-'}</dd>
                <dt className="col-5">{t('ক্যাটাগরি', 'Category')}</dt>
                <dd className="col-7">{volunteer.primary_category ? t(volunteer.primary_category.name_bn, volunteer.primary_category.name_en) : '-'}</dd>
                <dt className="col-5">{t('এলাকা', 'Jurisdiction')}</dt>
                <dd className="col-7">{volunteer.administrative_unit ? t(volunteer.administrative_unit.name_bn, volunteer.administrative_unit.name_en) : '-'}</dd>
                <dt className="col-5">{t('লিঙ্গ / জন্ম তারিখ', 'Gender / DoB')}</dt>
                <dd className="col-7">{volunteer.gender || '-'} / {volunteer.date_of_birth || '-'}</dd>
                <dt className="col-5">{t('এনআইডি', 'NID')}</dt>
                <dd className="col-7"><code>{revealedPii?.nid_number || volunteer.nid_masked || '-'}</code></dd>
                <dt className="col-5">{t('মোবাইল', 'Mobile')}</dt>
                <dd className="col-7"><code>{revealedPii?.mobile_number || volunteer.mobile_masked || '-'}</code></dd>
                <dt className="col-5">{t('ইমেইল', 'Email')}</dt>
                <dd className="col-7"><code>{revealedPii?.email || volunteer.email_masked || '-'}</code></dd>
                <dt className="col-5">{t('জরুরি যোগাযোগ', 'Emergency Contact')}</dt>
                <dd className="col-7">
                  {volunteer.emergency_contact_name || '-'} ({volunteer.emergency_contact_relation || '-'})<br />
                  <code>{revealedPii?.emergency_contact_phone || volunteer.emergency_contact_masked || '-'}</code>
                </dd>
                <dt className="col-5">{t('ঠিকানা', 'Address')}</dt>
                <dd className="col-7">{volunteer.present_address || '-'}</dd>
              </dl>
            </div>
          </div>

          {/* Skills */}
          <div className="card shadow-sm">
            <div className="card-header bg-white fw-semibold">{t('দক্ষতা ও সনদ', 'Skills & Certifications')}</div>
            <div className="card-body">
              {(volunteer.skills || []).length === 0 ? (
                <p className="text-muted small mb-0">{t('কোনো দক্ষতা যুক্ত নেই।', 'No skills assigned.')}</p>
              ) : (
                <ul className="list-group list-group-flush small">
                  {volunteer.skills.map((s) => (
                    <li key={s.id} className="list-group-item px-0 d-flex justify-content-between align-items-center">
                      <div>
                        <strong>{t(s.name_bn, s.name_en)}</strong>
                        <div className="text-muted">{s.proficiency_level} {s.certification_level ? `• ${s.certification_level}` : ''}</div>
                      </div>
                      <VolunteerVerificationBadge status={s.verification_status} />
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </div>
        </div>

        {/* 3-Pillar Verification, Deployments, Training */}
        <div className="col-lg-7">
          <div className="card shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold">{t('ত্রিস্তর যাচাই ও অনুমোদন কর্মপ্রবাহ', '3-Pillar Verification & Approval Workflow')}</div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                {[
                  { key: 'IDENTITY', label: t('পরিচয় যাচাই', 'Identity Verification'), status: volunteer.identity_verification_status },
                  { key: 'ORGANIZATION', label: t('সংগঠন যাচাই', 'Organization Verification'), status: volunteer.organization_verification_status },
                  { key: 'TRAINING', label: t('প্রশিক্ষণ যাচাই', 'Training Verification'), status: volunteer.training_verification_status },
                ].map((p) => (
                  <div key={p.key} className="col-md-4">
                    <div className="border rounded p-2 text-center">
                      <div className="small text-muted mb-1">{p.label}</div>
                      <VolunteerVerificationBadge status={p.status} />
                      {canVerifyVolunteers(user) && (
                        <div className="mt-2 d-flex justify-content-center gap-1">
                          <button type="button" className="btn btn-outline-success btn-sm py-0 px-2" onClick={() => pillarMutation.mutate({ pillar: p.key, status: 'VERIFIED', remarks })}>✓</button>
                          <button type="button" className="btn btn-outline-danger btn-sm py-0 px-2" onClick={() => pillarMutation.mutate({ pillar: p.key, status: 'REJECTED', remarks })}>✕</button>
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>

              {canWriteVolunteers(user) && volunteer.overall_verification_status === 'DRAFT' && (
                <button type="button" className="btn btn-info btn-sm me-2" onClick={() => submitMutation.mutate()}>
                  {t('যাচাইয়ের জন্য জমা দিন', 'Submit for Verification')}
                </button>
              )}

              {canVerifyVolunteers(user) && volunteer.overall_verification_status !== 'VERIFIED' && (
                <div className="border-top pt-2">
                  <input
                    className="form-control form-control-sm mb-2"
                    placeholder={t('যাচাই/অনুমোদন মন্তব্য...', 'Verification / approval remarks...')}
                    value={remarks}
                    onChange={(e) => setRemarks(e.target.value)}
                  />
                  <div className="d-flex flex-wrap gap-2">
                    <button type="button" className="btn btn-success btn-sm" onClick={() => approveMutation.mutate()}>
                      <i className="bi bi-check-circle me-1"></i>{t('চূড়ান্ত অনুমোদন', 'Approve Volunteer')}
                    </button>
                    <input
                      className="form-control form-control-sm w-auto flex-grow-1"
                      placeholder={t('প্রত্যাখ্যানের কারণ (ন্যূনতম ৫ অক্ষর)...', 'Rejection reason...')}
                      value={rejectReason}
                      onChange={(e) => setRejectReason(e.target.value)}
                    />
                    <button
                      type="button"
                      className="btn btn-danger btn-sm"
                      disabled={rejectReason.trim().length < 5}
                      onClick={() => rejectMutation.mutate()}
                    >
                      {t('প্রত্যাখ্যান', 'Reject')}
                    </button>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Deployments History */}
          <div className="card shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold">{t('মোতায়েন ইতিহাস', 'Deployment History')}</div>
            <div className="table-responsive">
              <table className="table table-sm mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('কোড', 'No')}</th>
                    <th>{t('দায়িত্ব', 'Role')}</th>
                    <th>{t('স্থান', 'Location')}</th>
                    <th>{t('সময়কাল', 'Dates')}</th>
                    <th>{t('অবস্থা', 'Status')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(volunteer.deployments || []).length === 0 ? (
                    <tr><td colSpan="5" className="text-center text-muted py-2">{t('কোনো মোতায়েন নেই', 'No deployments yet')}</td></tr>
                  ) : (
                    volunteer.deployments.map((d) => (
                      <tr key={d.id}>
                        <td>{d.deployment_no}</td>
                        <td>{d.assigned_role}</td>
                        <td>{d.deployment_location}</td>
                        <td>{d.start_date} {d.end_date ? `→ ${d.end_date}` : ''}</td>
                        <td><DeploymentStatusBadge status={d.deployment_status} /></td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {/* Training History */}
          <div className="card shadow-sm">
            <div className="card-header bg-white fw-semibold">{t('প্রশিক্ষণ ও সনদ ইতিহাস', 'Training & Certification History')}</div>
            <div className="table-responsive">
              <table className="table table-sm mb-0">
                <thead className="table-light">
                  <tr>
                    <th>{t('প্রশিক্ষণ', 'Program')}</th>
                    <th>{t('ঘণ্টা', 'Hours')}</th>
                    <th>{t('সনদ নং', 'Certificate No')}</th>
                    <th>{t('অবস্থা', 'Status')}</th>
                  </tr>
                </thead>
                <tbody>
                  {(volunteer.training_enrollments || []).length === 0 ? (
                    <tr><td colSpan="4" className="text-center text-muted py-2">{t('কোনো প্রশিক্ষণ রেকর্ড নেই', 'No training records')}</td></tr>
                  ) : (
                    volunteer.training_enrollments.map((tr) => (
                      <tr key={tr.id}>
                        <td>{t(tr.program_title_bn, tr.program_title_en)}</td>
                        <td>{tr.attendance_hours}</td>
                        <td>{tr.certificate_no || '-'}</td>
                        <td><span className={`badge bg-${tr.passed ? 'success' : 'secondary'}`}>{tr.enrollment_status}</span></td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default VolunteerDetailPage;
