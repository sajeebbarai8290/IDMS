import React from 'react';

/**
 * Reusable Empty State Component for tables, grids, and dashboards.
 */
export const EmptyState = ({
  icon = 'bi-inbox',
  title = 'কোন তথ্য পাওয়া যায়নি',
  titleEn = 'No records found',
  description = 'এই বিভাগে বর্তমানে কোন সক্রিয় তথ্য বিদ্যমান নেই।',
  action = null,
}) => {
  return (
    <div className="idms-card text-center p-5 my-3">
      <div className="mb-3">
        <i className={`bi ${icon} text-muted`} style={{ fontSize: '3rem' }}></i>
      </div>
      <h5 className="fw-bold text-secondary mb-1">{title}</h5>
      <p className="text-muted small mb-1">{titleEn}</p>
      <p className="text-secondary small mb-3">{description}</p>
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
};

export default EmptyState;
