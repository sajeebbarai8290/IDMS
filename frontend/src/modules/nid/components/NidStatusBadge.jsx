import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';

const STATUS_STYLES = {
  VERIFIED: {
    cls: 'bg-success-subtle text-success border border-success',
    icon: 'bi-patch-check-fill',
    bn: 'যাচাইকৃত',
    en: 'Verified',
  },
  FAILED: {
    cls: 'bg-danger-subtle text-danger border border-danger',
    icon: 'bi-x-octagon-fill',
    bn: 'ব্যর্থ',
    en: 'Failed',
  },
  MANUAL_REVIEW_REQUIRED: {
    cls: 'bg-warning-subtle text-warning-emphasis border border-warning',
    icon: 'bi-person-check-fill',
    bn: 'ম্যানুয়াল রিভিউ প্রয়োজন',
    en: 'Manual Review Required',
  },
  PROCESSING: {
    cls: 'bg-info-subtle text-info-emphasis border border-info',
    icon: 'bi-arrow-repeat',
    bn: 'প্রক্রিয়াধীন',
    en: 'Processing',
  },
  PENDING: {
    cls: 'bg-secondary-subtle text-secondary border border-secondary',
    icon: 'bi-hourglass-split',
    bn: 'অপেক্ষমাণ',
    en: 'Pending',
  },
};

export const NidStatusBadge = ({ status, isMock = false, manualOverride = false }) => {
  const { t } = useLanguage();
  const norm = (status || 'PENDING').toUpperCase();
  const cfg = STATUS_STYLES[norm] || STATUS_STYLES.PENDING;

  return (
    <span className="d-inline-flex flex-wrap align-items-center gap-1">
      <span className={`badge ${cfg.cls} py-1 px-2`}>
        <i className={`bi ${cfg.icon} me-1`}></i>
        {t(cfg.bn, cfg.en)}
      </span>
      {isMock && (
        <span
          className="badge bg-dark-subtle text-dark border border-dark-subtle py-1 px-2"
          title={t('ডেভেলপমেন্ট মক অ্যাডাপ্টার — সরকারি এনআইডি নয়', 'Simulated Mock Adapter — Not genuine government verification')}
        >
          <i className="bi bi-cpu me-1"></i>
          {t('মক (Mock)', 'MOCK')}
        </span>
      )}
      {manualOverride && (
        <span className="badge bg-primary-subtle text-primary border border-primary py-1 px-2">
          <i className="bi bi-shield-exclamation me-1"></i>
          {t('ওভাররাইড', 'Override')}
        </span>
      )}
    </span>
  );
};

export default NidStatusBadge;
