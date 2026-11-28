import React, { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationApi } from '../../../api/notificationApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  CATEGORY_META,
  formatNotificationApiError,
} from '../utils/notificationPermissions';

export const NotificationPreferencesPage = () => {
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const [prefs, setPrefs] = useState([]);
  const [feedback, setFeedback] = useState(null);
  const [deactivateTarget, setDeactivateTarget] = useState(null);
  const [deviceForm, setDeviceForm] = useState({
    device_uuid: '',
    platform: 'ANDROID',
    push_provider: 'FCM',
    push_token: '',
    device_name: '',
    app_version: '1.0.0',
  });

  const { data: prefRes, isLoading: prefLoading, error: prefError } = useQuery({
    queryKey: ['notifications', 'preferences'],
    queryFn: () => notificationApi.getPreferences(),
  });

  const { data: devRes, isLoading: devLoading } = useQuery({
    queryKey: ['notifications', 'devices'],
    queryFn: () => notificationApi.getDevices(),
  });

  useEffect(() => {
    if (prefRes?.data) {
      setPrefs(prefRes.data);
    }
  }, [prefRes]);

  const savePrefsMutation = useMutation({
    mutationFn: (payload) => notificationApi.updatePreferences(payload),
    onSuccess: (res) => {
      setFeedback({ variant: 'success', message: res?.message || t('পছন্দসমূহ সংরক্ষিত হয়েছে।', 'Notification preferences saved.') });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'preferences'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const registerDeviceMutation = useMutation({
    mutationFn: (payload) => notificationApi.registerDevice(payload),
    onSuccess: () => {
      setFeedback({ variant: 'success', message: t('মোবাইল ডিভাইস নিরাপদভাবে নিবন্ধিত হয়েছে।', 'Mobile push device registered securely.') });
      setDeviceForm({
        device_uuid: '',
        platform: 'ANDROID',
        push_provider: 'FCM',
        push_token: '',
        device_name: '',
        app_version: '1.0.0',
      });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'devices'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const deactivateDeviceMutation = useMutation({
    mutationFn: (id) => notificationApi.deactivateDevice(id),
    onSuccess: () => {
      setFeedback({ variant: 'info', message: t('ডিভাইস টোকেন নিষ্ক্রিয় করা হয়েছে।', 'Device token deactivated.') });
      queryClient.invalidateQueries({ queryKey: ['notifications', 'devices'] });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatNotificationApiError(err) }),
  });

  const toggleChannel = (category, field) => {
    setPrefs((prev) =>
      prev.map((row) => {
        if (row.category !== category) return row;
        if (row.policy_locked && (field === 'in_app_enabled' || field === 'sms_enabled')) {
          return row;
        }
        return { ...row, [field]: !row[field] };
      })
    );
  };

  const devices = devRes?.data || [];

  return (
    <div className="container-fluid py-3">
      <div className="mb-3">
        <h4 className="fw-bold mb-1">
          <i className="bi bi-sliders text-primary me-2"></i>
          {t('বিজ্ঞপ্তি পছন্দ ও মোবাইল ডিভাইস নিবন্ধন', 'Notification Preferences & Flutter Push Devices')}
        </h4>
        <p className="text-muted small mb-0">
          {t(
            'চ্যানেলভিত্তিক পছন্দ নির্ধারণ করুন (জরুরি দুর্যোগ সতর্কতা নীতি অনুযায়ী বাধ্যতামূলক)',
            'Configure delivery channel preferences per category (critical emergency alerts remain policy-enforced)'
          )}
        </p>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      <div className="row g-3">
        <div className="col-lg-8">
          <div className="card shadow-sm border-0">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span>{t('ক্যাটাগরিভিত্তিক চ্যানেল পছন্দ', 'Channel Preferences by Category')}</span>
              <button
                type="button"
                className="btn btn-primary btn-sm"
                disabled={savePrefsMutation.isPending || prefs.length === 0}
                onClick={() => savePrefsMutation.mutate({ preferences: prefs })}
              >
                <i className="bi bi-save me-1"></i>
                {t('পছন্দ সংরক্ষণ করুন', 'Save Preferences')}
              </button>
            </div>
            <div className="card-body p-0">
              {prefLoading ? (
                <div className="p-3"><Loader message="পছন্দ লোড হচ্ছে..." messageEn="Loading preferences..." /></div>
              ) : prefError ? (
                <div className="p-3"><AlertBanner variant="danger" message={formatNotificationApiError(prefError)} /></div>
              ) : (
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>{t('বিজ্ঞপ্তির ধরন', 'Category')}</th>
                        <th className="text-center">IN-APP</th>
                        <th className="text-center">EMAIL</th>
                        <th className="text-center">SMS</th>
                        <th className="text-center">PUSH</th>
                        <th className="text-center">{t('নীতি', 'Policy')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {prefs.map((row) => {
                        const meta = CATEGORY_META[row.category] || { bn: row.category, en: row.category };
                        return (
                          <tr key={row.category}>
                            <td className="fw-semibold small">{lang === 'en' ? meta.en : meta.bn}</td>
                            {['in_app_enabled', 'email_enabled', 'sms_enabled', 'push_enabled'].map((field) => {
                              const lockedField =
                                row.policy_locked && (field === 'in_app_enabled' || field === 'sms_enabled');
                              return (
                                <td className="text-center" key={field}>
                                  <input
                                    type="checkbox"
                                    className="form-check-input"
                                    checked={Boolean(row[field])}
                                    disabled={lockedField}
                                    onChange={() => toggleChannel(row.category, field)}
                                  />
                                </td>
                              );
                            })}
                            <td className="text-center">
                              {row.policy_locked ? (
                                <span className="badge bg-danger">
                                  <i className="bi bi-lock-fill me-1"></i>
                                  {t('বাধ্যতামূলক', 'Mandatory')}
                                </span>
                              ) : (
                                <span className="badge bg-light text-dark border">{t('ঐচ্ছিক', 'Configurable')}</span>
                              )}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card shadow-sm border-0 mb-3">
            <div className="card-header bg-white fw-semibold">
              {t('ফ্লাটার / মোবাইল পুশ ডিভাইস নিবন্ধন', 'Register Flutter Push Device')}
            </div>
            <div className="card-body">
              <form
                className="row g-2"
                onSubmit={(e) => {
                  e.preventDefault();
                  registerDeviceMutation.mutate({ ...deviceForm, locale: lang });
                }}
              >
                <div className="col-12">
                  <label className="form-label small fw-semibold">{t('ডিভাইস ইউইউআইডি *', 'Device UUID *')}</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder="flutter-android-device-001"
                    value={deviceForm.device_uuid}
                    onChange={(e) => setDeviceForm((p) => ({ ...p, device_uuid: e.target.value }))}
                    required
                  />
                </div>
                <div className="col-6">
                  <label className="form-label small fw-semibold">{t('প্ল্যাটফর্ম', 'Platform')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={deviceForm.platform}
                    onChange={(e) =>
                      setDeviceForm((p) => ({
                        ...p,
                        platform: e.target.value,
                        push_provider: e.target.value === 'IOS' ? 'APNS' : 'FCM',
                      }))
                    }
                  >
                    <option value="ANDROID">ANDROID</option>
                    <option value="IOS">IOS</option>
                    <option value="WEB">WEB</option>
                  </select>
                </div>
                <div className="col-6">
                  <label className="form-label small fw-semibold">{t('প্রোভাইডার', 'Provider')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={deviceForm.push_provider}
                    onChange={(e) => setDeviceForm((p) => ({ ...p, push_provider: e.target.value }))}
                  >
                    <option value="FCM">FCM</option>
                    <option value="APNS">APNS</option>
                    <option value="WEB_PUSH">WEB_PUSH</option>
                  </select>
                </div>
                <div className="col-12">
                  <label className="form-label small fw-semibold">{t('পুশ টোকেন * (এনক্রিপ্টেড সংরক্ষিত)', 'Push Token * (Stored Encrypted)')}</label>
                  <input
                    type="password"
                    className="form-control form-control-sm"
                    placeholder="FCM/APNs token..."
                    value={deviceForm.push_token}
                    onChange={(e) => setDeviceForm((p) => ({ ...p, push_token: e.target.value }))}
                    required
                  />
                </div>
                <div className="col-12">
                  <label className="form-label small fw-semibold">{t('ডিভাইস নাম', 'Device Name')}</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    placeholder="Field Enumerator Tablet"
                    value={deviceForm.device_name}
                    onChange={(e) => setDeviceForm((p) => ({ ...p, device_name: e.target.value }))}
                  />
                </div>
                <div className="col-12">
                  <button
                    type="submit"
                    className="btn btn-outline-primary btn-sm w-100"
                    disabled={registerDeviceMutation.isPending}
                  >
                    <i className="bi bi-phone-vibrate me-1"></i>
                    {t('ডিভাইস নিবন্ধন করুন', 'Register Device')}
                  </button>
                </div>
              </form>
            </div>
          </div>

          <div className="card shadow-sm border-0">
            <div className="card-header bg-white fw-semibold">
              {t('নিবন্ধিত ডিভাইসসমূহ', 'Registered Devices')}
            </div>
            <div className="card-body p-0">
              {devLoading ? (
                <div className="p-3"><Loader message="লোড হচ্ছে..." messageEn="Loading devices..." /></div>
              ) : devices.length === 0 ? (
                <div className="p-3"><EmptyState title={t('কোনো ডিভাইস নিবন্ধিত নেই', 'No registered push devices')} /></div>
              ) : (
                <div className="list-group list-group-flush">
                  {devices.map((dev) => (
                    <div key={dev.id} className="list-group-item d-flex justify-content-between align-items-center">
                      <div className="small">
                        <div className="fw-semibold">
                          {dev.device_name || dev.device_uuid}{' '}
                          <span className={`badge bg-${dev.is_active ? 'success' : 'secondary'}`}>
                            {dev.platform} / {dev.push_provider}
                          </span>
                        </div>
                        <div className="text-muted">Fingerprint: <code>{dev.token_fingerprint}</code></div>
                      </div>
                      {dev.is_active && (
                        <button
                          type="button"
                          className="btn btn-sm btn-outline-danger"
                          onClick={() => setDeactivateTarget(dev)}
                        >
                          <i className="bi bi-x-circle"></i>
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      <ConfirmDialog
        isOpen={Boolean(deactivateTarget)}
        title={t('ডিভাইস নিষ্ক্রিয় নিশ্চিতকরণ', 'Deactivate Push Device')}
        titleEn="Confirm Device Deactivation"
        message={t(
          `আপনি কি "${deactivateTarget?.device_name || deactivateTarget?.device_uuid}" ডিভাইসটি নিষ্ক্রিয় করতে চান?`,
          `Are you sure you want to deactivate "${deactivateTarget?.device_name || deactivateTarget?.device_uuid}"?`
        )}
        confirmText={t('হ্যাঁ, নিষ্ক্রিয় করুন', 'Yes, Deactivate')}
        cancelText={t('বাতিল', 'Cancel')}
        isLoading={deactivateDeviceMutation.isPending}
        onCancel={() => setDeactivateTarget(null)}
        onConfirm={() => {
          if (deactivateTarget) {
            deactivateDeviceMutation.mutate(deactivateTarget.id, {
              onSettled: () => setDeactivateTarget(null),
            });
          }
        }}
      />
    </div>
  );
};

export default NotificationPreferencesPage;
