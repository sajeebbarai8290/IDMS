import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { volunteerApi } from '../../../api/volunteerApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { VolunteerVerificationBadge } from '../components/VolunteerStatusBadges';
import {
  canManageVolunteerOrganizations,
  canVerifyVolunteerOrganization,
  formatVolunteerApiError,
  isVolunteerExternalPartner,
} from '../utils/volunteerPermissions';

export const VolunteerOrganizationPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [showModal, setShowModal] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const [form, setForm] = useState({
    org_code: '',
    name_en: '',
    name_bn: '',
    org_type: 'CPP',
    registration_no: '',
    administrative_unit_id: '',
    coverage_unit_ids: [],
    address: '',
    contact_person_name: '',
    contact_person_designation: '',
    contact_person_phone: '',
    contact_person_email: '',
    description: '',
  });

  const { data: lookupsRes } = useQuery({
    queryKey: ['volunteer', 'lookups'],
    queryFn: () => volunteerApi.getLookups(),
  });

  const { data: orgsRes, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'organizations'],
    queryFn: () => volunteerApi.getOrganizations(),
  });

  const createMutation = useMutation({
    mutationFn: (payload) => volunteerApi.createOrganization(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setShowModal(false);
      setFeedback({ variant: 'success', message: t('স্বেচ্ছাসেবক সংগঠন নিবন্ধিত হয়েছে।', 'Volunteer organization registered.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('সংরক্ষণ ব্যর্থ।', 'Save failed.')) }),
  });

  const verifyMutation = useMutation({
    mutationFn: ({ id, status }) => volunteerApi.verifyOrganization(id, { verification_status: status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setFeedback({ variant: 'success', message: t('সংগঠন যাচাই অবস্থা হালনাগাদ হয়েছে।', 'Organization verification updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('যাচাই ব্যর্থ।', 'Verification failed.')) }),
  });

  const orgs = orgsRes?.data || [];
  const lookups = lookupsRes?.data || {};
  const isExternal = isVolunteerExternalPartner(user);

  const toggleCoverageUnit = (unitId) => {
    const exists = form.coverage_unit_ids.includes(unitId);
    setForm({
      ...form,
      coverage_unit_ids: exists
        ? form.coverage_unit_ids.filter((u) => u !== unitId)
        : [...form.coverage_unit_ids, unitId],
    });
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h3 className="mb-0">{t('স্বেচ্ছাসেবক সংগঠনসমূহ (Volunteer Organizations)', 'Volunteer Organizations')}</h3>
          <small className="text-muted">{t('সংগঠন প্রোফাইল, যোগাযোগ কর্মকর্তা ও ভৌগোলিক কভারেজ', 'Organization profiles, contact persons & geographic coverage')}</small>
        </div>
        {canManageVolunteerOrganizations(user) && (
          <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowModal(true)}>
            <i className="bi bi-building-add me-1"></i>{t('নতুন সংগঠন নিবন্ধন', 'Register Organization')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {isLoading ? (
        <Loader />
      ) : error ? (
        <AlertBanner variant="danger" message={formatVolunteerApiError(error, t('তথ্য লোড ব্যর্থ।', 'Failed to load organizations.'))} />
      ) : orgs.length === 0 ? (
        <EmptyState title={t('কোনো সংগঠন পাওয়া যায়নি', 'No volunteer organizations found')} />
      ) : (
        <div className="row g-3">
          {orgs.map((org) => (
            <div key={org.id} className="col-lg-6">
              <div className="card shadow-sm h-100">
                <div className="card-body">
                  <div className="d-flex justify-content-between align-items-start mb-2">
                    <div>
                      <span className="badge bg-info text-dark me-2">{org.org_type}</span>
                      <span className="fw-semibold">{org.org_code}</span>
                      <h5 className="mt-1 mb-0">{t(org.name_bn || org.name_en, org.name_en)}</h5>
                    </div>
                    <VolunteerVerificationBadge status={org.verification_status} />
                  </div>
                  <p className="small text-muted mb-2">{org.description || '-'}</p>
                  <div className="small mb-2">
                    <strong>{t('প্রধান ইউনিট:', 'Primary Unit:')}</strong> {org.administrative_unit ? t(org.administrative_unit.name_bn, org.administrative_unit.name_en) : '-'}
                    {' | '}
                    <strong>{t('নিবন্ধিত স্বেচ্ছাসেবক:', 'Registered Volunteers:')}</strong> {org.volunteers_count ?? 0}
                  </div>
                  {!isExternal && (
                    <div className="small mb-2">
                      <strong>{t('যোগাযোগ:', 'Contact Person:')}</strong> {org.contact_person_name} ({org.contact_person_designation || '-'}) — {org.contact_person_phone}
                    </div>
                  )}
                  <div className="small">
                    <strong>{t('ভৌগোলিক কভারেজ:', 'Coverage:')}</strong>{' '}
                    {(org.coverages || []).map((c) => t(c.name_bn || c.name_en, c.name_en)).join(', ') || '-'}
                  </div>
                </div>
                {canVerifyVolunteerOrganization(user) && org.verification_status !== 'VERIFIED' && (
                  <div className="card-footer bg-white d-flex justify-content-end gap-2">
                    <button type="button" className="btn btn-success btn-sm" onClick={() => verifyMutation.mutate({ id: org.id, status: 'VERIFIED' })}>
                      {t('যাচাই অনুমোদন', 'Verify Organization')}
                    </button>
                    <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => verifyMutation.mutate({ id: org.id, status: 'REJECTED' })}>
                      {t('প্রত্যাখ্যান', 'Reject')}
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {showModal && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog modal-lg">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                createMutation.mutate(form);
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title">{t('নতুন স্বেচ্ছাসেবক সংগঠন নিবন্ধন', 'Register Volunteer Organization')}</h5>
                <button type="button" className="btn-close" onClick={() => setShowModal(false)} />
              </div>
              <div className="modal-body">
                <div className="row g-2">
                  <div className="col-md-4">
                    <label className="form-label small">{t('সংগঠনের ধরন', 'Org Type')} *</label>
                    <select className="form-select form-select-sm" value={form.org_type} onChange={(e) => setForm({ ...form, org_type: e.target.value })}>
                      {(lookups.org_types || ['CPP', 'BDRCS', 'SCOUT', 'NGO', 'COMMUNITY']).map((ot) => <option key={ot} value={ot}>{ot}</option>)}
                    </select>
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small">{t('সংগঠন কোড', 'Org Code')}</label>
                    <input className="form-control form-control-sm" value={form.org_code} onChange={(e) => setForm({ ...form, org_code: e.target.value })} placeholder="Auto if empty" />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small">{t('নিবন্ধন নম্বর', 'Registration No')}</label>
                    <input className="form-control form-control-sm" value={form.registration_no} onChange={(e) => setForm({ ...form, registration_no: e.target.value })} />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('নাম (ইংরেজি)', 'Name (English)')} *</label>
                    <input className="form-control form-control-sm" required value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('নাম (বাংলা)', 'Name (Bangla)')}</label>
                    <input className="form-control form-control-sm" value={form.name_bn} onChange={(e) => setForm({ ...form, name_bn: e.target.value })} />
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('প্রধান প্রশাসনিক ইউনিট', 'Primary Unit')} *</label>
                    <select className="form-select form-select-sm" required value={form.administrative_unit_id} onChange={(e) => setForm({ ...form, administrative_unit_id: e.target.value })}>
                      <option value="">{t('নির্বাচন করুন', 'Select Unit')}</option>
                      {(lookups.administrative_units || []).map((u) => <option key={u.id} value={u.id}>{t(u.name_bn, u.name_en)} ({u.tier_level})</option>)}
                    </select>
                  </div>
                  <div className="col-md-6">
                    <label className="form-label small">{t('ঠিকানা', 'Address')}</label>
                    <input className="form-control form-control-sm" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small">{t('যোগাযোগকারীর নাম', 'Contact Person')} *</label>
                    <input className="form-control form-control-sm" required value={form.contact_person_name} onChange={(e) => setForm({ ...form, contact_person_name: e.target.value })} />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small">{t('পদবি', 'Designation')}</label>
                    <input className="form-control form-control-sm" value={form.contact_person_designation} onChange={(e) => setForm({ ...form, contact_person_designation: e.target.value })} />
                  </div>
                  <div className="col-md-4">
                    <label className="form-label small">{t('ফোন', 'Phone')} *</label>
                    <input className="form-control form-control-sm" required value={form.contact_person_phone} onChange={(e) => setForm({ ...form, contact_person_phone: e.target.value })} />
                  </div>
                  <div className="col-12">
                    <label className="form-label small">{t('ভৌগোলিক কভারেজ ইউনিটসমূহ', 'Geographic Coverage Units')}</label>
                    <div className="d-flex flex-wrap gap-1">
                      {(lookups.administrative_units || []).map((u) => {
                        const sel = form.coverage_unit_ids.includes(u.id);
                        return (
                          <button key={u.id} type="button" className={`btn btn-sm ${sel ? 'btn-primary' : 'btn-outline-secondary'}`} onClick={() => toggleCoverageUnit(u.id)}>
                            {t(u.name_bn, u.name_en)}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setShowModal(false)}>{t('বাতিল', 'Cancel')}</button>
                <button type="submit" className="btn btn-primary btn-sm" disabled={createMutation.isPending}>{t('সংরক্ষণ', 'Save')}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default VolunteerOrganizationPage;
