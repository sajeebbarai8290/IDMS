import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { userManagementApi } from '../../../api/userManagementApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  AccountStatusBadge,
  IdpSyncBadge,
  MfaStatusBadge,
} from '../components/UserStatusBadges';
import {
  ACCOUNT_STATUS_META,
  canChangeUserStatus,
  canCreateUsers,
  canUpdateUsers,
  formatUserApiError,
  TIER_LEVEL_META,
} from '../utils/userPermissions';

export const UserListPage = () => {
  const { user: currentUser } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const [filters, setFilters] = useState({
    search: '',
    account_status: '',
    role_code: '',
    organization_id: '',
    tier_level: '',
    idp_sync_status: '',
    page: 1,
  });
  const [feedback, setFeedback] = useState(null);
  const [statusTarget, setStatusTarget] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['user-management', 'lookups'],
    queryFn: () => userManagementApi.getLookups(),
  });

  const cleanFilters = Object.fromEntries(
    Object.entries(filters).filter(([, v]) => v !== '' && v !== null)
  );

  const { data: usersRes, isLoading, error } = useQuery({
    queryKey: ['user-management', 'users', cleanFilters],
    queryFn: () => userManagementApi.getUsers(cleanFilters),
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status, reason }) =>
      userManagementApi.changeUserStatus(id, { account_status: status, reason }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setStatusTarget(null);
      setFeedback({
        variant: 'success',
        message: t('ব্যবহারকারীর অ্যাকাউন্ট অবস্থা হালনাগাদ করা হয়েছে।', 'User account status updated.'),
      });
    },
    onError: (err) => {
      setStatusTarget(null);
      setFeedback({
        variant: 'danger',
        message: formatUserApiError(err, t('অবস্থা পরিবর্তন ব্যর্থ।', 'Status change failed.')),
      });
    },
  });

  const users = usersRes?.data || [];
  const meta = usersRes?.meta || {};
  const lookups = lookupsRes?.data || {};
  const roles = lookups.roles || [];
  const organizations = lookups.organizations || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold mb-0">{t('ব্যবহারকারী তালিকা (User Directory)', 'User Directory')}</h4>
          <small className="text-muted">
            {t(
              'সিস্টেম ব্যবহারকারী, সাংগঠনিক অবস্থান, ভূমিকা ও কীক্লক অবস্থা পরিচালনা',
              'Manage officers, organizational assignments, system roles, and Keycloak identity status'
            )}
          </small>
        </div>
        <div className="d-flex gap-2">
          {canCreateUsers(currentUser) && (
            <Link to="/user-management/users/new" className="btn btn-primary btn-sm">
              <i className="bi bi-person-plus-fill me-1"></i>
              {t('নতুন ব্যবহারকারী যোগ করুন', 'Provision User')}
            </Link>
          )}
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {/* Search & Filters */}
      <div className="card shadow-sm mb-3">
        <div className="card-body py-2">
          <div className="row g-2">
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('নাম, ইমেইল, আইডি বা পদবি খুঁজুন...', 'Search name, email, employee ID...')}
                value={filters.search}
                onChange={(e) => setFilters({ ...filters, search: e.target.value, page: 1 })}
              />
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.account_status}
                onChange={(e) => setFilters({ ...filters, account_status: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
                {(lookups.account_statuses || ['PENDING', 'ACTIVE', 'SUSPENDED', 'DEACTIVATED', 'LOCKED']).map((s) => (
                  <option key={s} value={s}>
                    {ACCOUNT_STATUS_META[s] ? (lang === 'bn' ? ACCOUNT_STATUS_META[s].bn : ACCOUNT_STATUS_META[s].en) : s}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.role_code}
                onChange={(e) => setFilters({ ...filters, role_code: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল ভূমিকা', 'All Roles')}</option>
                {roles.map((r) => (
                  <option key={r.id} value={r.code}>
                    {lang === 'bn' ? (r.name_bn || r.code) : r.name_en}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-3">
              <select
                className="form-select form-select-sm"
                value={filters.organization_id}
                onChange={(e) => setFilters({ ...filters, organization_id: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল সংগঠন', 'All Organizations')}</option>
                {organizations.map((o) => (
                  <option key={o.id} value={o.id}>
                    {lang === 'bn' ? (o.name_bn || o.name_en) : o.name_en}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-2">
              <select
                className="form-select form-select-sm"
                value={filters.tier_level}
                onChange={(e) => setFilters({ ...filters, tier_level: e.target.value, page: 1 })}
              >
                <option value="">{t('সকল স্তর', 'All Geo Tiers')}</option>
                {(lookups.tier_levels || []).map((tier) => (
                  <option key={tier} value={tier}>
                    {TIER_LEVEL_META[tier] ? (lang === 'bn' ? TIER_LEVEL_META[tier].bn : TIER_LEVEL_META[tier].en) : tier}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader message="ব্যবহারকারী তালিকা লোড হচ্ছে..." messageEn="Loading users..." />
      ) : error ? (
        <AlertBanner
          variant="danger"
          message={formatUserApiError(error, t('তালিকা লোড ব্যর্থ।', 'Failed to load users.'))}
        />
      ) : users.length === 0 ? (
        <EmptyState message={t('কোনো ব্যবহারকারী পাওয়া যায়নি।', 'No matching user accounts found.')} />
      ) : (
        <div className="card shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('কর্মকর্তা / ব্যবহারকারী', 'Officer / User')}</th>
                  <th>{t('সংগঠন ও পদবি', 'Organization & Designation')}</th>
                  <th>{t('ভূমিকা', 'Role')}</th>
                  <th>{t('অধিক্ষেত্র', 'Jurisdiction')}</th>
                  <th>{t('অবস্থা ও নিরাপত্তা', 'Status & Security')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {users.map((u) => (
                  <tr key={u.id}>
                    <td>
                      <div className="fw-semibold">
                        <Link to={`/user-management/users/${u.id}`} className="text-decoration-none">
                          {lang === 'bn' ? (u.full_name_bn || u.full_name_en) : u.full_name_en}
                        </Link>
                      </div>
                      <div className="small text-muted">
                        {u.email}
                        {u.employee_id && <span className="ms-2 badge bg-light text-dark border">{u.employee_id}</span>}
                      </div>
                    </td>
                    <td className="small">
                      <div>{lang === 'bn' ? (u.organization?.name_bn || u.organization?.name_en || '-') : (u.organization?.name_en || '-')}</div>
                      <div className="text-muted">
                        {lang === 'bn' ? (u.designation_bn || u.designation || '-') : (u.designation || '-')}
                      </div>
                    </td>
                    <td>
                      <span className="badge bg-primary">{u.role_code}</span>
                      {Array.isArray(u.roles) && u.roles.length > 1 && (
                        <span className="badge bg-light text-dark border ms-1">+{u.roles.length - 1}</span>
                      )}
                    </td>
                    <td className="small">
                      {u.administrative_unit ? (
                        <>
                          <div>{lang === 'bn' ? u.administrative_unit.name_bn : u.administrative_unit.name_en}</div>
                          <span className="text-muted">{u.administrative_unit.tier_level}</span>
                        </>
                      ) : (
                        <span className="text-muted">-</span>
                      )}
                    </td>
                    <td>
                      <div className="d-flex flex-wrap gap-1">
                        <AccountStatusBadge status={u.account_status} />
                        <IdpSyncBadge status={u.idp_sync_status} />
                        <MfaStatusBadge enforced={u.mfa_enforced} verified={u.mfa_verified} />
                      </div>
                    </td>
                    <td className="text-end text-nowrap">
                      <Link to={`/user-management/users/${u.id}`} className="btn btn-outline-primary btn-sm me-1">
                        <i className="bi bi-eye"></i>
                      </Link>
                      {canUpdateUsers(currentUser) && (
                        <Link to={`/user-management/users/${u.id}/edit`} className="btn btn-outline-secondary btn-sm me-1">
                          <i className="bi bi-pencil"></i>
                        </Link>
                      )}
                      {canChangeUserStatus(currentUser) && u.id !== currentUser?.id && (
                        <button
                          type="button"
                          className={`btn btn-sm ${u.account_status === 'ACTIVE' ? 'btn-outline-warning' : 'btn-outline-success'}`}
                          onClick={() =>
                            setStatusTarget({
                              user: u,
                              nextStatus: u.account_status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE',
                            })
                          }
                          title={u.account_status === 'ACTIVE' ? t('স্থগিত করুন', 'Suspend') : t('সক্রিয় করুন', 'Activate')}
                        >
                          <i className={`bi ${u.account_status === 'ACTIVE' ? 'bi-pause-circle' : 'bi-check-circle'}`}></i>
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {meta.last_page > 1 && (
            <div className="card-footer bg-white d-flex justify-content-between align-items-center">
              <small className="text-muted">
                {t(`মোট ${meta.total} জন ব্যবহারকারী`, `Total ${meta.total} users`)}
              </small>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page <= 1}
                  onClick={() => setFilters({ ...filters, page: meta.current_page - 1 })}
                >
                  {t('পূর্ববর্তী', 'Previous')}
                </button>
                <span className="btn btn-light disabled">
                  {meta.current_page} / {meta.last_page}
                </span>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => setFilters({ ...filters, page: meta.current_page + 1 })}
                >
                  {t('পরবর্তী', 'Next')}
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      <ConfirmDialog
        isOpen={Boolean(statusTarget)}
        title={t('অ্যাকাউন্ট অবস্থা পরিবর্তন নিশ্চিতকরণ', 'Confirm Account Status Change')}
        message={
          statusTarget
            ? t(
                `আপনি কি "${statusTarget.user.full_name_en}" এর অ্যাকাউন্ট ${statusTarget.nextStatus} অবস্থায় পরিবর্তন করতে চান?`,
                `Change account status of "${statusTarget.user.full_name_en}" to ${statusTarget.nextStatus}?`
              )
            : ''
        }
        variant={statusTarget?.nextStatus === 'ACTIVE' ? 'success' : 'warning'}
        onConfirm={() =>
          statusMutation.mutate({
            id: statusTarget.user.id,
            status: statusTarget.nextStatus,
            reason: `Status changed via directory action to ${statusTarget.nextStatus}`,
          })
        }
        onCancel={() => setStatusTarget(null)}
      />
    </div>
  );
};

export default UserListPage;
