import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { volunteerApi } from '../../../api/volunteerApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import {
  canManageTrainingPrograms,
  canManageTrainingSessions,
  formatVolunteerApiError,
} from '../utils/volunteerPermissions';

export const VolunteerTrainingPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [showProgModal, setShowProgModal] = useState(false);
  const [showSesModal, setShowSesModal] = useState(false);
  const [enrollTarget, setEnrollTarget] = useState(null);
  const [enrollVolunteerId, setEnrollVolunteerId] = useState('');
  const [feedback, setFeedback] = useState(null);

  const [progForm, setProgForm] = useState({
    title_en: '',
    title_bn: '',
    category_id: '',
    skill_id: '',
    provider_name: '',
    duration_hours: 16,
    validity_months: 24,
  });

  const [sesForm, setSesForm] = useState({
    program_id: '',
    administrative_unit_id: '',
    venue: '',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: new Date().toISOString().slice(0, 10),
    trainer_name: '',
    max_participants: 30,
  });

  const { data: lookupsRes } = useQuery({ queryKey: ['volunteer', 'lookups'], queryFn: () => volunteerApi.getLookups() });
  const { data: progRes, isLoading: loadingProg } = useQuery({ queryKey: ['volunteer', 'training-programs'], queryFn: () => volunteerApi.getTrainingPrograms() });
  const { data: sesRes, isLoading: loadingSes } = useQuery({ queryKey: ['volunteer', 'training-sessions'], queryFn: () => volunteerApi.getTrainingSessions() });
  const { data: volRes } = useQuery({ queryKey: ['volunteer', 'volunteers', 'all'], queryFn: () => volunteerApi.getVolunteers({ per_page: 100 }) });

  const createProgMutation = useMutation({
    mutationFn: (payload) => volunteerApi.createTrainingProgram(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setShowProgModal(false);
      setFeedback({ variant: 'success', message: t('প্রশিক্ষণ প্রোগ্রাম সংরক্ষিত হয়েছে।', 'Training program created.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('সংরক্ষণ ব্যর্থ।', 'Save failed.')) }),
  });

  const createSesMutation = useMutation({
    mutationFn: (payload) => volunteerApi.createTrainingSession(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setShowSesModal(false);
      setFeedback({ variant: 'success', message: t('প্রশিক্ষণ সেশন নির্ধারিত হয়েছে।', 'Training session scheduled.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('সংরক্ষণ ব্যর্থ।', 'Save failed.')) }),
  });

  const enrollMutation = useMutation({
    mutationFn: ({ sessionId, volunteerId }) => volunteerApi.enrollVolunteer(sessionId, { volunteer_id: volunteerId }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setEnrollTarget(null);
      setEnrollVolunteerId('');
      setFeedback({ variant: 'success', message: t('স্বেচ্ছাসেবক প্রশিক্ষণে নিবন্ধিত হয়েছে।', 'Volunteer enrolled in session.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('নিবন্ধন ব্যর্থ।', 'Enrollment failed.')) }),
  });

  const certifyMutation = useMutation({
    mutationFn: ({ enrollmentId, hours }) =>
      volunteerApi.updateEnrollment(enrollmentId, {
        enrollment_status: 'ATTENDED',
        attendance_hours: hours,
        passed: true,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setFeedback({ variant: 'success', message: t('উপস্থিতি ও সনদপত্র ইস্যু করা হয়েছে।', 'Attendance recorded & certificate issued.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('সনদ ইস্যু ব্যর্থ।', 'Certification failed.')) }),
  });

  const lookups = lookupsRes?.data || {};
  const programs = progRes?.data || [];
  const sessions = sesRes?.data || [];
  const volunteers = volRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h3 className="mb-0">{t('প্রশিক্ষণ ও সনদ ব্যবস্থাপনা (Training Management)', 'Training & Certification Management')}</h3>
          <small className="text-muted">{t('প্রশিক্ষণ প্রোগ্রাম, সময়সূচী, উপস্থিতি ও সনদপত্র', 'Training programs, schedules, attendance & certificates')}</small>
        </div>
        <div className="d-flex gap-2">
          {canManageTrainingPrograms(user) && (
            <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => setShowProgModal(true)}>
              {t('নতুন প্রোগ্রাম', 'New Program')}
            </button>
          )}
          {canManageTrainingSessions(user) && (
            <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowSesModal(true)}>
              {t('নতুন সেশন সময়সূচী', 'Schedule Session')}
            </button>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <div className="row g-3">
        <div className="col-lg-4">
          <div className="card shadow-sm h-100">
            <div className="card-header bg-white fw-semibold">{t('প্রশিক্ষণ প্রোগ্রামসমূহ', 'Training Programs')}</div>
            <div className="card-body">
              {loadingProg ? <Loader /> : programs.length === 0 ? <EmptyState title={t('কোনো প্রোগ্রাম নেই', 'No programs')} /> : (
                <div className="d-flex flex-column gap-2">
                  {programs.map((p) => (
                    <div key={p.id} className="border rounded p-2">
                      <div className="fw-semibold small">{p.program_code} — {t(p.title_bn, p.title_en)}</div>
                      <div className="text-muted small">{p.provider_name} • {p.duration_hours} hrs</div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-8">
          <div className="card shadow-sm">
            <div className="card-header bg-white fw-semibold">{t('প্রশিক্ষণ সেশন ও সনদপত্র', 'Training Schedules, Attendance & Certificates')}</div>
            <div className="card-body">
              {loadingSes ? <Loader /> : sessions.length === 0 ? <EmptyState title={t('কোনো সেশন নেই', 'No scheduled sessions')} /> : (
                sessions.map((s) => (
                  <div key={s.id} className="border rounded p-3 mb-3">
                    <div className="d-flex justify-content-between align-items-start mb-2">
                      <div>
                        <span className="badge bg-secondary me-2">{s.session_code}</span>
                        <strong>{t(s.program?.title_bn, s.program?.title_en)}</strong>
                        <div className="small text-muted">
                          {s.venue} • {s.start_date} → {s.end_date} • {t('প্রশিক্ষক:', 'Trainer:')} {s.trainer_name || '-'}
                        </div>
                      </div>
                      {canManageTrainingSessions(user) && (
                        <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => setEnrollTarget(s)}>
                          {t('অংশগ্রহণকারী যোগ', 'Enroll Volunteer')}
                        </button>
                      )}
                    </div>
                    <div className="table-responsive">
                      <table className="table table-sm mb-0">
                        <thead className="table-light">
                          <tr>
                            <th>{t('স্বেচ্ছাসেবক', 'Volunteer')}</th>
                            <th>{t('উপস্থিতি', 'Status')}</th>
                            <th>{t('ঘণ্টা', 'Hours')}</th>
                            <th>{t('সনদ নং', 'Certificate')}</th>
                            <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                          </tr>
                        </thead>
                        <tbody>
                          {(s.enrollments || []).length === 0 ? (
                            <tr><td colSpan="5" className="text-muted small text-center">{t('কোনো অংশগ্রহণকারী নেই', 'No participants enrolled')}</td></tr>
                          ) : (
                            s.enrollments.map((en) => (
                              <tr key={en.id}>
                                <td>{en.volunteer_code} — {t(en.volunteer_name_bn, en.volunteer_name_en)}</td>
                                <td>{en.enrollment_status}</td>
                                <td>{en.attendance_hours}</td>
                                <td>{en.certificate_no || '-'}</td>
                                <td className="text-end">
                                  {canManageTrainingSessions(user) && !en.passed && (
                                    <button
                                      type="button"
                                      className="btn btn-sm btn-outline-success py-0"
                                      onClick={() => certifyMutation.mutate({ enrollmentId: en.id, hours: s.program?.duration_hours || 8 })}
                                    >
                                      {t('পাস ও সনদ ইস্যু', 'Pass & Issue Cert')}
                                    </button>
                                  )}
                                </td>
                              </tr>
                            ))
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Add Program Modal */}
      {showProgModal && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog">
            <form className="modal-content" onSubmit={(e) => { e.preventDefault(); createProgMutation.mutate({ ...progForm, category_id: progForm.category_id || null, skill_id: progForm.skill_id || null }); }}>
              <div className="modal-header"><h5 className="modal-title">{t('নতুন প্রশিক্ষণ প্রোগ্রাম', 'New Training Program')}</h5><button type="button" className="btn-close" onClick={() => setShowProgModal(false)} /></div>
              <div className="modal-body">
                <div className="mb-2"><label className="form-label small">{t('শিরোনাম (ইংরেজি)', 'Title (English)')} *</label><input className="form-control form-control-sm" required value={progForm.title_en} onChange={(e) => setProgForm({ ...progForm, title_en: e.target.value })} /></div>
                <div className="mb-2"><label className="form-label small">{t('শিরোনাম (বাংলা)', 'Title (Bangla)')} *</label><input className="form-control form-control-sm" required value={progForm.title_bn} onChange={(e) => setProgForm({ ...progForm, title_bn: e.target.value })} /></div>
                <div className="mb-2"><label className="form-label small">{t('আয়োজক সংস্থা', 'Provider Name')} *</label><input className="form-control form-control-sm" required value={progForm.provider_name} onChange={(e) => setProgForm({ ...progForm, provider_name: e.target.value })} /></div>
                <div className="row g-2">
                  <div className="col-6"><label className="form-label small">{t('সময়কাল (ঘণ্টা)', 'Duration (Hours)')}</label><input type="number" min="1" className="form-control form-control-sm" value={progForm.duration_hours} onChange={(e) => setProgForm({ ...progForm, duration_hours: Number(e.target.value) })} /></div>
                  <div className="col-6"><label className="form-label small">{t('মেয়াদ (মাস)', 'Validity (Months)')}</label><input type="number" min="1" className="form-control form-control-sm" value={progForm.validity_months} onChange={(e) => setProgForm({ ...progForm, validity_months: Number(e.target.value) })} /></div>
                </div>
              </div>
              <div className="modal-footer"><button type="button" className="btn btn-secondary btn-sm" onClick={() => setShowProgModal(false)}>{t('বাতিল', 'Cancel')}</button><button type="submit" className="btn btn-primary btn-sm">{t('সংরক্ষণ', 'Save')}</button></div>
            </form>
          </div>
        </div>
      )}

      {/* Add Session Modal */}
      {showSesModal && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog">
            <form className="modal-content" onSubmit={(e) => { e.preventDefault(); createSesMutation.mutate(sesForm); }}>
              <div className="modal-header"><h5 className="modal-title">{t('নতুন প্রশিক্ষণ সেশন', 'Schedule Training Session')}</h5><button type="button" className="btn-close" onClick={() => setShowSesModal(false)} /></div>
              <div className="modal-body">
                <div className="mb-2">
                  <label className="form-label small">{t('প্রোগ্রাম', 'Program')} *</label>
                  <select className="form-select form-select-sm" required value={sesForm.program_id} onChange={(e) => setSesForm({ ...sesForm, program_id: e.target.value })}>
                    <option value="">{t('নির্বাচন করুন', 'Select Program')}</option>
                    {programs.map((p) => <option key={p.id} value={p.id}>{t(p.title_bn, p.title_en)}</option>)}
                  </select>
                </div>
                <div className="mb-2">
                  <label className="form-label small">{t('প্রশাসনিক ইউনিট', 'Administrative Unit')} *</label>
                  <select className="form-select form-select-sm" required value={sesForm.administrative_unit_id} onChange={(e) => setSesForm({ ...sesForm, administrative_unit_id: e.target.value })}>
                    <option value="">{t('নির্বাচন করুন', 'Select Unit')}</option>
                    {(lookups.administrative_units || []).map((u) => <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)}</option>)}
                  </select>
                </div>
                <div className="mb-2"><label className="form-label small">{t('স্থান / ভেন্যু', 'Venue')} *</label><input className="form-control form-control-sm" required value={sesForm.venue} onChange={(e) => setSesForm({ ...sesForm, venue: e.target.value })} /></div>
                <div className="row g-2 mb-2">
                  <div className="col-6"><label className="form-label small">{t('শুরুর তারিখ', 'Start Date')} *</label><input type="date" className="form-control form-control-sm" required value={sesForm.start_date} onChange={(e) => setSesForm({ ...sesForm, start_date: e.target.value })} /></div>
                  <div className="col-6"><label className="form-label small">{t('শেষ তারিখ', 'End Date')} *</label><input type="date" className="form-control form-control-sm" required value={sesForm.end_date} onChange={(e) => setSesForm({ ...sesForm, end_date: e.target.value })} /></div>
                </div>
                <div className="mb-2"><label className="form-label small">{t('প্রশিক্ষক', 'Trainer Name')} *</label><input className="form-control form-control-sm" required value={sesForm.trainer_name} onChange={(e) => setSesForm({ ...sesForm, trainer_name: e.target.value })} /></div>
              </div>
              <div className="modal-footer"><button type="button" className="btn btn-secondary btn-sm" onClick={() => setShowSesModal(false)}>{t('বাতিল', 'Cancel')}</button><button type="submit" className="btn btn-primary btn-sm">{t('সংরক্ষণ', 'Save')}</button></div>
            </form>
          </div>
        </div>
      )}

      {/* Enroll Modal */}
      {enrollTarget && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog">
            <form className="modal-content" onSubmit={(e) => { e.preventDefault(); enrollMutation.mutate({ sessionId: enrollTarget.id, volunteerId: enrollVolunteerId }); }}>
              <div className="modal-header"><h5 className="modal-title">{t('স্বেচ্ছাসেবক অন্তর্ভুক্ত করুন', 'Enroll Volunteer')} ({enrollTarget.session_code})</h5><button type="button" className="btn-close" onClick={() => setEnrollTarget(null)} /></div>
              <div className="modal-body">
                <select className="form-select form-select-sm" required value={enrollVolunteerId} onChange={(e) => setEnrollVolunteerId(e.target.value)}>
                  <option value="">{t('স্বেচ্ছাসেবক নির্বাচন করুন', 'Select Volunteer')}</option>
                  {volunteers.map((v) => <option key={v.id} value={v.id}>{v.volunteer_code} — {t(v.full_name_bn, v.full_name_en)}</option>)}
                </select>
              </div>
              <div className="modal-footer"><button type="button" className="btn btn-secondary btn-sm" onClick={() => setEnrollTarget(null)}>{t('বাতিল', 'Cancel')}</button><button type="submit" className="btn btn-primary btn-sm">{t('নিবন্ধন করুন', 'Enroll')}</button></div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default VolunteerTrainingPage;
