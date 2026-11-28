import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  ACCOUNT_STATUS_META,
  IDP_SYNC_META,
  SCOPE_ASSIGNMENT_META,
  TIER_LEVEL_META,
} from '../utils/userPermissions';

export const AccountStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = ACCOUNT_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};

export const IdpSyncBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = IDP_SYNC_META[status] || { bn: status || 'N/A', en: status || 'N/A', variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};

export const MfaStatusBadge = ({ enforced, verified }) => {
  const { t } = useLanguage();
  if (verified) {
    return (
      <span className="badge bg-success">
        <i className="bi bi-shield-lock-fill me-1"></i>
        {t('এমএফএ যাচাইকৃত', 'MFA Verified')}
      </span>
    );
  }
  if (enforced) {
    return (
      <span className="badge bg-warning text-dark">
        <i className="bi bi-shield-exclamation me-1"></i>
        {t('এমএফএ বাধ্যতামূলক', 'MFA Enforced')}
      </span>
    );
  }
  return (
    <span className="badge bg-light text-secondary border">
      {t('ঐচ্ছিক', 'Optional')}
    </span>
  );
};

export const TierLevelLabel = ({ tier }) => {
  const { t } = useLanguage();
  const meta = TIER_LEVEL_META[tier];
  return <span>{meta ? t(meta.bn, meta.en) : tier}</span>;
};

export const ScopeAssignmentBadge = ({ type }) => {
  const { t } = useLanguage();
  const meta = SCOPE_ASSIGNMENT_META[type] || { bn: type, en: type, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};
