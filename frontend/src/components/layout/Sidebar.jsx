import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

export const Sidebar = ({ isOpen }) => {
  const { user } = useAuth();
  const role = user?.roles?.[0] || 'GUEST';

  return (
    <aside className={`idms-sidebar ${isOpen ? 'show' : ''}`}>
      <div className="py-2 idms-sidebar-nav">
        <div className="idms-sidebar-section-title">প্রধান মেনু (Main)</div>
        <NavLink to="/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-speedometer2 me-2"></i>
          <span>ড্যাশবোর্ড (Dashboard)</span>
        </NavLink>
        <NavLink to="/system/health" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-heart-pulse-fill me-2 text-danger"></i>
          <span>সিস্টেম অবস্থা (Health)</span>
        </NavLink>

        <div className="idms-sidebar-section-title">দুর্যোগ ব্যবস্থাপনা (Disaster)</div>
        <div className="nav-link text-muted opacity-75 d-flex justify-content-between align-items-center">
          <span><i className="bi bi-people me-2"></i>উপকারভোগী ব্যবস্থাপনা</span>
          <span className="badge bg-light text-secondary border">M01</span>
        </div>
        <div className="nav-link text-muted opacity-75 d-flex justify-content-between align-items-center">
          <span><i className="bi bi-box-seam me-2"></i>ত্রাণ বিতরণ ব্যবস্থাপনা</span>
          <span className="badge bg-light text-secondary border">M04</span>
        </div>
        <div className="nav-link text-muted opacity-75 d-flex justify-content-between align-items-center">
          <span><i className="bi bi-house-door me-2"></i>আশ্রয়কেন্দ্র অপারেশন</span>
          <span className="badge bg-light text-secondary border">M05</span>
        </div>
        <div className="nav-link text-muted opacity-75 d-flex justify-content-between align-items-center">
          <span><i className="bi bi-clipboard2-data me-2"></i>ক্ষয়ক্ষতি নিরূপণ (D-Form)</span>
          <span className="badge bg-light text-secondary border">M09</span>
        </div>

        <div className="idms-sidebar-section-title">লজিস্টিকস ও নিরাপত্তা</div>
        <div className="nav-link text-muted opacity-75 d-flex justify-content-between align-items-center">
          <span><i className="bi bi-building me-2"></i>গুদাম ও মজুদ (Warehouse)</span>
          <span className="badge bg-light text-secondary border">M08</span>
        </div>
        <div className="nav-link text-muted opacity-75 d-flex justify-content-between align-items-center">
          <span><i className="bi bi-shield-lock me-2"></i>নিরাপত্তা ও অডিট ট্রেইল</span>
          <span className="badge bg-light text-secondary border">M16</span>
        </div>

        <div className="mt-4 px-3">
          <div className="card bg-light border-0 p-2 text-center rounded">
            <small className="text-muted d-block mb-1">বর্তমান ভূমিকা (Role)</small>
            <span className="badge bg-secondary text-wrap">{role}</span>
          </div>
        </div>
      </div>
    </aside>
  );
};

export default Sidebar;
