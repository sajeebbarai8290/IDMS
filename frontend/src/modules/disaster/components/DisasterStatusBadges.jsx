import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';

const PHASE_STYLES = {
  REPORTED: { cls: 'bg-secondary-subtle text-secondary border border-secondary', bn: 'প্রতিবেদিত', en: 'Reported' },
  UNDER_ASSESSMENT: { cls: 'bg-info-subtle text-info-emphasis border border-info', bn: 'মূল্যায়নাধীন', en: 'Under Assessment' },
  VERIFIED: { cls: 'bg-primary-subtle text-primary border border-primary', bn: 'যাচাইকৃত', en: 'Verified' },
  ACTIVE: { cls: 'bg-danger text-white', bn: 'সক্রিয়', en: 'Active' },
  RESPONSE_ONGOING: { cls: 'bg-warning-subtle text-warning-emphasis border border-warning', bn: 'সাড়াদান চলমান', en: 'Response Ongoing' },
  RECOVERY: { cls: 'bg-success-subtle text-success border border-success', bn: 'পুনরুদ্ধার', en: 'Recovery' },
  CLOSED: { cls: 'bg-dark-subtle text-dark border border-dark-subtle', bn: 'সমাপ্ত', en: 'Closed' },
};

const SEVERITY_STYLES = {
  ADVISORY: { cls: 'bg-info-subtle text-info-emphasis border border-info', bn: 'পরামর্শমূলক', en: 'Advisory' },
  WARNING: { cls: 'bg-warning-subtle text-warning-emphasis border border-warning', bn: 'সতর্কতা', en: 'Warning' },
  DANGER: { cls: 'bg-danger-subtle text-danger border border-danger', bn: 'বিপদ', en: 'Danger' },
  GREAT_DANGER: { cls: 'bg-danger text-white', bn: 'মহাবিপদ', en: 'Great Danger' },
  CATASTROPHIC: { cls: 'bg-dark text-danger border border-danger', bn: 'ভয়াবহ বিপর্যয়', en: 'Catastrophic' },
};

export const DisasterPhaseBadge = ({ phase }) => {
  const { t } = useLanguage();
  const norm = (phase || 'REPORTED').toUpperCase();
  const cfg = PHASE_STYLES[norm] || PHASE_STYLES.REPORTED;

  return <span className={`badge ${cfg.cls} py-1 px-2`}>{t(cfg.bn, cfg.en)}</span>;
};

export const DisasterSeverityBadge = ({ severity }) => {
  const { t } = useLanguage();
  const norm = (severity || 'WARNING').toUpperCase();
  const cfg = SEVERITY_STYLES[norm] || SEVERITY_STYLES.WARNING;

  return <span className={`badge ${cfg.cls} py-1 px-2`}>{t(cfg.bn, cfg.en)}</span>;
};
