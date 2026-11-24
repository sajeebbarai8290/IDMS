import React from 'react';

/**
 * Reusable Confirmation Modal Dialog Component.
 */
export const ConfirmDialog = ({
  isOpen,
  title = 'নিশ্চিতকরণ',
  titleEn = 'Confirmation Required',
  message = 'আপনি কি এই কার্যটি সম্পন্ন করতে নিশ্চিত?',
  confirmText = 'হ্যাঁ, নিশ্চিত করুন',
  cancelText = 'বাতিল',
  confirmVariant = 'danger',
  onConfirm,
  onCancel,
  isLoading = false,
}) => {
  if (!isOpen) return null;

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content shadow-lg border-0">
          <div className="modal-header border-bottom-0 pb-0">
            <div>
              <h5 className="modal-title fw-bold text-secondary mb-0">{title}</h5>
              <small className="text-muted">{titleEn}</small>
            </div>
            <button type="button" className="btn-close" aria-label="Close" onClick={onCancel} disabled={isLoading}></button>
          </div>
          <div className="modal-body py-4">
            <p className="text-secondary mb-0">{message}</p>
          </div>
          <div className="modal-footer border-top-0 pt-0">
            <button type="button" className="btn btn-outline-secondary" onClick={onCancel} disabled={isLoading}>
              {cancelText}
            </button>
            <button
              type="button"
              className={`btn btn-${confirmVariant} d-inline-flex align-items-center gap-2`}
              onClick={onConfirm}
              disabled={isLoading}
            >
              {isLoading && <span className="spinner-border spinner-border-sm" role="status"></span>}
              {confirmText}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ConfirmDialog;
