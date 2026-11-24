import React from 'react';

export const VerificationStatusBadge = ({ status }) => {
  const normStatus = (status || 'DRAFT').toUpperCase();

  switch (normStatus) {
    case 'APPROVED':
      return (
        <span className="badge bg-success-subtle text-success border border-success py-1 px-2">
          <i className="bi bi-check-circle-fill me-1"></i>
          অনুমোদিত (Approved)
        </span>
      );
    case 'FIELD_VERIFIED':
      return (
        <span className="badge bg-primary-subtle text-primary border border-primary py-1 px-2">
          <i className="bi bi-geo-alt-fill me-1"></i>
          মাঠ যাচাইকৃত (Field Verified)
        </span>
      );
    case 'PENDING_VERIFICATION':
      return (
        <span className="badge bg-warning-subtle text-warning-emphasis border border-warning py-1 px-2">
          <i className="bi bi-hourglass-split me-1"></i>
          যাচাই অপেক্ষমান (Pending)
        </span>
      );
    case 'REJECTED':
      return (
        <span className="badge bg-danger-subtle text-danger border border-danger py-1 px-2">
          <i className="bi bi-x-circle-fill me-1"></i>
          বাতিলকৃত (Rejected)
        </span>
      );
    default:
      return (
        <span className="badge bg-secondary-subtle text-secondary border border-secondary py-1 px-2">
          <i className="bi bi-file-earmark-text me-1"></i>
          খসড়া (Draft)
        </span>
      );
  }
};

export default VerificationStatusBadge;
