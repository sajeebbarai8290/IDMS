import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationApi } from '../../../api/notificationApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { CategoryLabel, SeverityBadge } from '../components/NotificationBadges';
import { CATEGORY_META, formatNotificationApiError } from '../utils/notificationPermissions';

export const NotificationCenterPage = () => {
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const [filters, setFilters] = useState({
    unread_only: false,
    category: '',
    severity: '',
    search: '',
    page: 1,
  });
  const [feedback, setFeedback] = useState(null);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['notifications', 'inbox', filters],
    queryFn: () => notificationApi.getInbox({
      unread_only: filters.unread_only ? 'true' : undefined,
      category: filters.category || undefined,
      severity: filters.severity || undefined,
      search: filters.search || undefined,
      page: filters.page,
      per_page: 15,
    }),
  });

  const markReadMutation = useMutation({
    mutationFn: (id) => notificationApi.markInboxRead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications', 'inbox'] });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'dashboard'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const markAllMutation = useMutation({
    mutationFn: () => notificationApi.markAllInboxRead(),
    onSuccess: () => {
      setFeedback({ variant: 'success', message: t('সকল বিজ্ঞপ্তি পঠিত হিসেবে চিহ্নিত হয়েছে।', 'All notifications marked as read.') });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'inbox'] });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'dashboard'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const items = res?.data || [];
  const meta = res?.meta || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-inbox-fill text-primary me-2"></i>
            {t('আমার বিজ্ঞপ্তি ইনবক্স', 'My In-App Notification Inbox')}
          </h4>
          <p className="text-muted small mb-0">
            {t('অপঠিত বিজ্ঞপ্তি', 'Unread notifications')}: <strong>{meta.unread_count ?? 0}</strong>
          </p>
        </div>

        <button
          type="button"
          className="btn btn-outline-success btn-sm"
          disabled={markAllMutation.isPending || (meta.unread_count ?? 0) === 0}
          onClick={() => markAllMutation.mutate()}
        >
          <i className="bi bi-check2-all me-1"></i>
          {t('সব পঠিত চিহ্নিত করুন', 'Mark All as Read')}
        </button>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      <div className="card shadow-sm border-0 mb-3">
        <div className="card-body">
          <div className="row g-2 align-items-end">
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('অনুসন্ধান', 'Search')}</label>
              <input
                type="text"
                className="form-control form-control-sm"
                value={filters.search}
                placeholder={t('শিরোনাম বা বার্তা লিখুন...', 'Search title or message...')}
                onChange={(e) => setFilters((p) => ({ ...p, search: e.target.value, page: 1 }))}
              />
            </div>
            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('ক্যাটাগরি', 'Category')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.category}
                onChange={(e) => setFilters((p) => ({ ...p, category: e.target.value, page: 1 }))}
              >
                <option value="">{t('সকল ক্যাটাগরি', 'All Categories')}</option>
                {Object.entries(CATEGORY_META).map(([key, metaItem]) => (
                  <option key={key} value={key}>{lang === 'en' ? metaItem.en : metaItem.bn}</option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <label className="form-label small fw-semibold">{t('তীব্রতা', 'Severity')}</label>
              <select
                className="form-select form-select-sm"
                value={filters.severity}
                onChange={(e) => setFilters((p) => ({ ...p, severity: e.target.value, page: 1 }))}
              >
                <option value="">{t('সকল', 'All')}</option>
                <option value="CRITICAL">CRITICAL</option>
                <option value="HIGH">HIGH</option>
                <option value="WARNING">WARNING</option>
                <option value="INFO">INFO</option>
              </select>
            </div>
            <div className="col-md-3">
              <div className="form-check">
                <input
                  id="unreadOnlyCheck"
                  className="form-check-input"
                  type="checkbox"
                  checked={filters.unread_only}
                  onChange={(e) => setFilters((p) => ({ ...p, unread_only: e.target.checked, page: 1 }))}
                />
                <label className="form-check-label small fw-semibold" htmlFor="unreadOnlyCheck">
                  {t('শুধুমাত্র অপঠিত দেখান', 'Show unread only')}
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader message="ইনবক্স লোড হচ্ছে..." messageEn="Loading inbox..." />
      ) : error ? (
        <AlertBanner variant="danger" message={formatNotificationApiError(error)} />
      ) : items.length === 0 ? (
        <EmptyState title={t('কোনো বিজ্ঞপ্তি পাওয়া যায়নি', 'No notifications in your inbox')} />
      ) : (
        <div className="card shadow-sm border-0">
          <div className="list-group list-group-flush">
            {items.map((item) => (
              <div
                key={item.id}
                className={`list-group-item ${item.is_read ? '' : 'bg-primary bg-opacity-10 border-start border-4 border-primary'}`}
              >
                <div className="d-flex flex-wrap justify-content-between align-items-start gap-2">
                  <div className="flex-grow-1">
                    <div className="d-flex flex-wrap align-items-center gap-2 mb-1">
                      {!item.is_read && (
                        <span className="badge bg-primary">{t('নতুন', 'NEW')}</span>
                      )}
                      <SeverityBadge severity={item.severity || 'INFO'} />
                      {item.category && <CategoryLabel category={item.category} />}
                      <span className="small text-muted">{item.notification_no}</span>
                    </div>
                    <h6 className="fw-bold mb-1">
                      {lang === 'en' ? (item.title_en || item.title_bn) : (item.title_bn || item.title_en)}
                    </h6>
                    <p className="mb-1 small">
                      {lang === 'en' ? (item.message_body_en || item.message_body) : (item.message_body_bn || item.message_body)}
                    </p>
                    <small className="text-muted">
                      {item.created_at ? new Date(item.created_at).toLocaleString() : ''}
                    </small>
                  </div>

                  <div>
                    {!item.is_read ? (
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-primary"
                        disabled={markReadMutation.isPending}
                        onClick={() => markReadMutation.mutate(item.id)}
                      >
                        <i className="bi bi-check2 me-1"></i>
                        {t('পঠিত চিহ্নিত করুন', 'Mark Read')}
                      </button>
                    ) : (
                      <span className="badge bg-light text-muted border">
                        <i className="bi bi-check2-all me-1"></i>
                        {t('পঠিত', 'Read')}
                      </span>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>

          {meta.last_page > 1 && (
            <div className="card-footer bg-white d-flex justify-content-between align-items-center">
              <small className="text-muted">
                {t('পৃষ্ঠা', 'Page')} {meta.current_page} / {meta.last_page}
              </small>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page <= 1}
                  onClick={() => setFilters((p) => ({ ...p, page: p.page - 1 }))}
                >
                  {t('পূর্ববর্তী', 'Previous')}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => setFilters((p) => ({ ...p, page: p.page + 1 }))}
                >
                  {t('পরবর্তী', 'Next')}
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default NotificationCenterPage;
