import React, { useState } from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';

export const NidManualReviewModal = ({
  isOpen,
  verification,
  onClose,
  onSubmit,
  isSubmitting = false,
  errorMessage = null,
}) => {
  const { t } = useLanguage();
  const [decisionStatus, setDecisionStatus] = useState('VERIFIED');
  const [overrideReason, setOverrideReason] = useState('');
  const [validationError, setValidationError] = useState('');

  if (!isOpen || !verification) return null;

  const handleConfirm = (e) => {
    e.preventDefault();
    if (overrideReason.trim().length < 10) {
      setValidationError(
        t(
          'ম্যানুয়াল রিভিউয়ের কারণ কমপক্ষে ১০ অক্ষরের হতে হবে।',
          'Override reason must be at least 10 characters.'
        )
      );
      return;
    }
    setValidationError('');
    onSubmit({
      decision_status: decisionStatus,
      override_reason: overrideReason.trim(),
    });
  };

  return (
    <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
      <div className="modal-dialog modal-dialog-centered">
        <form className="modal-content shadow-lg border-0" onSubmit={handleConfirm}>
          <div className="modal-header bg-light">
            <div>
              <h5 className="modal-title fw-bold mb-0">
                <i className="bi bi-shield-check me-2 text-primary"></i>
                {t('ম্যানুয়াল এনআইডি রিভিউ ও ওভাররাইড', 'Manual NID Review & Override')}
              </h5>
              <small className="text-muted font-monospace">{verification.verification_reference}</small>
            </div>
            <button type="button" className="btn-close" onClick={onClose} disabled={isSubmitting}></button>
          </div>

          <div className="modal-body">
            <div className="alert alert-info py-2 small">
              <strong>{t('মাস্কড এনআইডি:', 'Masked NID:')}</strong>{' '}
              <span className="font-monospace">{verification.nid_masked}</span> •{' '}
              <strong>{t('বর্তমান অবস্থা:', 'Current Status:')}</strong> {verification.verification_status}
            </div>

            {errorMessage && (
              <div className="alert alert-danger py-2 small">{errorMessage}</div>
            )}

            <div className="mb-3">
              <label className="form-label small fw-bold">
                {t('রিভিউ সিদ্ধান্ত *', 'Review Decision *')}
              </label>
              <select
                className="form-select form-select-sm"
                value={decisionStatus}
                onChange={(e) => setDecisionStatus(e.target.value)}
                disabled={isSubmitting}
              >
                <option value="VERIFIED">
                  {t('যাচাইকৃত হিসেবে অনুমোদন করুন (VERIFIED)', 'Approve as VERIFIED')}
                </option>
                <option value="FAILED">
                  {t('ব্যর্থ / বাতিল হিসেবে চিহ্নিত করুন (FAILED)', 'Mark as FAILED')}
                </option>
              </select>
            </div>

            <div className="mb-2">
              <label className="form-label small fw-bold">
                {t('ওভাররাইড কারণ ও দাপ্তরিক নোট *', 'Override Justification & Audit Note *')}
              </label>
              <textarea
                className={`form-control form-control-sm ${validationError ? 'is-invalid' : ''}`}
                rows="4"
                value={overrideReason}
                onChange={(e) => setOverrideReason(e.target.value)}
                placeholder={t(
                  'মূল জাতীয় পরিচয়পত্র কপি যাচাই সাপেক্ষে অনুমোদনের/বাতিলের কারণ লিখুন...',
                  'Document physical NID inspection findings and reason for manual override...'
                )}
                disabled={isSubmitting}
                required
              ></textarea>
              {validationError && <div className="invalid-feedback">{validationError}</div>}
              <div className="form-text">
                {t(
                  'এই সিদ্ধান্তটি অডিট ট্রেইলে স্থায়ীভাবে সংরক্ষিত হবে।',
                  'This decision and reason will be permanently recorded in the audit trail.'
                )}
              </div>
            </div>
          </div>

          <div className="modal-footer">
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={onClose} disabled={isSubmitting}>
              {t('বাতিল', 'Cancel')}
            </button>
            <button type="submit" className="btn btn-primary btn-sm" disabled={isSubmitting}>
              {isSubmitting && <span className="spinner-border spinner-border-sm me-2"></span>}
              {t('সিদ্ধান্ত সংরক্ষণ করুন', 'Submit Review Decision')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

export default NidManualReviewModal;
