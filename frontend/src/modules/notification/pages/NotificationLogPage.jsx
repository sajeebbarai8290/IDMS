import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationApi } from '../../../api/notificationApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import {
  CategoryLabel,
  ChannelBadge,
  DeliveryStatusBadge,
  SeverityBadge,
} from '../components/NotificationBadges';
import {
  canSendNotificationBroadcast,
  formatNotificationApiError,
} from '../utils/notificationPermissions';

export const NotificationLogPage = () => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const [activeTab, setActiveTab] = useState('campaigns');
  const [campaignFilters, setCampaignFilters] = useState({ search: '', category: '', severity: '', status: '', page: 1 });
  const [dispatchFilters, setDispatchFilters] = useState({ channel: '', delivery_status: '', search: '', page: 1 });
  const [feedback, setFeedback] = useState(null);

  const canRetry = canSendNotificationBroadcast(user);

  const { data: campRes, isLoading: campLoading, error: campError } = useQuery({
    queryKey: ['notifications', 'campaigns', campaignFilters],
    queryFn: () => notificationApi.getCampaigns({
      search: campaignFilters.search || undefined,
      category: campaignFilters.category || undefined,
      severity: campaignFilters.severity || undefined,
      status: campaignFilters.status || undefined,
      page: campaignFilters.page,
      per_page: 15,
    }),
    enabled: activeTab === 'campaigns',
  });

  const { data: dispRes, isLoading: dispLoading, error: dispError } = useQuery({
    queryKey: ['notifications', 'dispatches', dispatchFilters],
    queryFn: () => notificationApi.getDispatches({
      channel: dispatchFilters.channel || undefined,
      delivery_status: dispatchFilters.delivery_status || undefined,
      search: dispatchFilters.search || undefined,
      page: dispatchFilters.page,
      per_page: 20,
    }),
    enabled: activeTab === 'dispatches',
  });

  const retryMutation = useMutation({
    mutationFn: (id) => notificationApi.retryDispatch(id),
    onSuccess: () => {
      setFeedback({ variant: 'success', message: t('পুনরায় প্রেরণের জন্য সারিবদ্ধ করা হয়েছে।', 'Dispatch queued for retry.') });
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const campaigns = campRes?.data || [];
  const campMeta = campRes?.meta || {};
  const dispatches = dispRes?.data || [];
  const dispMeta = dispRes?.meta || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-journal-text text-primary me-2"></i>
            {t('বিজ্ঞপ্তি ও ডেলিভারি লগ', 'Notification Campaigns & Delivery Logs')}
          </h4>
          <p className="text-muted small mb-0">
            {t('প্রাপকের গোপনীয়তা রক্ষিত মাস্কড ডেলিভারি রেকর্ড ও পুনঃচেষ্টা নিয়ন্ত্রণ', 'Privacy-masked delivery logs across In-App, Email, SMS, and Push channels')}
          </p>
        </div>

        <ul className="nav nav-pills">
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link ${activeTab === 'campaigns' ? 'active' : ''}`}
              onClick={() => setActiveTab('campaigns')}
            >
              {t('বিজ্ঞপ্তি প্রচারণা', 'Notification Campaigns')}
            </button>
          </li>
          <li className="nav-item">
            <button
              type="button"
              className={`nav-link ${activeTab === 'dispatches' ? 'active' : ''}`}
              onClick={() => setActiveTab('dispatches')}
            >
              {t('ডেলিভারি লগ', 'Delivery Logs')}
            </button>
          </li>
        </ul>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {activeTab === 'campaigns' ? (
        <>
          <div className="card shadow-sm border-0 mb-3">
            <div className="card-body">
              <div className="row g-2">
                <div className="col-md-4">
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder={t('নম্বর বা শিরোনাম খুঁজুন...', 'Search notification no or title...')}
                    value={campaignFilters.search}
                    onChange={(e) => setCampaignFilters((p) => ({ ...p, search: e.target.value, page: 1 }))}
                  />
                </div>
                <div className="col-md-3">
                  <select
                    className="form-select form-select-sm"
                    value={campaignFilters.severity}
                    onChange={(e) => setCampaignFilters((p) => ({ ...p, severity: e.target.value, page: 1 }))}
                  >
                    <option value="">{t('সকল তীব্রতা', 'All Severities')}</option>
                    <option value="CRITICAL">CRITICAL</option>
                    <option value="HIGH">HIGH</option>
                    <option value="WARNING">WARNING</option>
                    <option value="INFO">INFO</option>
                  </select>
                </div>
                <div className="col-md-3">
                  <select
                    className="form-select form-select-sm"
                    value={campaignFilters.status}
                    onChange={(e) => setCampaignFilters((p) => ({ ...p, status: e.target.value, page: 1 }))}
                  >
                    <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
                    <option value="SCHEDULED">SCHEDULED</option>
                    <option value="QUEUED">QUEUED</option>
                    <option value="DISPATCHED">DISPATCHED</option>
                    <option value="PARTIALLY_FAILED">PARTIALLY_FAILED</option>
                    <option value="FAILED">FAILED</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          {campLoading ? (
            <Loader message="তালিকা লোড হচ্ছে..." messageEn="Loading notification campaigns..." />
          ) : campError ? (
            <AlertBanner variant="danger" message={formatNotificationApiError(campError)} />
          ) : campaigns.length === 0 ? (
            <EmptyState title={t('কোনো বিজ্ঞপ্তি পাওয়া যায়নি', 'No notifications found')} />
          ) : (
            <div className="card shadow-sm border-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('নম্বর', 'No')}</th>
                      <th>{t('শিরোনাম ও ক্যাটাগরি', 'Title & Category')}</th>
                      <th>{t('তীব্রতা', 'Severity')}</th>
                      <th>{t('চ্যানেল', 'Channels')}</th>
                      <th>{t('প্রাপক / সফল / ব্যর্থ', 'Recipients / OK / Fail')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {campaigns.map((c) => (
                      <tr key={c.id}>
                        <td className="fw-semibold small">{c.notification_no}</td>
                        <td>
                          <div className="fw-semibold">{lang === 'en' ? c.title_en : c.title_bn}</div>
                          <div className="mt-1"><CategoryLabel category={c.category} /></div>
                        </td>
                        <td><SeverityBadge severity={c.severity} /></td>
                        <td>
                          <div className="d-flex flex-wrap gap-1">
                            {(c.channels || []).map((ch) => <ChannelBadge key={ch} channel={ch} />)}
                          </div>
                        </td>
                        <td className="small">
                          <strong>{c.total_recipients}</strong> /{' '}
                          <span className="text-success fw-semibold">{c.delivered_count}</span> /{' '}
                          <span className="text-danger fw-semibold">{c.failed_count}</span>
                        </td>
                        <td>
                          <span className="badge bg-secondary">{c.status}</span>
                          {c.scheduled_at && (
                            <div className="small text-muted">{new Date(c.scheduled_at).toLocaleString()}</div>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {campMeta.last_page > 1 && (
                <div className="card-footer bg-white d-flex justify-content-between align-items-center">
                  <small className="text-muted">{campMeta.current_page} / {campMeta.last_page}</small>
                  <div className="btn-group btn-group-sm">
                    <button
                      type="button"
                      className="btn btn-outline-secondary"
                      disabled={campMeta.current_page <= 1}
                      onClick={() => setCampaignFilters((p) => ({ ...p, page: p.page - 1 }))}
                    >
                      {t('পূর্ববর্তী', 'Previous')}
                    </button>
                    <button
                      type="button"
                      className="btn btn-outline-secondary"
                      disabled={campMeta.current_page >= campMeta.last_page}
                      onClick={() => setCampaignFilters((p) => ({ ...p, page: p.page + 1 }))}
                    >
                      {t('পরবর্তী', 'Next')}
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}
        </>
      ) : (
        <>
          <div className="card shadow-sm border-0 mb-3">
            <div className="card-body">
              <div className="row g-2">
                <div className="col-md-4">
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder={t('রেফারেন্স বা শিরোনাম...', 'Search reference or title...')}
                    value={dispatchFilters.search}
                    onChange={(e) => setDispatchFilters((p) => ({ ...p, search: e.target.value, page: 1 }))}
                  />
                </div>
                <div className="col-md-3">
                  <select
                    className="form-select form-select-sm"
                    value={dispatchFilters.channel}
                    onChange={(e) => setDispatchFilters((p) => ({ ...p, channel: e.target.value, page: 1 }))}
                  >
                    <option value="">{t('সকল চ্যানেল', 'All Channels')}</option>
                    <option value="IN_APP">IN_APP</option>
                    <option value="EMAIL">EMAIL</option>
                    <option value="SMS">SMS</option>
                    <option value="PUSH">PUSH</option>
                  </select>
                </div>
                <div className="col-md-3">
                  <select
                    className="form-select form-select-sm"
                    value={dispatchFilters.delivery_status}
                    onChange={(e) => setDispatchFilters((p) => ({ ...p, delivery_status: e.target.value, page: 1 }))}
                  >
                    <option value="">{t('সকল ডেলিভারি অবস্থা', 'All Delivery Statuses')}</option>
                    <option value="DELIVERED">DELIVERED</option>
                    <option value="SENT">SENT</option>
                    <option value="QUEUED">QUEUED</option>
                    <option value="RETRYING">RETRYING</option>
                    <option value="FAILED">FAILED</option>
                    <option value="SKIPPED">SKIPPED</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          {dispLoading ? (
            <Loader message="ডেলিভারি লগ লোড হচ্ছে..." messageEn="Loading delivery logs..." />
          ) : dispError ? (
            <AlertBanner variant="danger" message={formatNotificationApiError(dispError)} />
          ) : dispatches.length === 0 ? (
            <EmptyState title={t('কোনো ডেলিভারি লগ পাওয়া যায়নি', 'No delivery logs found')} />
          ) : (
            <div className="card shadow-sm border-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('চ্যানেল', 'Channel')}</th>
                      <th>{t('প্রাপক (মাস্কড)', 'Recipient (Masked)')}</th>
                      <th>{t('প্রোভাইডার ও রেফারেন্স', 'Provider & Ref')}</th>
                      <th>{t('চেষ্টা', 'Attempts')}</th>
                      <th>{t('অবস্থা', 'Status')}</th>
                      <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {dispatches.map((d) => (
                      <tr key={d.id}>
                        <td><ChannelBadge channel={d.channel} /></td>
                        <td>
                          <div className="fw-semibold small">
                            {d.recipient_summary
                              ? (lang === 'en' ? d.recipient_summary.full_name_en : (d.recipient_summary.full_name_bn || d.recipient_summary.full_name_en))
                              : t('সিস্টেম প্রাপক', 'System Recipient')}
                          </div>
                          <code className="small text-muted">{d.masked_destination}</code>
                        </td>
                        <td className="small">
                          <div>{d.provider_name || '-'}</div>
                          <span className="text-muted">{d.provider_reference || '-'}</span>
                          {d.last_error && <div className="text-danger">{d.last_error}</div>}
                        </td>
                        <td className="small">{d.attempt_count}</td>
                        <td><DeliveryStatusBadge status={d.delivery_status} /></td>
                        <td className="text-end">
                          {canRetry && d.delivery_status === 'FAILED' && (
                            <button
                              type="button"
                              className="btn btn-sm btn-outline-danger"
                              disabled={retryMutation.isPending}
                              onClick={() => retryMutation.mutate(d.id)}
                            >
                              <i className="bi bi-arrow-clockwise me-1"></i>
                              {t('পুনঃচেষ্টা', 'Retry')}
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {dispMeta.last_page > 1 && (
                <div className="card-footer bg-white d-flex justify-content-between align-items-center">
                  <small className="text-muted">{dispMeta.current_page} / {dispMeta.last_page}</small>
                  <div className="btn-group btn-group-sm">
                    <button
                      type="button"
                      className="btn btn-outline-secondary"
                      disabled={dispMeta.current_page <= 1}
                      onClick={() => setDispatchFilters((p) => ({ ...p, page: p.page - 1 }))}
                    >
                      {t('পূর্ববর্তী', 'Previous')}
                    </button>
                    <button
                      type="button"
                      className="btn btn-outline-secondary"
                      disabled={dispMeta.current_page >= dispMeta.last_page}
                      onClick={() => setDispatchFilters((p) => ({ ...p, page: p.page + 1 }))}
                    >
                      {t('পরবর্তী', 'Next')}
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default NotificationLogPage;
