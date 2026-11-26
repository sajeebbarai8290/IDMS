import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { damageApi } from '../../../api/damageApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import EmptyState from '../../../components/common/EmptyState';
import AlertBanner from '../../../components/common/AlertBanner';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  canCreateDamage,
  canDeleteDamage,
  canExportDamage,
  canUpdateDamage,
  DAMAGE_SEVERITY_META,
  DAMAGE_STATUS_META,
  DAMAGE_TYPE_META,
  formatBdt,
  formatDamageApiError,
  triggerBlobDownload,
} from '../utils/damagePermissions';
import { DamageSeverityBadge, DamageStatusBadge, DamageTypeLabel } from '../components/DamageStatusBadges';

export const DamageAssessmentListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [filters, setFilters] = useState({
    search: '',
    disaster_event_id: '',
    verification_status: '',
    assessment_type: '',
    severity_level: '',
    category_id: '',
    page: 1,
  });
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['damage', 'lookups'],
    queryFn: () => damageApi.getLookups(),
  });

  const { data: listRes, isLoading, error } = useQuery({
    queryKey: ['damage', 'assessments', filters],
    queryFn: () =>
      damageApi.getAssessments(
        Object.fromEntries(Object.entries(filters).filter(([, val]) => val !== '' && val !== null))
      ),
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => damageApi.deleteAssessment(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['damage'] });
      setDeleteTarget(null);
      setFeedback({ variant: 'success', message: t('খসড়া ডি-ফর্ম মুছে ফেলা হয়েছে।', 'Draft assessment deleted.') });
    },
    onError: (err) => {
      setDeleteTarget(null);
      setFeedback({ variant: 'danger', message: formatDamageApiError(err, t('মুছে ফেলা ব্যর্থ।', 'Delete failed.')) });
    },
  });

  const handleExport = async () => {
    try {
      const res = await damageApi.exportCsv(
        Object.fromEntries(Object.entries(filters).filter(([k, val]) => k !== 'page' && val !== ''))
      );
      triggerBlobDownload(res.data, 'idms-damage-assessments.csv');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatDamageApiError(err, t('এক্সপোর্ট ব্যর্থ।', 'Export failed.')) });
    }
  };

  const assessments = listRes?.data || [];
  const meta = listRes?.meta || {};
  const disasters = lookupsRes?.data?.disasters || [];
  const categories = lookupsRes?.data?.categories || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-list-ul text-danger me-2"></i>
            {t('ক্ষয়ক্ষতি নিরূপণ তালিকা (D-Forms)', 'Damage Assessments Registry (D-Forms)')}
          </h4>
          <p className="text-muted small mb-0">
            {t('মাঠ পর্যায়ের ডি-ফর্ম অনুসন্ধান, ফিল্টার ও যাচাইকরণ ব্যবস্থাপনা', 'Search, filter, and manage field D-Forms')}
          </p>
        </div>

        <div className="d-flex gap-2">
          {canExportDamage(user) && (
            <button type="button" className="btn btn-sm btn-outline-success" onClick={handleExport}>
              <i className="bi bi-download me-1"></i>
              {t('সিএসভি এক্সপোর্ট', 'Export CSV')}
            </button>
          )}
          {canCreateDamage(user) && (
            <Link to="/damage/assessments/new" className="btn btn-sm btn-danger">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন ডি-ফর্ম নিবন্ধন', 'Register D-Form')}
            </Link>
          )}
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}
      {error && (
        <AlertBanner
          variant="danger"
          message={formatDamageApiError(error, t('তালিকা লোড করতে ব্যর্থ।', 'Failed to load assessments.'))}
        />
      )}

      {/* Filter Bar */}
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body py-2">
          <div className="row g-2">
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('কোড, সংস্থা, দল বা স্থান খুঁজুন...', 'Search code, org, team, location...')}
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value, page: 1 })}
              />
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.disaster_event_id}
                onChange={(e) => setFilters({ ...filters, disaster_event_id: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল দুর্যোগ', 'All Disasters')}</option>
                {disasters.map((d) => (
                  <option key={d.id} value={d.id}>{t(d.name_bn, d.name_en)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.verification_status}
                onChange={(e) => setFilters({ ...filters, verification_status: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
                {Object.entries(DAMAGE_STATUS_META).map(([k, v]) => (
                  <option key={k} value={k}>{t(v.bn, v.en)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.assessment_type}
                onChange={(e) => setFilters({ ...filters, assessment_type: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল ধরন', 'All Types')}</option>
                {Object.entries(DAMAGE_TYPE_META).map(([k, v]) => (
                  <option key={k} value={k}>{t(v.bn, v.en)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.severity_level}
                onChange={(e) => setFilters({ ...filters, severity_level: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল তীব্রতা', 'All Severities')}</option>
                {Object.entries(DAMAGE_SEVERITY_META).map(([k, v]) => (
                  <option key={k} value={k}>{t(v.bn, v.en)}</option>
                ))}
              </select>
            </div>
            <div className="col-md-1">
              <select
                className="form-select form-select-sm"
                value={filters.category_id}
                onChange={(e) => setFilters({ ...filters, category_id: e.target.value, page: 1 })}
              >
                <option value="">{t('খাত', 'Cat')}</option>
                {categories.map((c) => (
                  <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader />
      ) : assessments.length === 0 ? (
        <EmptyState
          title={t('কোনো ক্ষয়ক্ষতি নিরূপণ পাওয়া যায়নি', 'No Damage Assessments Found')}
          description={t('নতুন ডি-ফর্ম নিবন্ধন করুন অথবা ফিল্টার পরিবর্তন করুন।', 'Register a new D-Form or adjust filters.')}
        />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('ডি-ফর্ম কোড', 'D-Form Code')}</th>
                  <th>{t('দুর্যোগ ও এলাকা', 'Disaster & Area')}</th>
                  <th>{t('ধরন ও তারিখ', 'Type & Date')}</th>
                  <th>{t('ক্ষয়ক্ষতি (পরিবার/ইউনিট)', 'Impact & Units')}</th>
                  <th className="text-end">{t('আনুমানিক ক্ষতি', 'Est. Loss')}</th>
                  <th className="text-center">{t('তীব্রতা', 'Severity')}</th>
                  <th className="text-center">{t('অবস্থা', 'Status')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {assessments.map((row) => (
                  <tr key={row.id}>
                    <td>
                      <Link to={`/damage/assessments/${row.id}`} className="fw-bold text-decoration-none">
                        {row.d_form_code}
                      </Link>
                      {row.sync_status === 'SYNCED' && (
                        <span className="badge bg-light text-info border ms-1">SYNC</span>
                      )}
                      <div className="small text-muted">{row.assessing_organization}</div>
                    </td>
                    <td>
                      <div className="fw-medium">
                        {t(row.disaster_event?.name_bn, row.disaster_event?.name_en)}
                      </div>
                      <div className="small text-muted">
                        {t(row.administrative_unit?.name_bn, row.administrative_unit?.name_en)}
                        {row.ward_no ? ` (${row.ward_no})` : ''}
                      </div>
                    </td>
                    <td>
                      <div className="small fw-medium"><DamageTypeLabel type={row.assessment_type} /></div>
                      <div className="small text-muted">{row.assessment_date}</div>
                    </td>
                    <td>
                      <div className="small">
                        {t('পরিবার', 'HH')}: <strong>{Number(row.affected_households || 0).toLocaleString()}</strong>
                      </div>
                      <div className="small text-muted">
                        F:{row.total_fully_damaged} / P:{row.total_partially_damaged} / M:{row.total_minor_damaged}
                      </div>
                    </td>
                    <td className="text-end fw-semibold text-danger">
                      {formatBdt(row.total_estimated_loss_bdt)}
                    </td>
                    <td className="text-center">
                      <DamageSeverityBadge severity={row.severity_level} />
                    </td>
                    <td className="text-center">
                      <DamageStatusBadge status={row.verification_status} />
                    </td>
                    <td className="text-end">
                      <div className="btn-group btn-group-sm">
                        <Link to={`/damage/assessments/${row.id}`} className="btn btn-outline-primary">
                          <i className="bi bi-eye"></i>
                        </Link>
                        {row.is_editable && canUpdateDamage(user) && (
                          <Link to={`/damage/assessments/${row.id}/edit`} className="btn btn-outline-secondary">
                            <i className="bi bi-pencil"></i>
                          </Link>
                        )}
                        {row.verification_status === 'DRAFT' && canDeleteDamage(user) && (
                          <button
                            type="button"
                            className="btn btn-outline-danger"
                            onClick={() => setDeleteTarget(row)}
                          >
                            <i className="bi bi-trash"></i>
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {meta.last_page > 1 && (
            <div className="card-footer bg-white d-flex justify-content-between align-items-center">
              <small className="text-muted">
                {t('মোট রেকর্ড', 'Total')}: {meta.total} | {t('পৃষ্ঠা', 'Page')} {meta.current_page} / {meta.last_page}
              </small>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page <= 1}
                  onClick={() => setFilters({ ...filters, page: filters.page - 1 })}
                >
                  {t('পূর্ববর্তী', 'Previous')}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => setFilters({ ...filters, page: filters.page + 1 })}
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
        title={t('খসড়া ডি-ফর্ম মুছুন', 'Delete Draft Assessment')}
        message={t(
          `আপনি কি নিশ্চিত যে ${deleteTarget?.d_form_code} মুছে ফেলতে চান?`,
          `Are you sure you want to delete draft ${deleteTarget?.d_form_code}?`
        )}
        confirmLabel={t('মুছে ফেলুন', 'Delete')}
        confirmVariant="danger"
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
};

export default DamageAssessmentListPage;
