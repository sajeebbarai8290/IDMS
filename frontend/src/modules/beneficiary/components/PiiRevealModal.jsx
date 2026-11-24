import React, { useState } from 'react';
import beneficiaryApi from '../../../api/beneficiaryApi';

export const PiiRevealModal = ({ householdId, maskedNid, maskedMobile, onUnmasked }) => {
  const [show, setShow] = useState(false);
  const [loading, setLoading] = useState(false);
  const [unmaskedData, setUnmaskedData] = useState(null);
  const [error, setError] = useState(null);

  const handleReveal = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await beneficiaryApi.revealPii(householdId);
      const isSuccess = res?.success ?? res?.data?.success;
      const dataPayload = res?.data || res;
      const unmaskedNid = dataPayload?.unmasked_nid || res?.unmasked_nid;

      if (isSuccess && unmaskedNid) {
        const finalData = {
          ...dataPayload,
          unmasked_nid: unmaskedNid,
          unmasked_mobile: dataPayload?.unmasked_mobile || res?.unmasked_mobile,
          warning: dataPayload?.warning || res?.warning || 'তথ্য উন্মোচন অডিট লগে রেকর্ড করা হয়েছে।',
        };
        setUnmaskedData(finalData);
        if (onUnmasked) {
          onUnmasked(finalData);
        }
      } else {
        setError(dataPayload?.message || res?.message || 'অনুমোদন ব্যর্থ হয়েছে বা অনুমতি নেই।');
      }
    } catch (err) {
      const errorMsg =
        err?.message ||
        err?.response?.data?.message ||
        'সংবেদনশীল তথ্য উন্মোচন করার অনুমতি নেই। (Unauthorized to decrypt PII)';
      setError(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  const handleClose = () => {
    setShow(false);
    setUnmaskedData(null);
    setError(null);
  };

  return (
    <>
      <button
        type="button"
        className="btn btn-outline-danger btn-sm"
        onClick={() => setShow(true)}
        title="সংবেদনশীল নাগরিক তথ্য উন্মোচন করুন"
      >
        <i className="bi bi-eye-fill me-1"></i>
        এনআইডি দেখুন (PII)
      </button>

      {show && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog modal-dialog-centered">
            <div className="modal-content shadow-lg border-danger">
              <div className="modal-header bg-danger-subtle text-danger">
                <h5 className="modal-title">
                  <i className="bi bi-shield-lock-fill me-2"></i>
                  সংবেদনশীল তথ্য উন্মোচন (PII Decryption)
                </h5>
                <button type="button" className="btn-close" onClick={handleClose}></button>
              </div>
              <div className="modal-body">
                <div className="alert alert-warning py-2 mb-3">
                  <small>
                    <i className="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>সতর্কবার্তা:</strong> এই তথ্য উন্মোচন করার প্রতিটি ঘটনা সার্ভার অডিট ট্রেইলে (Audit Log)
                    আপনার ইউজার আইডি ও আইপি এড্রেস সহ স্থায়ীভাবে সংরক্ষিত হবে।
                  </small>
                </div>

                {error && <div className="alert alert-danger py-2">{error}</div>}

                {unmaskedData ? (
                  <div className="bg-light p-3 rounded border">
                    <div className="mb-2">
                      <small className="text-muted d-block">উন্মোচিত জাতীয় পরিচয়পত্র (Unmasked NID):</small>
                      <strong className="fs-5 text-dark font-monospace">{unmaskedData.unmasked_nid}</strong>
                    </div>
                    {unmaskedData.unmasked_mobile && (
                      <div className="mb-2">
                        <small className="text-muted d-block">মোবাইল নম্বর (Unmasked Mobile):</small>
                        <strong className="fs-6 text-dark font-monospace">{unmaskedData.unmasked_mobile}</strong>
                      </div>
                    )}
                    <div className="mt-3 text-success">
                      <small>
                        <i className="bi bi-check2-circle me-1"></i>
                        {unmaskedData.warning}
                      </small>
                    </div>
                  </div>
                ) : (
                  <div>
                    <p className="mb-2 text-muted">
                      বর্তমান মাস্কড তথ্য: এনআইডি: <code className="fs-6">{maskedNid || '••••••••••••'}</code>
                      {maskedMobile && (
                        <span>, মোবাইল: <code className="fs-6">{maskedMobile}</code></span>
                      )}
                    </p>
                    <p className="small text-secondary mb-3">
                      আপনি কি এই নাগরিকের সম্পূর্ণ জাতীয় পরিচয়পত্র ও ব্যক্তিগত ফোন নম্বর দেখতে চান?
                    </p>
                  </div>
                )}
              </div>
              <div className="modal-footer">
                {!unmaskedData ? (
                  <>
                    <button type="button" className="btn btn-secondary btn-sm" onClick={handleClose}>
                      বাতিল করুন
                    </button>
                    <button
                      type="button"
                      className="btn btn-danger btn-sm"
                      onClick={handleReveal}
                      disabled={loading}
                    >
                      {loading ? (
                        <>
                          <span className="spinner-border spinner-border-sm me-1"></span>
                          উন্মোচিত হচ্ছে...
                        </>
                      ) : (
                        'অনুমোদন দিয়ে দেখুন (Decrypt)'
                      )}
                    </button>
                  </>
                ) : (
                  <button type="button" className="btn btn-primary btn-sm" onClick={handleClose}>
                    সম্পন্ন
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
};

export default PiiRevealModal;
