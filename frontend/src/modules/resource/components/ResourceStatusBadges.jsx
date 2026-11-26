import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  ALLOCATION_STATUS_META,
  MAINTENANCE_STATUS_META,
  RESOURCE_STATUS_META,
} from '../utils/resourcePermissions';

export const ResourceStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = RESOURCE_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  const textDark = meta.variant === 'warning' || meta.variant === 'info' ? 'text-dark' : '';
  return <span className={`badge bg-${meta.variant} ${textDark}`}>{t(meta.bn, meta.en)}</span>;
};

export const AllocationStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = ALLOCATION_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  const textDark = meta.variant === 'info' ? 'text-dark' : '';
  return <span className={`badge bg-${meta.variant} ${textDark}`}>{t(meta.bn, meta.en)}</span>;
};

export const MaintenanceStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = MAINTENANCE_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  const textDark = meta.variant === 'warning' ? 'text-dark' : '';
  return <span className={`badge bg-${meta.variant} ${textDark}`}>{t(meta.bn, meta.en)}</span>;
};
