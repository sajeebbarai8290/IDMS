import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  DAMAGE_SEVERITY_META,
  DAMAGE_STATUS_META,
  DAMAGE_TYPE_META,
} from '../utils/damagePermissions';

export const DamageStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = DAMAGE_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const DamageSeverityBadge = ({ severity }) => {
  const { t } = useLanguage();
  const meta = DAMAGE_SEVERITY_META[severity] || { bn: severity, en: severity, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const DamageTypeLabel = ({ type }) => {
  const { t } = useLanguage();
  const meta = DAMAGE_TYPE_META[type] || { bn: type, en: type };
  return <span>{t(meta.bn, meta.en)}</span>;
};
