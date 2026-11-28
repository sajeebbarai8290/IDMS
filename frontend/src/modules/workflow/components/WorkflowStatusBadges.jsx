import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';
import {
  WORKFLOW_ACTION_META,
  WORKFLOW_LEVEL_META,
  WORKFLOW_PRIORITY_META,
  WORKFLOW_STATE_META,
} from '../utils/workflowPermissions';

export const WorkflowStateBadge = ({ state }) => {
  const { t } = useLanguage();
  const meta = WORKFLOW_STATE_META[state] || { bn: state, en: state, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant} ${meta.variant === 'warning' ? 'text-dark' : ''}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const WorkflowPriorityBadge = ({ priority }) => {
  const { t } = useLanguage();
  const meta = WORKFLOW_PRIORITY_META[priority] || { bn: priority, en: priority, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant} ${meta.variant === 'warning' ? 'text-dark' : ''}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const WorkflowLevelLabel = ({ level }) => {
  const { t } = useLanguage();
  const meta = WORKFLOW_LEVEL_META[level];
  return <span>{meta ? t(meta.bn, meta.en) : level}</span>;
};

export const WorkflowActionBadge = ({ action }) => {
  const { t } = useLanguage();
  const meta = WORKFLOW_ACTION_META[action] || { bn: action, en: action, variant: 'secondary' };
  return (
    <span className={`badge bg-${meta.variant} ${meta.variant === 'warning' ? 'text-dark' : ''}`}>
      {t(meta.bn, meta.en)}
    </span>
  );
};

export const OverdueBadge = ({ isOverdue }) => {
  const { t } = useLanguage();
  if (!isOverdue) return null;
  return (
    <span className="badge bg-danger ms-1">
      <i className="bi bi-alarm-fill me-1"></i>
      {t('মেয়াদোত্তীর্ণ', 'Overdue')}
    </span>
  );
};
