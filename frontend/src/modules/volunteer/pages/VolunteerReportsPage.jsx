import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { volunteerApi } from '../../../api/volunteerApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canExportVolunteerReports,
  formatVolunteerApiError,
  triggerVolunteerCsvDownload,
} from '../utils/volunteerPermissions';

export const VolunteerReportsPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const [activeTab, setActiveTab] = useState('register');
  const [feedback, setFeedback] = useState(null);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'reports'],
    queryFn: () => volunteerApi.getReports(),
  });

  const handleExport = async (reportType) => {
    try {
      const blob = await volunteerApi.exportCsv({ report_type: reportType });
      triggerVolunteerCsvDownload(blob, `idms-volunteer-${reportType}.csv`);
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('এক্সপোর্ট ব্যর্থ।', 'Export failed.')) });
    }
  };

  if (isLoading) return <Loader />;
  if (error) return <AlertBanner variant="danger" message={formatVolunteerApiError(error, t('প্রতিবেদন লোড ব্যর্থ।', 'Failed to load reports.'))} />;

  const data = res?.data || {};
  const register = data.volunteer_register || [];
  const deployments = data.deployment_history || [];
  const attendance = data.attendance_history || [];
  const training = data.training_certifications || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3 className="mb-0">{t('স্বেচ্ছাসেবক প্রতিবেদনসমূহ (Reports)', 'Volunteer Management Reports')}</h3>
          <small className="text-muted">{t('স্বেচ্ছাসেবক নিবন্ধন, মোতায়েন ইতিহাস, উপস্থিতি ও প্রশিক্ষণ সনদ প্রতিবেদন', 'Register, deployment history, attendance & training certification reports')}</small>
        </div>
        {canExportVolunteerReports(user) && (
          <button type="button" className="btn btn-success btn-sm" onClick={() => handleExport(activeTab)}>
            <i className="bi bi-file-earmark-spreadsheet me-1"></i>{t('বর্তমান ট্যাব CSV ডাউনলোড', 'Export Current CSV')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <ul className="nav nav-tabs mb-3">
        <li className="nav-item"><button type="button" className={`nav-link ${activeTab === 'register' ? 'active' : ''}`} onClick={() => setActiveTab('register')}>{t('স্বেচ্ছাসেবক রেজিস্টার', 'Volunteer Register')} ({register.length})</button></li>
        <li className="nav-item"><button type="button" className={`nav-link ${activeTab === 'deployments' ? 'active' : ''}`} onClick={() => setActiveTab('deployments')}>{t('মোতায়েন ইতিহাস', 'Deployment History')} ({deployments.length})</button></li>
        <li className="nav-item"><button type="button" className={`nav-link ${activeTab === 'attendance' ? 'active' : ''}`} onClick={() => setActiveTab('attendance')}>{t('উপস্থিতি', 'Attendance')} ({attendance.length})</button></li>
        <li className="nav-item"><button type="button" className={`nav-link ${activeTab === 'training' ? 'active' : ''}`} onClick={() => setActiveTab('training')}>{t('প্রশিক্ষণ ও সনদ', 'Training & Certifications')} ({training.length})</button></li>
      </ul>

      <div className="card shadow-sm">
        <div className="table-responsive">
          {activeTab === 'register' && (
            <table className="table table-sm table-hover mb-0">
              <thead className="table-light">
                <tr><th>Code</th><th>Name</th><th>Organization</th><th>Category</th><th>Unit</th><th>Verification</th><th>Availability</th></tr>
              </thead>
              <tbody>
                {register.map((r) => (
                  <tr key={r.id}><td>{r.volunteer_code}</td><td>{t(r.full_name_bn, r.full_name_en)}</td><td>{r.organization || '-'}</td><td>{r.category || '-'}</td><td>{t(r.unit_bn, r.unit_en)}</td><td>{r.overall_verification_status}</td><td>{r.availability_status}</td></tr>
                ))}
              </tbody>
            </table>
          )}
          {activeTab === 'deployments' && (
            <table className="table table-sm table-hover mb-0">
              <thead className="table-light">
                <tr><th>Deployment No</th><th>Volunteer</th><th>Disaster / Op</th><th>Role</th><th>Location</th><th>Dates</th><th>Status</th></tr>
              </thead>
              <tbody>
                {deployments.map((d) => (
                  <tr key={d.id}><td>{d.deployment_no}</td><td>{d.volunteer_code} — {d.volunteer_name}</td><td>{d.disaster_code || d.operation_no || '-'}</td><td>{d.assigned_role}</td><td>{d.deployment_location}</td><td>{d.start_date} → {d.end_date || '-'}</td><td>{d.deployment_status}</td></tr>
                ))}
              </tbody>
            </table>
          )}
          {activeTab === 'attendance' && (
            <table className="table table-sm table-hover mb-0">
              <thead className="table-light">
                <tr><th>Date</th><th>Deployment</th><th>Volunteer</th><th>Duration (min)</th><th>Assisted</th><th>Confirmation</th></tr>
              </thead>
              <tbody>
                {attendance.map((a) => (
                  <tr key={a.id}><td>{a.attendance_date}</td><td>{a.deployment_no}</td><td>{a.volunteer_code} — {a.volunteer_name}</td><td>{a.duration_minutes ?? '-'}</td><td>{a.beneficiaries_assisted}</td><td>{a.confirmation_status}</td></tr>
                ))}
              </tbody>
            </table>
          )}
          {activeTab === 'training' && (
            <table className="table table-sm table-hover mb-0">
              <thead className="table-light">
                <tr><th>Session</th><th>Program</th><th>Volunteer</th><th>Status</th><th>Hours</th><th>Certificate</th></tr>
              </thead>
              <tbody>
                {training.map((tr) => (
                  <tr key={tr.id}><td>{tr.session_code}</td><td>{t(tr.program_title_bn, tr.program_title_en)}</td><td>{tr.volunteer_code} — {tr.volunteer_name}</td><td>{tr.enrollment_status}</td><td>{tr.attendance_hours}</td><td>{tr.certificate_no || '-'}</td></tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
};

export default VolunteerReportsPage;
