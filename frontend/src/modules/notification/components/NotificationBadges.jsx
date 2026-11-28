import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  ALERT_STATUS_META,
  CATEGORY_META,
  CHANNEL_META,
  DELIVERY_STATUS_META,
  SEVERITY_META,
} from '../utils/notificationPermissions';

export const SeverityBadge = ({ severity }) => {
  const { lang } = useLanguage();
  const meta = SEVERITY_META[severity] || { bn: severity, en: severity, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{lang === 'en' ? meta.en : meta.bn}</span>;
};

export const DeliveryStatusBadge = ({ status }) => {
  const { lang } = useLanguage();
  const meta = DELIVERY_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{lang === 'en' ? meta.en : meta.bn}</span>;
};

export const AlertStatusBadge = ({ status }) => {
  const { lang } = useLanguage();
  const meta = ALERT_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{lang === 'en' ? meta.en : meta.bn}</span>;
};

export const ChannelBadge = ({ channel }) => {
  const { lang } = useLanguage();
  const meta = CHANNEL_META[channel] || { bn: channel, en: channel, icon: 'bi-bell' };
  return (
    <span className="badge bg-light text-dark border d-inline-flex align-items-center gap-1">
      <i className={`bi ${meta.icon} text-primary`}></i>
      <span>{lang === 'en' ? meta.en : meta.bn}</span>
    </span>
  );
};

export const CategoryLabel = ({ category }) => {
  const { lang } = useLanguage();
  const meta = CATEGORY_META[category] || { bn: category, en: category, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant} bg-opacity-75`}>{lang === 'en' ? meta.en : meta.bn}</span>;
};
