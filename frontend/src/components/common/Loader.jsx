import React from 'react';

/**
 * Reusable Loading Spinner Component with Bangla/English text.
 */
export const Loader = ({ message = 'তথ্য লোড হচ্ছে...', messageEn = 'Loading data...', fullPage = false }) => {
  const content = (
    <div className="d-flex flex-column align-items-center justify-content-center p-4">
      <div className="spinner-border text-success mb-2" role="status" style={{ width: '2.5rem', height: '2.5rem' }}>
        <span className="visually-hidden">Loading...</span>
      </div>
      <div className="fw-semibold text-secondary">{message}</div>
      <small className="text-muted">{messageEn}</small>
    </div>
  );

  if (fullPage) {
    return (
      <div className="d-flex align-items-center justify-content-center min-vh-100 bg-light">
        {content}
      </div>
    );
  }

  return content;
};

export default Loader;
