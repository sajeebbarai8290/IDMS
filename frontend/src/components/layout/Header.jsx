import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import Badge from '../common/Badge';

export const Header = ({ toggleSidebar }) => {
  const { user, isAuthenticated, logout } = useAuth();
  const [lang, setLang] = useState('bn');

  const scope = user?.administrativeScope;

  return (
    <header className="idms-header d-flex align-items-center justify-content-between px-3">
      <div className="d-flex align-items-center gap-3">
        <button
          className="btn btn-sm btn-outline-secondary d-md-none"
          onClick={toggleSidebar}
          aria-label="Toggle navigation"
        >
          <i className="bi bi-list fs-5"></i>
        </button>

        <Link to="/dashboard" className="text-decoration-none d-flex align-items-center gap-2">
          <div className="rounded-circle bg-success text-white d-flex align-items-center justify-content-center shadow-sm" style={{ width: 38, height: 38 }}>
            <i className="bi bi-shield-check fs-5"></i>
          </div>
          <div>
            <div className="idms-brand-title">আইডিএমএস | IDMS</div>
            <div className="idms-brand-subtitle d-none d-sm-block">দুর্যোগ ব্যবস্থাপনা ও ত্রাণ মন্ত্রণালয়</div>
          </div>
        </Link>
      </div>

      <div className="d-flex align-items-center gap-3">
        {/* Active Administrative Scope Indicator */}
        {isAuthenticated && scope && (
          <div className="d-none d-lg-flex align-items-center gap-2 px-3 py-1 bg-light border rounded">
            <i className="bi bi-geo-alt-fill text-danger"></i>
            <div className="small">
              <span className="text-muted">আওতাধীন এলাকা: </span>
              <strong className="text-secondary">{scope.name_bn || scope.name_en || 'জাতীয়'}</strong>
            </div>
            <Badge tier={scope.tier_level || 'NATIONAL'}>
              {scope.tier_level || 'NATIONAL'}
            </Badge>
          </div>
        )}

        {/* Language Selector */}
        <div className="btn-group btn-group-sm" role="group">
          <button
            type="button"
            className={`btn ${lang === 'bn' ? 'btn-success' : 'btn-outline-secondary'}`}
            onClick={() => setLang('bn')}
          >
            বাংলা
          </button>
          <button
            type="button"
            className={`btn ${lang === 'en' ? 'btn-success' : 'btn-outline-secondary'}`}
            onClick={() => setLang('en')}
          >
            EN
          </button>
        </div>

        {/* User Account / SSO Status */}
        {isAuthenticated ? (
          <div className="dropdown">
            <button
              className="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-2"
              type="button"
              id="userMenu"
              data-bs-toggle="dropdown"
              aria-expanded="false"
            >
              <i className="bi bi-person-circle fs-5 text-success"></i>
              <span className="d-none d-md-inline fw-semibold">{user?.name}</span>
            </button>
            <ul className="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userMenu">
              <li className="dropdown-header">
                <div className="fw-bold">{user?.name}</div>
                <small className="text-muted">{user?.email}</small>
                <div className="mt-1">
                  <span className="badge bg-primary">{user?.roles?.[0] || 'OFFICER'}</span>
                </div>
              </li>
              <li><hr className="dropdown-divider" /></li>
              <li>
                <Link className="dropdown-item d-flex align-items-center gap-2" to="/system/health">
                  <i className="bi bi-activity text-info"></i>
                  <span>সিস্টেম স্বাস্থ্য (Health)</span>
                </Link>
              </li>
              <li><hr className="dropdown-divider" /></li>
              <li>
                <button className="dropdown-item text-danger d-flex align-items-center gap-2" onClick={logout}>
                  <i className="bi bi-box-arrow-right"></i>
                  <span>লগআউট (Logout)</span>
                </button>
              </li>
            </ul>
          </div>
        ) : (
          <Link to="/login" className="btn btn-success btn-sm d-flex align-items-center gap-1">
            <i className="bi bi-key-fill"></i>
            <span>লগইন (SSO)</span>
          </Link>
        )}
      </div>
    </header>
  );
};

export default Header;
