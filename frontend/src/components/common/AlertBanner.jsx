import React from 'react';

/**
 * Reusable Alert Banner Component.
 */
export const AlertBanner = ({
  variant = 'info',
  title = null,
  message,
  onClose = null,
  icon = null,
}) => {
  const defaultIcons = {
    danger: 'bi-exclamation-triangle-fill',
    warning: 'bi-exclamation-circle-fill',
    success: 'bi-check-circle-fill',
    info: 'bi-info-circle-fill',
  };

  const alertIcon = icon || defaultIcons[variant] || 'bi-info-circle';

  return (
    <div className={`alert alert-${variant} alert-dismissible fade show d-flex align-items-start gap-2 shadow-sm`} role="alert">
      <i className={`bi ${alertIcon} fs-5 mt-1`}></i>
      <div className="flex-grow-1">
        {title && <div className="fw-bold mb-1">{title}</div>}
        <div>{message}</div>
      </div>
      {onClose && (
        <button type="button" className="btn-close" aria-label="Close" onClick={onClose}></button>
      )}
    </div>
  );
};

export default AlertBanner;
