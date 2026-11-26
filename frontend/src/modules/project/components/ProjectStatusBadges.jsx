import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  DISBURSEMENT_STATUS_META,
  MILESTONE_APPROVAL_META,
  PROJECT_STATUS_META,
  RISK_SEVERITY_META,
} from '../utils/projectPermissions';

export const ProjectStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = PROJECT_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  const cls = meta.variant === 'teal' ? 'bg-success bg-opacity-75' : `bg-${meta.variant}`;
  return <span className={`badge ${cls}`}>{t(meta.bn, meta.en)}</span>;
};

export const MilestoneApprovalBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = MILESTONE_APPROVAL_META[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};

export const DisbursementStatusBadge = ({ status }) => {
  const { t } = useLanguage();
  const meta = DISBURSEMENT_STATUS_META[status] || { bn: status, en: status, variant: 'secondary' };
  return <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>;
};

export const RiskSeverityBadge = ({ severity }) => {
  const { t } = useLanguage();
  const meta = RISK_SEVERITY_META[severity] || { bn: severity, en: severity, variant: 'secondary' };
  const style = meta.variant === 'orange' ? { backgroundColor: '#fd7e14' } : undefined;
  const cls = meta.variant === 'orange' ? 'text-white' : `bg-${meta.variant}`;
  return (
    <span className={`badge ${cls}`} style={style}>
      {t(meta.bn, meta.en)}
    </span>
  );
};
