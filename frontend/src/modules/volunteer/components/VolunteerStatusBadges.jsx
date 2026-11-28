import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  DEPLOYMENT_STATUS_META,
  VOLUNTEER_AVAILABILITY_META,
  VOLUNTEER_VERIFICATION_META,
} from '../utils/volunteerPermissions';

export const VolunteerVerificationBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = VOLUNTEER_VERIFICATION_META[status] || { bn: status, en: status, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const VolunteerAvailabilityBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = VOLUNTEER_AVAILABILITY_META[status] || { bn: status, en: status, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const DeploymentStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = DEPLOYMENT_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const ConfirmationStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const map = {
    PENDING: { bn: 'সুপারভাইজার অপেক্ষমাণ', en: 'Pending Confirmation', variant: 'warning' },
    CONFIRMED: { bn: 'নিশ্চিতকৃত', en: 'Confirmed', variant: 'success' },
    DISPUTED: { bn: 'আপত্তিযুক্ত', en: 'Disputed', variant: 'danger' },
  };
  const meta = map[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};
