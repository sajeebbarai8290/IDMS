import React from 'react';
import { Link } from 'react-router-dom';

export const UnauthorizedPage = () => {
  return (
    <div className="container min-vh-100 d-flex align-items-center justify-content-center text-center">
      <div className="card shadow-sm border-0 p-5 rounded-4" style={{ maxWidth: 500 }}>
        <div className="mb-3 text-danger">
          <i className="bi bi-shield-x" style={{ fontSize: '4rem' }}></i>
        </div>
        <h3 className="fw-bold text-secondary mb-1">অনুমতিহীন প্রবেশ (403)</h3>
        <p className="text-muted small mb-3">Access Denied / Insufficient Jurisdiction Scope</p>
        <p className="text-secondary small mb-4">
          আপনার বর্তমান ভূমিকা বা প্রশাসনিক অধিক্ষেত্রের এই তথ্য দেখার বৈধ অনুমোদন নেই। বিস্তারিত অনুসন্ধানের জন্য বিভাগীয় প্রধান বা সিস্টেম অ্যাডমিনের সাথে যোগাযোগ করুন।
        </p>
        <Link to="/dashboard" className="btn btn-success d-inline-flex align-items-center gap-2 justify-content-center">
          <i className="bi bi-house-door"></i>
          <span>ড্যাশবোর্ডে ফিরে যান</span>
        </Link>
      </div>
    </div>
  );
};

export default UnauthorizedPage;
