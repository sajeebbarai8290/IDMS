import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { responseApi } from '../../../api/responseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canCreateOperation,
  canManageActivities,
  formatResponseApiError,
  isResponseBlackout,
} from '../utils/responsePermissions';

export const ResponseDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [disasterFilter, setDisasterFilter] = useState('');
  const [showActModal, setShowActModal] = useState(false);
  const [actForm, setActForm] = useState({ code: '', name_en: '', name_bn: '', description: '' });
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['response', 'lookups'],
    queryFn: () => responseApi.getLookups(),
  });

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['response', 'dashboard', disasterFilter],
    queryFn: () => responseApi.getDashboard(disasterFilter ? { disaster_event_id: disasterFilter } : {}),
  });

  const createActMutation = useMutation({
    mutationFn: (payload) => responseApi.createActivity(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['response', 'lookups'] });
      setActForm({ code: '', name_en: '', name_bn: '', description: '' });
      setShowActModal(false);
      setFeedback({ variant: 'success', message: t('সাড়াদান কার্যক্রম যোগ করা হয়েছে।', 'Response activity added successfully.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatResponseApiError(err, t('কার্যক্রম সংরক্ষণ ব্যর্থ।', 'Failed to create activity.')) });
    },
  });

  if (isLoading) return <Loader />;

  const summary = dashRes?.data?.summary || {};
  const coverage = dashRes?.data?.geographical_coverage || [];
  const requirements = dashRes?.data?.emergency_requirements || [];
  const disasters = lookupsRes?.data?.disasters || [];
  const activities = lookupsRes?.data?.activities || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-broadcast-pin text-danger me-2"></i>
            {t('দুর্যোগ সাড়াদান সমন্বয় ড্যাশবোর্ড', 'Disaster Response Command Dashboard')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'উদ্ধার অভিযান, সাড়াদান দল, টাস্ক অগ্রগতি, জরুরি চাহিদা ও পরিস্থিতি প্রতিবেদন',
              'Emergency response operations, search & rescue teams, task tracking, and situation reports'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <select
            className="form-select form-select-sm"
            style={{ width: '230px' }}
            value={disasterFilter}
            onChange={(e) => setDisasterFilter(e.target.value)}
          >
            <option value="">{t('সকল দুর্যোগ ইভেন্ট', 'All Disaster Events')}</option>
            {disasters.map((d) => (
              <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)} ({d.disaster_code})</option>
            ))}
          </select>

          <Link to="/response/map" className="btn btn-sm btn-outline-primary">
            <i className="bi bi-map me-1"></i>
            {t('জিআইএস মানচিত্র', 'GIS Map')}
          </Link>
          <Link to="/response/operations" className="btn btn-sm btn-outline-secondary">
            {t('অভিযান তালিকা', 'Operations')}
          </Link>
          {canCreateOperation(user) && (
            <Link to="/response/operations/new" className="btn btn-sm btn-danger">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন অভিযান', 'New Operation')}
            </Link>
          )}
        </div>
      </div>

      {isResponseBlackout(user) && (
        <AlertBanner
          variant="info"
          message={t(
            'সুপার অ্যাডমিন অপারেশনাল ব্ল্যাকআউট সক্রিয়: আপনি ড্যাশবোর্ড, মানচিত্র দেখতে ও কার্যক্রম কনফিগার করতে পারবেন, তবে অপারেশন/টাস্ক/রিপোর্ট সম্পাদন করতে পারবেন না।',
            'Super Admin Operational Blackout active: You may view operations, maps, and configure activity types, but cannot create or mutate operational response records.'
          )}
        />
      )}

      {error && <AlertBanner variant="danger" message={formatResponseApiError(error, t('ড্যাশবোর্ড লোড ব্যর্থ', 'Failed to load dashboard'))} />}
      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {/* KPI Cards */}
      <div className="row g-3 mb-4">
        {[
          { bn: 'সক্রিয় অভিযান', en: 'Active Operations', val: summary.active_operations ?? 0, sub: `${summary.total_operations ?? 0} total`, icon: 'bi-activity', color: 'danger', to: '/response/operations' },
          { bn: 'অপেক্ষমাণ / চলমান কাজ', en: 'Pending Tasks', val: summary.pending_tasks ?? 0, icon: 'bi-list-task', color: 'warning', to: '/response/operations' },
          { bn: 'সম্পন্ন কাজ', en: 'Completed Tasks', val: summary.completed_tasks ?? 0, icon: 'bi-check2-circle', color: 'success', to: '/response/operations' },
          { bn: 'সক্রিয় সাড়াদান দল', en: 'Response Teams', val: `${summary.response_teams_active ?? 0}/${summary.response_teams_total ?? 0}`, icon: 'bi-people-fill', color: 'primary', to: '/response/operations' },
          { bn: 'উদ্ধার ও সরিয়ে নেওয়া', en: 'Rescued / Evacuated', val: `${summary.total_rescued ?? 0} / ${summary.total_evacuated ?? 0}`, icon: 'bi-life-preserver', color: 'info', to: '/response/map' },
          { bn: 'জরুরি চাহিদা বাকি', en: 'Open Requirements', val: summary.open_requirements ?? 0, icon: 'bi-truck', color: 'dark', to: '/response/operations' },
        ].map((k) => (
          <div className="col-6 col-md-4 col-xl-2" key={k.en}>
            <Link to={k.to} className="text-decoration-none">
              <div className="card border-0 shadow-sm h-100">
                <div className="card-body">
                  <span className={`badge bg-${k.color} bg-opacity-10 text-${k.color} p-2 mb-2 d-inline-block`}>
                    <i className={`bi ${k.icon} fs-5`}></i>
                  </span>
                  <div className="fs-4 fw-bold text-dark">{k.val}</div>
                  <div className="small text-muted">{t(k.bn, k.en)}</div>
                </div>
              </div>
            </Link>
          </div>
        ))}
      </div>

      <div className="row g-3 mb-4">
        {/* Geographic Coverage */}
        <div className="col-lg-6">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white border-bottom fw-bold">
              <i className="bi bi-geo-alt text-primary me-2"></i>
              {t('ভৌগোলিক সাড়াদান কভারেজ', 'Geographical Response Coverage')}
            </div>
            <div className="card-body">
              {coverage.length === 0 ? (
                <p className="text-muted small mb-0">{t('কোন অভিযান রেকর্ড পাওয়া যায়নি।', 'No response operations recorded yet.')}</p>
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>{t('এলাকা', 'Jurisdiction')}</th>
                        <th>{t('স্তর', 'Tier')}</th>
                        <th className="text-end">{t('মোট অভিযান', 'Operations')}</th>
                        <th className="text-end">{t('সক্রিয়', 'Active')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {coverage.map((c) => (
                        <tr key={c.administrative_unit_id}>
                          <td className="fw-medium">{t(c.unit_name_bn, c.unit_name_en)}</td>
                          <td><span className="badge bg-light text-dark border">{c.tier_level}</span></td>
                          <td className="text-end">{c.operations_count}</td>
                          <td className="text-end fw-bold text-danger">{c.active_count}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Emergency Requirements */}
        <div className="col-lg-6">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white border-bottom fw-bold">
              <i className="bi bi-exclamation-triangle text-warning me-2"></i>
              {t('জরুরি সম্পদ ও যানবাহনের চাহিদা', 'Emergency Resource Requirements')}
            </div>
            <div className="card-body">
              {requirements.length === 0 ? (
                <p className="text-muted small mb-0">{t('কোন জরুরি চাহিদা নথিভুক্ত নেই।', 'No resource requirements logged yet.')}</p>
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>{t('চাহিদা', 'Requirement')}</th>
                        <th>{t('অভিযান', 'Operation')}</th>
                        <th className="text-end">{t('প্রয়োজন', 'Needed')}</th>
                        <th>{t('অবস্থা', 'Status')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {requirements.map((r) => (
                        <tr key={r.id}>
                          <td className="fw-medium">{r.requirement_title}</td>
                          <td><small className="text-muted">{r.operation_no}</small></td>
                          <td className="text-end">{r.quantity_needed} {r.unit}</td>
                          <td><span className="badge bg-secondary">{r.status}</span></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Configurable Response Activities */}
      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
          <h6 className="fw-bold mb-0">
            <i className="bi bi-grid me-2 text-secondary"></i>
            {t('সাড়াদান কার্যক্রম শ্রেণিবিভাগ', 'Configured Response Activity Types')}
          </h6>
          {canManageActivities(user) && (
            <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setShowActModal(true)}>
              + {t('নতুন কার্যক্রম যোগ', 'Add Activity')}
            </button>
          )}
        </div>
        <div className="card-body d-flex flex-wrap gap-2">
          {activities.map((a) => (
            <div key={a.id} className="border rounded px-3 py-2 bg-light small">
              <div className="fw-bold">{t(a.name_bn, a.name_en)}</div>
              <div className="text-muted" style={{ fontSize: '0.75rem' }}>{a.code}</div>
            </div>
          ))}
        </div>
      </div>

      {showActModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.45)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <form
              className="modal-content"
              onSubmit={(e) => {
                e.preventDefault();
                createActMutation.mutate(actForm);
              }}
            >
              <div className="modal-header">
                <h5 className="modal-title fw-bold">{t('নতুন সাড়াদান কার্যক্রম', 'Add Response Activity Type')}</h5>
                <button type="button" className="btn-close" onClick={() => setShowActModal(false)}></button>
              </div>
              <div className="modal-body">
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('কোড', 'Code')}</label>
                  <input
                    className="form-control"
                    required
                    placeholder="e.g. LIVESTOCK_RESCUE"
                    value={actForm.code}
                    onChange={(e) => setActForm((p) => ({ ...p, code: e.target.value.toUpperCase() }))}
                  />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('ইংরেজি নাম', 'English Name')}</label>
                  <input className="form-control" required value={actForm.name_en} onChange={(e) => setActForm((p) => ({ ...p, name_en: e.target.value }))} />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('বাংলা নাম', 'Bangla Name')}</label>
                  <input className="form-control" required value={actForm.name_bn} onChange={(e) => setActForm((p) => ({ ...p, name_bn: e.target.value }))} />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-semibold">{t('বিবরণ', 'Description')}</label>
                  <textarea className="form-control" rows={2} value={actForm.description} onChange={(e) => setActForm((p) => ({ ...p, description: e.target.value }))} />
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setShowActModal(false)}>{t('বাতিল', 'Cancel')}</button>
                <button type="submit" className="btn btn-primary" disabled={createActMutation.isPending}>{t('সংরক্ষণ করুন', 'Save')}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default ResponseDashboardPage;
