import React from 'react';

export const Footer = () => {
  return (
    <footer className="idms-footer">
      <div className="container-fluid px-0">
        <div className="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
          <div>
            &copy; {new Date().getFullYear()} গণপ্রজাতন্ত্রী বাংলাদেশ সরকার | দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয় (MoDMR)
          </div>
          <div className="d-flex align-items-center gap-3">
            <span className="text-danger fw-semibold">
              <i className="bi bi-telephone-fill me-1"></i>দুর্যোগ জরুরি বার্তা: ১০৯০ / ৯৯৯
            </span>
            <span className="text-muted">|</span>
            <span>ভার্সন: ১.০.০-প্রস্তাবিত (BNDA মানসম্মত)</span>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
