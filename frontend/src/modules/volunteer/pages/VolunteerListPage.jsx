import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { volunteerApi } from '../../../api/volunteerApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import { VolunteerAvailabilityBadge, VolunteerVerificationBadge } from '../components/VolunteerStatusBadges';
import {
  canDeleteVolunteers,
  canExportVolunteerReports,
  canWriteVolunteers,
  formatVolunteerApiError,
  isVolunteerExternalPartner,
  triggerVolunteerCsvDownload,
} from '../utils/volunteerPermissions';

export const VolunteerListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [filters, setFilters] = useState({
    search: '',
    organization_id: '',
    primary_category_id: '',
    overall_verification_status: '',
    availability_status: '',
    page: 1,
  });
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['volunteer', 'lookups'],
    queryFn: () => volunteerApi.getLookups(),
  });

  const cleanFilters = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== null));
  const { data: listRes, isLoading, error } = useQuery({
    queryKey: ['volunteer', 'volunteers', cleanFilters],
    queryFn: () => volunteerApi.getVolunteers(cleanFilters),
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => volunteerApi.deleteVolunteer(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['volunteer'] });
      setDeleteTarget(null);
      setFeedback({ variant: 'success', message: t('স্বেচ্ছাসেবক আর্কাইভ করা হয়েছে।', 'Volunteer archived successfully.') });
    },
    onError: (err) => {
      setDeleteTarget(null);
      setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('আর্কাইভ ব্যর্থ।', 'Archive failed.')) });
    },
  });

  const handleExport = async () => {
    try {
      const blob = await volunteerApi.exportCsv({ report_type: 'register', ...cleanFilters });
      triggerVolunteerCsvDownload(blob, 'idms-volunteer-register.csv');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatVolunteerApiError(err, t('এক্সপোর্ট ব্যর্থ।', 'Export failed.')) });
    }
  };

  const volunteers = listRes?.data || [];
  const meta = listRes?.meta || {};
  const lookups = lookupsRes?.data || {};
  const isExternal = isVolunteerExternalPartner(user);

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h3 className="mb-0">{t('স্বেচ্ছাসেবক রোস্টার (Volunteer Roster)', 'Volunteer Roster')}</h3>
          <small className="text-muted">
            {t('নিবন্ধিত স্বেচ্ছাসেবকদের তালিকা, যাচাই অবস্থা ও প্রাপ্যতা', 'Registered volunteers directory, verification & availability status')}
          </small>
        </div>
        <div className="d-flex gap-2">
          {canWriteVolunteers(user) && (
            <Link to="/volunteer/volunteers/new" className="btn btn-primary btn-sm">
              <i className="bi bi-person-plus-fill me-1"></i>{t('নতুন নিবন্ধন', 'Register Volunteer')}
            </Link>
          )}
          {canExportVolunteerReports(user) && (
            <button type="button" className="btn btn-outline-success btn-sm" onClick={handleExport}>
              <i className="bi bi-download me-1"></i>CSV
            </button>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <div className="card shadow-sm mb-3">
        <div className="card-body py-2">
          <div className="row g-2">
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('নাম বা কোড দিয়ে খুঁজুন...', 'Search name or code...')}
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value, page: 1 })}
              />
            </div>
            <div className="col-md-2">
              <select className="form-select form-select-sm" value={filters.organization_id} onChange={(e) => setFilters({ ...filters, organization_id: e.target.value, page: 1 })}>
                <option value="">{t('সকল সংগঠন', 'All Organizations')}</option>
                {(lookups.organizations || []).map((o) => <option key={o.id} value={o.id}>{o.name_en}</option>)}
              </select>
            </div>
            <div className="col-md-2">
              <select className="form-select form-select-sm" value={filters.primary_category_id} onChange={(e) => setFilters({ ...filters, primary_category_id: e.target.value, page: 1 })}>
                <option value="">{t('সকল ক্যাটাগরি', 'All Categories')}</option>
                {(lookups.categories || []).map((c) => <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>)}
              </select>
            </div>
            <div className="col-md-2">
              <select className="form-select form-select-sm" value={filters.overall_verification_status} onChange={(e) => setFilters({ ...filters, overall_verification_status: e.target.value, page: 1 })}>
                <option value="">{t('যাচাই অবস্থা', 'Verification Status')}</option>
                {(lookups.verification_statuses || []).map((s) => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            <div className="col-md-2">
              <select className="form-select form-select-sm" value={filters.availability_status} onChange={(e) => setFilters({ ...filters, availability_status: e.target.value, page: 1 })}>
                <option value="">{t('প্রাপ্যতা', 'Availability')}</option>
                {(lookups.availability_statuses || []).map((s) => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
            <div className="col-md-1">
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm w-100"
                onClick={() => setFilters({ search: '', organization_id: '', primary_category_id: '', overall_verification_status: '', availability_status: '', page: 1 })}
              >
                {t('রিসেট', 'Reset')}
              </button>
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader />
      ) : error ? (
        <AlertBanner variant="danger" message={formatVolunteerApiError(error, t('তালিকা লোড ব্যর্থ।', 'Failed to load volunteers.'))} />
      ) : volunteers.length === 0 ? (
        <EmptyState title={t('কোনো স্বেচ্ছাসেবক পাওয়া যায়নি', 'No volunteers found')} />
      ) : (
        <div className="card shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('কোড', 'Code')}</th>
                  <th>{t('নাম', 'Full Name')}</th>
                  <th>{t('সংগঠন ও ক্যাটাগরি', 'Organization & Category')}</th>
                  <th>{t('এলাকা', 'Jurisdiction')}</th>
                  {!isExternal && <th>{t('মোবাইল (মাস্কড)', 'Mobile (Masked)')}</th>}
                  <th>{t('যাচাই', 'Verification')}</th>
                  <th>{t('প্রাপ্যতা', 'Availability')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {volunteers.map((v) => (
                  <tr key={v.id}>
                    <td className="fw-semibold">{v.volunteer_code}</td>
                    <td>
                      <div>{t(v.full_name_bn, v.full_name_en)}</div>
                      <small className="text-muted">{v.full_name_en}</small>
                    </td>
                    <td>
                      <div>{v.organization?.name_en || '-'}</div>
                      <small className="text-muted">{v.primary_category ? t(v.primary_category.name_bn, v.primary_category.name_en) : '-'}</small>
                    </td>
                    <td>{v.administrative_unit ? t(v.administrative_unit.name_bn, v.administrative_unit.name_en) : '-'}</td>
                    {!isExternal && <td><code>{v.mobile_masked || '-'}</code></td>}
                    <td><VolunteerVerificationBadge status={v.overall_verification_status} /></td>
                    <td><VolunteerAvailabilityBadge status={v.availability_status} /></td>
                    <td className="text-end">
                      <Link to={`/volunteer/volunteers/${v.id}`} className="btn btn-sm btn-outline-primary me-1">
                        {t('বিস্তারিত', 'View')}
                      </Link>
                      {canWriteVolunteers(user) && (
                        <Link to={`/volunteer/volunteers/${v.id}/edit`} className="btn btn-sm btn-outline-secondary me-1">
                          {t('সম্পাদনা', 'Edit')}
                        </Link>
                      )}
                      {canDeleteVolunteers(user) && (
                        <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => setDeleteTarget(v)}>
                          <i className="bi bi-trash"></i>
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {meta.last_page > 1 && (
            <div className="card-footer d-flex justify-content-between align-items-center bg-white">
              <small className="text-muted">
                {t('মোট:', 'Total:')} {meta.total} | {t('পৃষ্ঠা', 'Page')} {meta.current_page} / {meta.last_page}
              </small>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page <= 1}
                  onClick={() => setFilters({ ...filters, page: meta.current_page - 1 })}
                >
                  {t('পূর্ববর্তী', 'Previous')}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => setFilters({ ...filters, page: meta.current_page + 1 })}
                >
                  {t('পরবর্তী', 'Next')}
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      <ConfirmDialog
        show={!!deleteTarget}
        title={t('স্বেচ্ছাসেবক আর্কাইভ নিশ্চিত করুন', 'Confirm Volunteer Archive')}
        message={t(`আপনি কি ${deleteTarget?.volunteer_code} আর্কাইভ করতে চান?`, `Archive volunteer ${deleteTarget?.volunteer_code}?`)}
        confirmVariant="danger"
        onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
};

export default VolunteerListPage;
