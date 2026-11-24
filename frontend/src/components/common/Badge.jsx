import React from 'react';

/**
 * Reusable Status & Administrative Tier Badge Component.
 */
export const Badge = ({
  variant = 'secondary',
  tier = null,
  children,
  icon = null,
  className = '',
}) => {
  let badgeClass = `badge bg-${variant}`;

  if (tier) {
    const tierMap = {
      NATIONAL: 'tier-national',
      DIRECTORATE: 'tier-directorate',
      DIVISION: 'tier-division',
      DISTRICT: 'tier-district',
      UPAZILA: 'tier-upazila',
      UNION: 'tier-union',
      FIELD: 'tier-field',
    };
    const tierClass = tierMap[tier.toUpperCase()] || 'bg-secondary';
    badgeClass = `badge-tier ${tierClass}`;
  }

  return (
    <span className={`${badgeClass} ${className} d-inline-flex align-items-center gap-1`}>
      {icon && <i className={`bi ${icon}`}></i>}
      {children}
    </span>
  );
};

export default Badge;
