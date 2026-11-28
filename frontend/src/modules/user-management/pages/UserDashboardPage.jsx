import React from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { userManagementApi } from '../../../api/userManagementApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { TierLevelLabel } from '../components/UserStatusBadges';
import {
  canCreateUsers,
  canManageRoleConfig,
  formatUserApiError,
} from '../utils/userPermissions';

export const UserDashboardPage = () => {
  const { user } = useAuth();
  const { t, lang } = useLanguage();

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['user-management', 'dashboard'],
    queryFn: () => userManagementApi.getDashboard(),
  });

  if (isLoading) {
    return <Loader message="ব্যবহারকারী ড্যাশবোর্ড লোড হচ্ছে..." messageEn="Loading user management dashboard..." />;
  }

  if (error) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner
          variant="danger"
          message={formatUserApiError(error, t('ড্যাশবোর্ড লোড করতে ব্যর্থ।', 'Failed to load user dashboard.'))}
        />
      </div>
    );
  }

  const data = dashRes?.data || {};
  const kpis = data.kpis || {};
  const byOrg = data.by_organization || [];
  const byGeo = data.by_geographical_level || [];
  const byRole = data.role_distribution || [];
  const kcHealth = data.keycloak_health || {};
  const recentActivity = data.recent_activity || [];

  const totalUsers = kpis.total_users || 0;
  const maxOrg = Math.max(1, ...byOrg.map((o) => o.total || 0));
  const maxGeo = Math.max(1, ...byGeo.map((g) => g.total || 0));
  const maxRole = Math.max(1, ...byRole.map((r) => r.total || 0));

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-people-fill text-primary me-2"></i>
            {t('ব্যবহারকারী ও প্রবেশাধিকার ড্যাশবোর্ড (M12)', 'User & Access Management Dashboard (M12)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'কীক্লক আইডেন্টিটি সিঙ্ক, ৭-স্তরীয় সাংগঠনিক অধিক্ষেত্র এবং ভূমিকা-ভিত্তিক প্রবেশাধিকার পর্যবেক্ষণ',
              'Keycloak IAM federation, 7-tier organizational scoping, and RBAC governance overview'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <Link to="/user-management/users" className="btn btn-outline-primary btn-sm">
            <i className="bi bi-person-lines-fill me-1"></i>
            {t('ব্যবহারকারী তালিকা', 'User Directory')}
          </Link>
          {canCreateUsers(user) && (
            <Link to="/user-management/users/new" className="btn btn-primary btn-sm">
              <i className="bi bi-person-plus-fill me-1"></i>
              {t('নতুন ব্যবহারকারী', 'Provision User')}
            </Link>
          )}
          <Link to="/user-management/roles" className="btn btn-outline-secondary btn-sm">
            <i className="bi bi-shield-lock-fill me-1"></i>
            {canManageRoleConfig(user)
              ? t('ভূমিকা ও অনুমতি ম্যাট্রিক্স', 'Roles & Permissions')
              : t('ভূমিকা তালিকা', 'View Roles')}
          </Link>
        </div>
      </div>

      {/* Primary KPI Cards */}
      <div className="row g-3 mb-3">
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('মোট ব্যবহারকারী', 'Total Users')}</div>
              <div className="fs-3 fw-bold text-primary">{kpis.total_users ?? 0}</div>
              <div className="small text-muted">{t('নিবন্ধিত প্রোফাইল', 'Registered profiles')}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('সক্রিয় ব্যবহারকারী', 'Active Users')}</div>
              <div className="fs-3 fw-bold text-success">{kpis.active_users ?? 0}</div>
              <div className="small text-success">
                {totalUsers > 0 ? `${Math.round(((kpis.active_users ?? 0) / totalUsers) * 100)}%` : '0%'}
              </div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('নিষ্ক্রিয় / সীমাবদ্ধ', 'Inactive / Restricted')}</div>
              <div className="fs-3 fw-bold text-danger">{kpis.inactive_users ?? 0}</div>
              <div className="small text-muted">
                {t(`স্থগিত: ${kpis.suspended_users ?? 0}, লক: ${kpis.locked_users ?? 0}`, `Susp: ${kpis.suspended_users ?? 0}, Lock: ${kpis.locked_users ?? 0}`)}
              </div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('অপেক্ষমাণ (Pending)', 'Pending Users')}</div>
              <div className="fs-3 fw-bold text-warning">{kpis.pending_users ?? 0}</div>
              <div className="small text-muted">{t('সক্রিয়করণের অপেক্ষায়', 'Awaiting activation')}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('এমএফএ বাধ্যতামূলক', 'MFA Enforced')}</div>
              <div className="fs-3 fw-bold text-info">{kpis.mfa_enforced_users ?? 0}</div>
              <div className="small text-muted">
                {t(`যাচাইকৃত: ${kpis.mfa_verified_users ?? 0}`, `Verified: ${kpis.mfa_verified_users ?? 0}`)}
              </div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-body">
              <div className="text-muted small">{t('কীক্লক আইডিপি', 'Keycloak IdP')}</div>
              <div className="fs-6 fw-bold mt-1">
                <span className={`badge bg-${kcHealth.reachable ? 'success' : 'warning text-dark'}`}>
                  {kcHealth.status || 'UNKNOWN'}
                </span>
              </div>
              <div className="small text-muted mt-1">
                {t(`সংযুক্ত: ${kpis.idp_linked_users ?? 0}`, `Linked: ${kpis.idp_linked_users ?? 0}`)}
                {(kpis.idp_failed_users ?? 0) > 0 && (
                  <span className="text-danger ms-1">({t('ব্যর্থ', 'Fail')}: {kpis.idp_failed_users})</span>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Distribution Breakdowns */}
      <div className="row g-3 mb-3">
        <div className="col-lg-4">
          <div className="card shadow-sm h-100">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span><i className="bi bi-building me-2 text-primary"></i>{t('সংগঠন অনুযায়ী ব্যবহারকারী', 'Users by Organization')}</span>
              <Link to="/user-management/organizations" className="small text-decoration-none">{t('সব দেখুন', 'Manage')}</Link>
            </div>
            <div className="card-body">
              {byOrg.length === 0 ? (
                <EmptyState message={t('কোনো তথ্য নেই', 'No organization data')} />
              ) : (
                byOrg.slice(0, 7).map((item, idx) => (
                  <div key={item.organization_id || idx} className="mb-2">
                    <div className="d-flex justify-content-between small">
                      <span className="text-truncate me-2" title={lang === 'bn' ? item.name_bn : item.name_en}>
                        {lang === 'bn' ? (item.name_bn || item.name_en) : item.name_en}
                      </span>
                      <span className="fw-semibold">{item.active}/{item.total}</span>
                    </div>
                    <div className="progress" style={{ height: 6 }}>
                      <div
                        className="progress-bar bg-primary"
                        style={{ width: `${Math.round((item.total / maxOrg) * 100)}%` }}
                      />
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card shadow-sm h-100">
            <div className="card-header bg-white fw-semibold">
              <i className="bi bi-geo-alt-fill me-2 text-success"></i>
              {t('ভৌগোলিক স্তর অনুযায়ী ব্যবহারকারী', 'Users by Geographical Level')}
            </div>
            <div className="card-body">
              {byGeo.length === 0 ? (
                <EmptyState message={t('কোনো তথ্য নেই', 'No geographical data')} />
              ) : (
                byGeo.map((item) => (
                  <div key={item.tier_level} className="mb-2">
                    <div className="d-flex justify-content-between small">
                      <TierLevelLabel tier={item.tier_level} />
                      <span className="fw-semibold">{item.active}/{item.total}</span>
                    </div>
                    <div className="progress" style={{ height: 6 }}>
                      <div
                        className="progress-bar bg-success"
                        style={{ width: `${Math.round((item.total / maxGeo) * 100)}%` }}
                      />
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>

        <div className="col-lg-4">
          <div className="card shadow-sm h-100">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span><i className="bi bi-person-badge-fill me-2 text-info"></i>{t('ভূমিকা বিন্যাস', 'Role Distribution')}</span>
              <span className="badge bg-light text-dark border">{kpis.total_roles ?? 0} {t('ভূমিকা', 'Roles')}</span>
            </div>
            <div className="card-body">
              {byRole.length === 0 ? (
                <EmptyState message={t('কোনো তথ্য নেই', 'No role data')} />
              ) : (
                byRole.slice(0, 8).map((item) => (
                  <div key={item.role_code} className="mb-2">
                    <div className="d-flex justify-content-between small">
                      <span className="text-truncate me-2">
                        {lang === 'bn' ? (item.name_bn || item.role_code) : (item.name_en || item.role_code)}
                      </span>
                      <span className="fw-semibold">{item.total}</span>
                    </div>
                    <div className="progress" style={{ height: 6 }}>
                      <div
                        className="progress-bar bg-info"
                        style={{ width: `${Math.round((item.total / maxRole) * 100)}%` }}
                      />
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Recent Audit Activity */}
      <div className="card shadow-sm">
        <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span>
            <i className="bi bi-journal-check me-2 text-secondary"></i>
            {t('সাম্প্রতিক ব্যবহারকারী ও নিরাপত্তা অডিট লগ', 'Recent User & RBAC Audit Activity')}
          </span>
        </div>
        <div className="table-responsive">
          <table className="table table-sm table-hover mb-0 align-middle">
            <thead className="table-light">
              <tr>
                <th>{t('কার্যক্রম', 'Action')}</th>
                <th>{t('লক্ষ্য সত্তা', 'Target')}</th>
                <th>{t('সম্পাদনকারী', 'Actor')}</th>
                <th>{t('সময়', 'Timestamp')}</th>
              </tr>
            </thead>
            <tbody>
              {recentActivity.length === 0 ? (
                <tr>
                  <td colSpan={4} className="text-center text-muted py-3">
                    {t('কোনো সাম্প্রতিক অডিট লগ নেই।', 'No recent user management audit events.')}
                  </td>
                </tr>
              ) : (
                recentActivity.map((log) => (
                  <tr key={log.id}>
                    <td><span className="badge bg-light text-dark border">{log.action_name}</span></td>
                    <td className="small">{log.auditable_type} ({String(log.auditable_id || '').slice(0, 8)})</td>
                    <td className="small">{log.actor_name}</td>
                    <td className="small text-muted">{log.created_at ? new Date(log.created_at).toLocaleString() : '-'}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default UserDashboardPage;
