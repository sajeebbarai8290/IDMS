import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { notificationApi } from '../../../api/notificationApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import {
  AlertStatusBadge,
  CategoryLabel,
  ChannelBadge,
  SeverityBadge,
} from '../components/NotificationBadges';
import {
  canManageAlertRules,
  canManageNotificationTemplates,
  canSendNotificationBroadcast,
  formatNotificationApiError,
} from '../utils/notificationPermissions';

export const NotificationDashboardPage = () => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['notifications', 'dashboard'],
    queryFn: () => notificationApi.getDashboard(),
  });

  if (isLoading) {
    return <Loader message="বিজ্ঞপ্তি ও সতর্কতা ড্যাশবোর্ড লোড হচ্ছে..." messageEn="Loading Notification & Alert dashboard..." />;
  }

  if (error) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner
          variant="danger"
          message={formatNotificationApiError(error, t('ড্যাশবোর্ড লোড করতে ব্যর্থ।', 'Failed to load notification dashboard.'))}
        />
      </div>
    );
  }

  const data = res?.data || {};
  const kpis = data.kpis || {};
  const byChannel = data.by_channel || [];
  const byCategory = data.by_category || [];
  const recentNotifications = data.recent_notifications || [];
  const recentAlerts = data.recent_alerts || [];
  const providerHealth = data.provider_health || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-bell-fill text-primary me-2"></i>
            {t('বিজ্ঞপ্তি ও সতর্কতা ড্যাশবোর্ড (M13)', 'Notification & Alert Dashboard (M13)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'বহু-চ্যানেল জরুরি সতর্কবার্তা, এসএমএস/ইমেইল/পুশ ডেলিভারি পর্যবেক্ষণ এবং সতর্কতা নিষ্পত্তি',
              'Multi-channel emergency alerting, SMS/Email/Push delivery tracking, and active alert operations'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <Link to="/notifications/inbox" className="btn btn-outline-primary btn-sm">
            <i className="bi bi-inbox-fill me-1"></i>
            {t('ইন-অ্যাপ ইনবক্স', 'My Inbox')} ({kpis.unread_in_app ?? 0})
          </Link>
          {canSendNotificationBroadcast(user) && (
            <Link to="/notifications/send" className="btn btn-danger btn-sm">
              <i className="bi bi-megaphone-fill me-1"></i>
              {t('নতুন বিজ্ঞপ্তি / জরুরি প্রচার', 'Send Notification')}
            </Link>
          )}
          <Link to="/notifications/alerts" className="btn btn-outline-warning btn-sm">
            <i className="bi bi-exclamation-triangle-fill me-1"></i>
            {t('সতর্কতা কেন্দ্র', 'Alert Console')}
          </Link>
          {canManageNotificationTemplates(user) && (
            <Link to="/notifications/templates" className="btn btn-outline-secondary btn-sm">
              <i className="bi bi-file-earmark-richtext me-1"></i>
              {t('টেমপ্লেট', 'Templates')}
            </Link>
          )}
        </div>
      </div>

      {/* Primary KPI Cards */}
      <div className="row g-3 mb-3">
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('মোট বিজ্ঞপ্তি', 'Total Notifications')}</div>
              <div className="fs-3 fw-bold text-primary">{kpis.total_notifications ?? 0}</div>
              <div className="small text-muted">{t('মোট ডিসপ্যাচ', 'Dispatches')}: {kpis.total_dispatches ?? 0}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('বিতরণকৃত', 'Delivered')}</div>
              <div className="fs-3 fw-bold text-success">{kpis.delivered_notifications ?? 0}</div>
              <div className="small text-muted">{t('সফল ডেলিভারি', 'Successful deliveries')}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('ব্যর্থ বিজ্ঞপ্তি', 'Failed')}</div>
              <div className="fs-3 fw-bold text-danger">{kpis.failed_notifications ?? 0}</div>
              <div className="small text-muted">{t('পুনঃচেষ্টাযোগ্য', 'Retry eligible')}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('অপেক্ষমাণ', 'Pending')}</div>
              <div className="fs-3 fw-bold text-warning">{kpis.pending_notifications ?? 0}</div>
              <div className="small text-muted">{t('সারিবদ্ধ / পুনঃচেষ্টা', 'Queued / retrying')}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('সক্রিয় সতর্কতা', 'Active Alerts')}</div>
              <div className="fs-3 fw-bold text-danger">{kpis.active_alerts ?? 0}</div>
              <div className="small text-muted">{t('এস্কেলেটেড', 'Escalated')}: {kpis.escalated_alerts ?? 0}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('অগৃহীত সতর্কতা', 'Unacknowledged Alerts')}</div>
              <div className="fs-3 fw-bold text-dark">{kpis.unacknowledged_alerts ?? 0}</div>
              <div className="small text-muted">{t('সমাধানকৃত', 'Resolved')}: {kpis.resolved_alerts ?? 0}</div>
            </div>
          </div>
        </div>
      </div>

      {/* Channel Health & Category Breakdown */}
      <div className="row g-3 mb-3">
        <div className="col-lg-6">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span>{t('চ্যানেলভিত্তিক বিতরণ সারাংশ', 'Channel Delivery Summary')}</span>
              <Link to="/notifications/logs" className="btn btn-link btn-sm p-0 text-decoration-none">
                {t('ডেলিভারি লগ দেখুন', 'View Logs')}
              </Link>
            </div>
            <div className="card-body">
              {byChannel.length === 0 ? (
                <EmptyState title={t('কোনো ডিসপ্যাচ রেকর্ড নেই', 'No dispatch records yet')} />
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th>{t('চ্যানেল', 'Channel')}</th>
                        <th className="text-end">{t('মোট', 'Total')}</th>
                        <th className="text-end text-success">{t('সফল', 'Delivered')}</th>
                        <th className="text-end text-warning">{t('অপেক্ষমাণ', 'Pending')}</th>
                        <th className="text-end text-danger">{t('ব্যর্থ', 'Failed')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {byChannel.map((row) => (
                        <tr key={row.channel}>
                          <td><ChannelBadge channel={row.channel} /></td>
                          <td className="text-end fw-semibold">{row.total}</td>
                          <td className="text-end text-success">{row.delivered}</td>
                          <td className="text-end text-warning">{row.pending}</td>
                          <td className="text-end text-danger">{row.failed}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-6">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-semibold">
              {t('গেটওয়ে ও ক্যাটাগরি অবস্থা', 'Gateway Readiness & Category Breakdown')}
            </div>
            <div className="card-body">
              <div className="row g-2 mb-3">
                {['sms', 'push', 'email'].map((gw) => {
                  const info = providerHealth[gw] || {};
                  return (
                    <div key={gw} className="col-md-4">
                      <div className="border rounded p-2 bg-light">
                        <div className="small text-muted text-uppercase fw-semibold">{gw}</div>
                        <div className="fw-semibold small">{info.driver || 'N/A'}</div>
                        <span className={`badge bg-${info.status === 'READY' ? 'success' : 'warning'} mt-1`}>
                          {info.status || 'UNKNOWN'}
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>

              {byCategory.length > 0 && (
                <div className="d-flex flex-wrap gap-2">
                  {byCategory.map((item) => (
                    <div key={item.category} className="border rounded px-2 py-1 small d-flex align-items-center gap-2">
                      <CategoryLabel category={item.category} />
                      <span className="fw-bold">{item.total}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Recent Alerts & Recent Notifications */}
      <div className="row g-3">
        <div className="col-lg-6">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span>{t('সাম্প্রতিক সতর্কতা', 'Recent Operational Alerts')}</span>
              <Link to="/notifications/alerts" className="btn btn-link btn-sm p-0 text-decoration-none">
                {canManageAlertRules(user) ? t('সকল সতর্কতা ও নিয়ম', 'Alerts & Rules') : t('সকল সতর্কতা', 'All Alerts')}
              </Link>
            </div>
            <div className="card-body p-0">
              {recentAlerts.length === 0 ? (
                <div className="p-3"><EmptyState title={t('কোনো সতর্কতা নেই', 'No alerts found')} /></div>
              ) : (
                <div className="list-group list-group-flush">
                  {recentAlerts.map((alert) => (
                    <div key={alert.id} className="list-group-item">
                      <div className="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div className="fw-semibold">
                            <span className="text-muted small me-2">{alert.alert_no}</span>
                            {lang === 'en' ? alert.title_en : alert.title_bn}
                          </div>
                          <div className="small text-muted mt-1">
                            {alert.administrative_unit ? (lang === 'en' ? alert.administrative_unit.name_en : alert.administrative_unit.name_bn) : t('জাতীয়', 'National')}
                          </div>
                        </div>
                        <div className="d-flex gap-1">
                          <SeverityBadge severity={alert.severity} />
                          <AlertStatusBadge status={alert.status} />
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-6">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span>{t('সাম্প্রতিক বিজ্ঞপ্তি প্রেরণ', 'Recent Notification Campaigns')}</span>
              <Link to="/notifications/logs" className="btn btn-link btn-sm p-0 text-decoration-none">
                {t('সবগুলো দেখুন', 'View All')}
              </Link>
            </div>
            <div className="card-body p-0">
              {recentNotifications.length === 0 ? (
                <div className="p-3"><EmptyState title={t('কোনো বিজ্ঞপ্তি নেই', 'No notifications sent yet')} /></div>
              ) : (
                <div className="list-group list-group-flush">
                  {recentNotifications.map((item) => (
                    <div key={item.id} className="list-group-item">
                      <div className="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div className="fw-semibold">
                            <span className="text-muted small me-2">{item.notification_no}</span>
                            {lang === 'en' ? item.title_en : item.title_bn}
                          </div>
                          <div className="small text-muted mt-1 d-flex flex-wrap gap-2">
                            <CategoryLabel category={item.category} />
                            <span>{t('প্রাপক', 'Recipients')}: {item.total_recipients}</span>
                            <span className="text-success">{t('সফল', 'Delivered')}: {item.delivered_count}</span>
                          </div>
                        </div>
                        <SeverityBadge severity={item.severity} />
                      </div>
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

export default NotificationDashboardPage;
