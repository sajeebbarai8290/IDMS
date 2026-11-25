import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import { OPERATION_STATUS_META, TASK_STATUS_META } from '../utils/responsePermissions';

export const OperationStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = OPERATION_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};

export const TaskStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = TASK_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};
