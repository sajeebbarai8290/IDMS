import React from 'react';
import { Link } from 'react-router-dom';

export const NotFoundPage = () => {
  return (
    <div className="container min-vh-100 d-flex align-items-center justify-content-center text-center">
      <div className="card shadow-sm border-0 p-5 rounded-4" style={{ maxWidth: 500 }}>
        <div className="mb-3 text-warning">
          <i className="bi bi-compass" style={{ fontSize: '4rem' }}></i>
        </div>
        <h3 className="fw-bold text-secondary mb-1">পৃষ্ঠাটি পাওয়া যায়নি (404)</h3>
        <p className="text-muted small mb-3">Resource / Page Not Found</p>
        <p className="text-secondary small mb-4">
          আপনার অনুরোধকৃত পৃষ্ঠাটি স্থানান্তর করা হয়েছে বা বিদ্যমান নেই।
        </p>
        <Link to="/dashboard" className="btn btn-primary d-inline-flex align-items-center gap-2 justify-content-center">
          <i className="bi bi-arrow-left"></i>
          <span>প্রধান পৃষ্ঠায় ফিরুন</span>
        </Link>
      </div>
    </div>
  );
};

export default NotFoundPage;
