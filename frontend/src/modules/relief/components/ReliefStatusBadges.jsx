import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  ALLOCATION_STATUS_META,
  PRIORITY_META,
  REQUISITION_STATUS_META,
} from '../utils/reliefPermissions';

export const RequisitionStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = REQUISITION_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const AllocationStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = ALLOCATION_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const PriorityBadge = ({ priority }) => {
  const { t } = useLanguage();
  const meta = PRIORITY_META[priority] || { bn: priority, en: priority, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant} bg-opacity-75`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};
