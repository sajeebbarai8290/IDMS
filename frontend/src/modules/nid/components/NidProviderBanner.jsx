import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';

export const NidProviderBanner = ({ provider }) => {
  const { t } = useLanguage();

  if (!provider) return null;

  if (provider.is_mock) {
    return (
      <div className="alert alert-warning d-flex align-items-start gap-3 shadow-sm mb-3" role="alert">
        <i className="bi bi-exclamation-triangle-fill fs-4 text-warning-emphasis mt-1"></i>
        <div className="small">
          <div className="fw-bold">
            {t(
              'ডেভেলপমেন্ট মোড সক্রিয়: মক এনআইডি অ্যাডাপ্টার (Mock NID Adapter)',
              'Development Mode Active: Mock NID Adapter'
            )}
          </div>
          <div>
            {t(
              'এই পরিবেশে যাচাই ফলাফল সম্পূর্ণ সিমুলেটেড। এটি বাংলাদেশ নির্বাচন কমিশন বা পরিচয় (Porichoy) গেটওয়ের প্রকৃত সরকারি যাচাই নয়। অনুমোদিত সরকারি API ডকুমেন্টেশন ও ক্রেডেনশিয়াল যুক্ত না হওয়া পর্যন্ত মক ফলাফলকে সরকারি প্রমাণ হিসেবে গণ্য করা যাবে না।',
              'Verification results in this environment are simulated. They are NOT genuine Bangladesh Election Commission / Porichoy verifications until official API documentation, authorization, and credentials are configured.'
            )}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div
      className={`alert ${provider.available ? 'alert-success' : 'alert-danger'} d-flex align-items-start gap-3 shadow-sm mb-3`}
      role="alert"
    >
      <i className={`bi ${provider.available ? 'bi-shield-check' : 'bi-shield-x'} fs-4 mt-1`}></i>
      <div className="small">
        <div className="fw-bold">
          {provider.provider_code} — {provider.available ? t('সক্রিয় ও প্রস্তুত', 'Available') : t('কনফিগার করা হয়নি / অনুপলব্ধ', 'Unavailable / Not Configured')}
        </div>
        <div>{provider.message}</div>
      </div>
    </div>
  );
};

export default NidProviderBanner;
