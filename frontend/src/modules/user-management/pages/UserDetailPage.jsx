import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { userManagementApi } from '../../../api/userManagementApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  AccountStatusBadge,
  IdpSyncBadge,
  MfaStatusBadge,
  ScopeAssignmentBadge,
} from '../components/UserStatusBadges';
import {
  canAssignUserRoles,
  canAssignUserScopes,
  canChangeUserStatus,
  canDeleteUsers,
  canSyncKeycloak,
  canUpdateUsers,
  canViewUserActivity,
  formatUserApiError,
  SCOPE_ASSIGNMENT_META,
} from '../utils/userPermissions';

export const UserDetailPage = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user: currentUser } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const [feedback, setFeedback] = useState(null);
  const [photoUrl, setPhotoUrl] = useState(null);
  const [statusReason, setStatusReason] = useState('');
  const [editedRoles, setEditedRoles] = useState(null);
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [scopeForm, setScopeForm] = useState({
    administrative_unit_id: '',
    assignment_type: 'ADDITIONAL_CHARGE',
    valid_from: '',
    valid_until: '',
    official_order_ref: '',
    notes: '',
  });

  const { data: userRes, isLoading, error } = useQuery({
    queryKey: ['user-management', 'user', id],
    queryFn: () => userManagementApi.getUser(id),
  });

  const { data: lookupsRes } = useQuery({
    queryKey: ['user-management', 'lookups'],
    queryFn: () => userManagementApi.getLookups(),
  });

  const { data: activityRes } = useQuery({
    queryKey: ['user-management', 'user-activity', id],
    queryFn: () => userManagementApi.getUserActivity(id, { per_page: 15 }),
    enabled: canViewUserActivity(currentUser) || currentUser?.id === id,
  });

  const profile = userRes?.data;
  const selectedRoles =
    editedRoles ??
    (profile?.roles?.length
      ? profile.roles.map((r) => r.code)
      : profile?.role_code
        ? [profile.role_code]
        : []);

  useEffect(() => {
    let activeUrl = null;
    if (!profile?.has_photo) {
      return undefined;
    }
    userManagementApi
      .downloadPhoto(profile.id)
      .then((blob) => {
        activeUrl = URL.createObjectURL(blob);
        setPhotoUrl(activeUrl);
      })
      .catch(() => setPhotoUrl(null));
    return () => {
      if (activeUrl) URL.revokeObjectURL(activeUrl);
    };
  }, [profile?.id, profile?.has_photo]);

  const statusMutation = useMutation({
    mutationFn: (account_status) =>
      userManagementApi.changeUserStatus(id, { account_status, reason: statusReason || `Changed to ${account_status}` }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setStatusReason('');
      setFeedback({ variant: 'success', message: t('অ্যাকাউন্ট অবস্থা সফলভাবে পরিবর্তিত হয়েছে।', 'Account status updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to change status.') }),
  });

  const keycloakSyncMutation = useMutation({
    mutationFn: (payload) => userManagementApi.syncKeycloak(id, payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setFeedback({
        variant: res?.success ? 'success' : 'warning',
        message: res?.success
          ? t('কীক্লক আইডেন্টিটি সিঙ্ক সম্পন্ন হয়েছে।', 'Keycloak identity synchronized.')
          : (res?.sync_result?.error_message || t('কীক্লক সিঙ্ক ব্যর্থ।', 'Keycloak sync failed.')),
      });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Keycloak sync failed.') }),
  });

  const rolesMutation = useMutation({
    mutationFn: (role_codes) => userManagementApi.assignUserRoles(id, { role_codes, role_code: role_codes[0] }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setFeedback({ variant: 'success', message: t('ভূমিকা সফলভাবে হালনাগাদ করা হয়েছে।', 'User roles updated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to assign roles.') }),
  });

  const assignScopeMutation = useMutation({
    mutationFn: (payload) => userManagementApi.assignUserScope(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setScopeForm({
        administrative_unit_id: '',
        assignment_type: 'ADDITIONAL_CHARGE',
        valid_from: '',
        valid_until: '',
        official_order_ref: '',
        notes: '',
      });
      setFeedback({ variant: 'success', message: t('নতুন অধিক্ষেত্র দায়িত্ব যুক্ত করা হয়েছে।', 'Administrative scope assigned.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to assign scope.') }),
  });

  const revokeScopeMutation = useMutation({
    mutationFn: (scopeId) => userManagementApi.revokeUserScope(id, scopeId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setFeedback({ variant: 'success', message: t('অধিক্ষেত্র দায়িত্ব প্রত্যাহার করা হয়েছে।', 'Scope assignment revoked.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to revoke scope.') }),
  });

  const uploadPhotoMutation = useMutation({
    mutationFn: (file) => {
      const fd = new FormData();
      fd.append('photo', file);
      return userManagementApi.uploadPhoto(id, fd);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setFeedback({ variant: 'success', message: t('প্রোফাইল ছবি আপলোড হয়েছে।', 'Profile photo uploaded.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Photo upload failed.') }),
  });

  const archiveMutation = useMutation({
    mutationFn: () => userManagementApi.deleteUser(id, statusReason || 'Archived via User Management detail view.'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      navigate('/user-management/users');
    },
    onError: (err) => {
      setConfirmArchive(false);
      setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to archive user.') });
    },
  });

  if (isLoading) {
    return <Loader message="ব্যবহারকারীর বিবরণ লোড হচ্ছে..." messageEn="Loading user profile..." />;
  }

  if (error || !profile) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner variant="danger" message={formatUserApiError(error, t('ব্যবহারকারী পাওয়া যায়নি।', 'User not found.'))} />
      </div>
    );
  }

  const lookups = lookupsRes?.data || {};
  const allRoles = lookups.roles || [];
  const adminUnits = lookups.administrative_units || [];
  const activityLogs = activityRes?.data || [];
  const isSelf = currentUser?.id === profile.id;

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-0">{lang === 'bn' ? (profile.full_name_bn || profile.full_name_en) : profile.full_name_en}</h4>
          <small className="text-muted">{profile.email} {profile.employee_id ? `• ${profile.employee_id}` : ''}</small>
        </div>
        <div className="d-flex gap-2">
          <Link to="/user-management/users" className="btn btn-outline-secondary btn-sm">
            <i className="bi bi-arrow-left me-1"></i>{t('তালিকা', 'Directory')}
          </Link>
          {canUpdateUsers(currentUser) && (
            <Link to={`/user-management/users/${profile.id}/edit`} className="btn btn-primary btn-sm">
              <i className="bi bi-pencil me-1"></i>{t('সম্পাদনা', 'Edit Profile')}
            </Link>
          )}
          {canDeleteUsers(currentUser) && !isSelf && (
            <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => setConfirmArchive(true)}>
              <i className="bi bi-archive me-1"></i>{t('আর্কাইভ', 'Archive')}
            </button>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <div className="row g-3">
        {/* Left column: Profile & Keycloak/Lifecycle */}
        <div className="col-lg-4">
          <div className="card shadow-sm mb-3">
            <div className="card-body text-center">
              {photoUrl ? (
                <img
                  src={photoUrl}
                  alt={profile.full_name_en}
                  className="rounded-circle border mb-2"
                  style={{ width: 96, height: 96, objectFit: 'cover' }}
                />
              ) : (
                <div
                  className="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center mb-2 text-secondary"
                  style={{ width: 96, height: 96 }}
                >
                  <i className="bi bi-person-fill fs-1"></i>
                </div>
              )}
              <h5 className="fw-bold mb-0">{profile.full_name_en}</h5>
              {profile.full_name_bn && <div className="text-muted small">{profile.full_name_bn}</div>}
              <div className="mt-2 d-flex justify-content-center flex-wrap gap-1">
                <AccountStatusBadge status={profile.account_status} />
                <IdpSyncBadge status={profile.idp_sync_status} />
                <MfaStatusBadge enforced={profile.mfa_enforced} verified={profile.mfa_verified} />
              </div>

              {canUpdateUsers(currentUser) && (
                <div className="mt-3">
                  <label className="btn btn-outline-secondary btn-sm mb-0">
                    <i className="bi bi-camera me-1"></i>
                    {t('ছবি পরিবর্তন', 'Upload Photo')}
                    <input
                      type="file"
                      className="d-none"
                      accept="image/jpeg,image/png,image/webp"
                      onChange={(e) => e.target.files?.[0] && uploadPhotoMutation.mutate(e.target.files[0])}
                    />
                  </label>
                </div>
              )}
            </div>
            <ul className="list-group list-group-flush small">
              <li className="list-group-item d-flex justify-content-between">
                <span className="text-muted">{t('পদবি', 'Designation')}</span>
                <span className="fw-semibold">{lang === 'bn' ? (profile.designation_bn || profile.designation || '-') : (profile.designation || '-')}</span>
              </li>
              <li className="list-group-item d-flex justify-content-between">
                <span className="text-muted">{t('সংগঠন', 'Organization')}</span>
                <span className="fw-semibold text-end">{lang === 'bn' ? (profile.organization?.name_bn || profile.organization?.name_en || '-') : (profile.organization?.name_en || '-')}</span>
              </li>
              <li className="list-group-item d-flex justify-content-between">
                <span className="text-muted">{t('বিভাগ / ইউনিট', 'Department')}</span>
                <span>{lang === 'bn' ? (profile.department_bn || profile.department_en || '-') : (profile.department_en || '-')}</span>
              </li>
              <li className="list-group-item d-flex justify-content-between">
                <span className="text-muted">{t('মোবাইল', 'Mobile')}</span>
                <span>{profile.mobile_number || '-'}</span>
              </li>
              <li className="list-group-item d-flex justify-content-between">
                <span className="text-muted">{t('প্রাথমিক অধিক্ষেত্র', 'Primary Scope')}</span>
                <span>{profile.administrative_unit ? `${profile.administrative_unit.name_en} (${profile.administrative_unit.tier_level})` : '-'}</span>
              </li>
              <li className="list-group-item d-flex justify-content-between">
                <span className="text-muted">{t('সর্বশেষ লগইন', 'Last Login')}</span>
                <span>{profile.last_login_at ? new Date(profile.last_login_at).toLocaleString() : '-'}</span>
              </li>
            </ul>
          </div>

          {/* Keycloak Federation & MFA */}
          <div className="card shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold">
              <i className="bi bi-key-fill text-success me-2"></i>
              {t('কীক্লক আইডেন্টিটি ও এমএফএ', 'Keycloak Federation & MFA')}
            </div>
            <div className="card-body small">
              <div className="mb-1"><strong>Sub UUID:</strong> <code>{profile.keycloak_sub || t('সংযুক্ত নয়', 'Not linked')}</code></div>
              <div className="mb-1"><strong>Username:</strong> {profile.keycloak_username || '-'}</div>
              <div className="mb-2"><strong>Last Sync:</strong> {profile.idp_synced_at ? new Date(profile.idp_synced_at).toLocaleString() : '-'}</div>
              {profile.idp_sync_error && (
                <div className="alert alert-warning py-1 px-2 small">{profile.idp_sync_error}</div>
              )}
              {canSyncKeycloak(currentUser) && (
                <div className="d-flex flex-wrap gap-2 mt-2">
                  <button
                    type="button"
                    className="btn btn-outline-primary btn-sm"
                    disabled={keycloakSyncMutation.isPending}
                    onClick={() => keycloakSyncMutation.mutate({})}
                  >
                    <i className="bi bi-arrow-repeat me-1"></i>
                    {t('কীক্লক সিঙ্ক করুন', 'Sync Keycloak')}
                  </button>
                  <button
                    type="button"
                    className="btn btn-outline-dark btn-sm"
                    disabled={keycloakSyncMutation.isPending}
                    onClick={() =>
                      keycloakSyncMutation.mutate({
                        enforce_mfa_only: true,
                        mfa_enforced: !profile.mfa_enforced,
                      })
                    }
                  >
                    <i className="bi bi-shield-lock me-1"></i>
                    {profile.mfa_enforced ? t('এমএফএ শিথিল', 'Disable MFA Policy') : t('এমএফএ বাধ্যতামূলক', 'Enforce MFA')}
                  </button>
                </div>
              )}
            </div>
          </div>

          {/* Account Lifecycle Controls */}
          {canChangeUserStatus(currentUser) && !isSelf && (
            <div className="card shadow-sm">
              <div className="card-header bg-white fw-semibold">
                <i className="bi bi-toggle-on text-primary me-2"></i>
                {t('অ্যাকাউন্ট জীবনচক্র নিয়ন্ত্রণ', 'Lifecycle Status Controls')}
              </div>
              <div className="card-body">
                <input
                  type="text"
                  className="form-control form-control-sm mb-2"
                  placeholder={t('পরিবর্তনের কারণ উল্লেখ করুন...', 'Reason for status change...')}
                  value={statusReason}
                  onChange={(e) => setStatusReason(e.target.value)}
                />
                <div className="d-flex flex-wrap gap-1">
                  <button type="button" className="btn btn-success btn-sm" onClick={() => statusMutation.mutate('ACTIVE')}>
                    {t('সক্রিয়', 'Activate')}
                  </button>
                  <button type="button" className="btn btn-warning btn-sm" onClick={() => statusMutation.mutate('SUSPENDED')}>
                    {t('স্থগিত', 'Suspend')}
                  </button>
                  <button type="button" className="btn btn-dark btn-sm" onClick={() => statusMutation.mutate('DEACTIVATED')}>
                    {t('নিষ্ক্রিয়', 'Deactivate')}
                  </button>
                  <button type="button" className="btn btn-danger btn-sm" onClick={() => statusMutation.mutate('LOCKED')}>
                    {t('লক', 'Lock')}
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Right column: Roles, Organizational Scopes, Activity */}
        <div className="col-lg-8">
          {/* Role Assignment */}
          <div className="card shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
              <span><i className="bi bi-person-badge me-2 text-primary"></i>{t('ভূমিকা ও দায়িত্বসমূহ', 'Assigned System Roles')}</span>
              {canAssignUserRoles(currentUser) && (
                <button
                  type="button"
                  className="btn btn-primary btn-sm"
                  disabled={rolesMutation.isPending || selectedRoles.length === 0}
                  onClick={() => rolesMutation.mutate(selectedRoles)}
                >
                  {t('ভূমিকা সংরক্ষণ', 'Save Roles')}
                </button>
              )}
            </div>
            <div className="card-body">
              <div className="row g-2">
                {allRoles.map((r) => {
                  const checked = selectedRoles.includes(r.code);
                  return (
                    <div key={r.id} className="col-md-4">
                      <div className="form-check">
                        <input
                          className="form-check-input"
                          type="checkbox"
                          id={`detail-role-${r.code}`}
                          disabled={!canAssignUserRoles(currentUser)}
                          checked={checked}
                          onChange={() => {
                            const next = selectedRoles.includes(r.code)
                              ? (selectedRoles.length > 1 ? selectedRoles.filter((x) => x !== r.code) : selectedRoles)
                              : [...selectedRoles, r.code];
                            setEditedRoles(next);
                          }}
                        />
                        <label className="form-check-label small" htmlFor={`detail-role-${r.code}`}>
                          <strong>{lang === 'bn' ? r.name_bn : r.name_en}</strong>
                          <div className="text-muted" style={{ fontSize: '0.72rem' }}>{r.code}</div>
                        </label>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Organizational & Temporary Geographical Scopes */}
          <div className="card shadow-sm mb-3">
            <div className="card-header bg-white fw-semibold">
              <i className="bi bi-diagram-2-fill text-success me-2"></i>
              {t('সাংগঠনিক ও অস্থায়ী ভৌগোলিক অধিক্ষেত্র', 'Organizational & Temporary Scope Assignments')}
            </div>
            <div className="card-body">
              <div className="table-responsive mb-3">
                <table className="table table-sm align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th>{t('অধিক্ষেত্র', 'Administrative Unit')}</th>
                      <th>{t('ধরন', 'Assignment Type')}</th>
                      <th>{t('সময়কাল', 'Validity')}</th>
                      <th>{t('অফিস আদেশ', 'Order Ref')}</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    {(profile.administrative_scopes || []).length === 0 ? (
                      <tr>
                        <td colSpan={5} className="text-muted text-center py-2">
                          {t('কোনো অতিরিক্ত অধিক্ষেত্র নির্ধারিত নেই।', 'No scope assignments recorded.')}
                        </td>
                      </tr>
                    ) : (
                      profile.administrative_scopes.map((s) => (
                        <tr key={s.id} className={!s.is_currently_valid ? 'opacity-50' : ''}>
                          <td className="small">
                            <strong>{s.administrative_unit?.name_en}</strong> ({s.administrative_unit?.tier_level})
                            <div className="text-muted">{s.administrative_unit?.ancestry_path}</div>
                          </td>
                          <td><ScopeAssignmentBadge type={s.assignment_type} /></td>
                          <td className="small">
                            {s.valid_from ? new Date(s.valid_from).toLocaleDateString() : '-'}
                            {' → '}
                            {s.valid_until ? new Date(s.valid_until).toLocaleDateString() : t('অনির্দিষ্ট', 'Indefinite')}
                          </td>
                          <td className="small">{s.official_order_ref || '-'}</td>
                          <td className="text-end">
                            {canAssignUserScopes(currentUser) && s.assignment_type !== 'PRIMARY_POSTING' && s.is_active && (
                              <button
                                type="button"
                                className="btn btn-outline-danger btn-sm"
                                onClick={() => revokeScopeMutation.mutate(s.id)}
                              >
                                {t('প্রত্যাহার', 'Revoke')}
                              </button>
                            )}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>

              {canAssignUserScopes(currentUser) && (
                <form
                  className="border rounded p-2 bg-light"
                  onSubmit={(e) => {
                    e.preventDefault();
                    assignScopeMutation.mutate({
                      ...scopeForm,
                      valid_from: scopeForm.valid_from || null,
                      valid_until: scopeForm.valid_until || null,
                    });
                  }}
                >
                  <div className="fw-semibold small mb-2">{t('নতুন / অস্থায়ী দায়িত্ব যুক্ত করুন', 'Assign Additional / Temporary Scope')}</div>
                  <div className="row g-2">
                    <div className="col-md-4">
                      <select
                        className="form-select form-select-sm"
                        required
                        value={scopeForm.administrative_unit_id}
                        onChange={(e) => setScopeForm({ ...scopeForm, administrative_unit_id: e.target.value })}
                      >
                        <option value="">{t('অধিক্ষেত্র নির্বাচন', 'Select Unit')}</option>
                        {adminUnits.map((u) => (
                          <option key={u.id} value={u.id}>
                            [{u.tier_level}] {u.name_en}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="col-md-3">
                      <select
                        className="form-select form-select-sm"
                        value={scopeForm.assignment_type}
                        onChange={(e) => setScopeForm({ ...scopeForm, assignment_type: e.target.value })}
                      >
                        {(lookups.assignment_types || ['PRIMARY_POSTING', 'ADDITIONAL_CHARGE', 'EMERGENCY_DEPUTATION', 'INSPECTION_TAG']).map((at) => (
                          <option key={at} value={at}>
                            {SCOPE_ASSIGNMENT_META[at] ? (lang === 'bn' ? SCOPE_ASSIGNMENT_META[at].bn : SCOPE_ASSIGNMENT_META[at].en) : at}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="col-md-2">
                      <input
                        type="date"
                        className="form-control form-control-sm"
                        value={scopeForm.valid_from}
                        onChange={(e) => setScopeForm({ ...scopeForm, valid_from: e.target.value })}
                        title="Valid From"
                      />
                    </div>
                    <div className="col-md-3">
                      <input
                        type="date"
                        className="form-control form-control-sm"
                        value={scopeForm.valid_until}
                        onChange={(e) => setScopeForm({ ...scopeForm, valid_until: e.target.value })}
                        title="Valid Until"
                      />
                    </div>
                    <div className="col-md-5">
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        placeholder={t('অফিস আদেশ নম্বর', 'Official Order Ref (e.g. Memo-124)')}
                        value={scopeForm.official_order_ref}
                        onChange={(e) => setScopeForm({ ...scopeForm, official_order_ref: e.target.value })}
                      />
                    </div>
                    <div className="col-md-5">
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        placeholder={t('মন্তব্য', 'Notes')}
                        value={scopeForm.notes}
                        onChange={(e) => setScopeForm({ ...scopeForm, notes: e.target.value })}
                      />
                    </div>
                    <div className="col-md-2 d-grid">
                      <button type="submit" className="btn btn-success btn-sm" disabled={assignScopeMutation.isPending}>
                        {t('যুক্ত করুন', 'Assign')}
                      </button>
                    </div>
                  </div>
                </form>
              )}
            </div>
          </div>

          {/* Activity Audit Trail */}
          <div className="card shadow-sm">
            <div className="card-header bg-white fw-semibold">
              <i className="bi bi-clock-history text-secondary me-2"></i>
              {t('ব্যবহারকারীর কার্যক্রম ও অডিট লগ', 'User Activity & Audit History')}
            </div>
            <div className="table-responsive">
              <table className="table table-sm table-hover mb-0 align-middle">
                <thead className="table-light">
                  <tr>
                    <th>{t('কার্যক্রম', 'Action')}</th>
                    <th>{t('সম্পাদনকারী', 'Actor')}</th>
                    <th>{t('বিবরণ', 'Details')}</th>
                    <th>{t('সময়', 'Time')}</th>
                  </tr>
                </thead>
                <tbody>
                  {activityLogs.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="text-center text-muted py-3">
                        {t('কোনো অডিট লগ পাওয়া যায়নি।', 'No activity logs recorded yet.')}
                      </td>
                    </tr>
                  ) : (
                    activityLogs.map((log) => (
                      <tr key={log.id}>
                        <td><span className="badge bg-light text-dark border">{log.action_name}</span></td>
                        <td className="small">{log.actor_name}</td>
                        <td className="small text-muted">
                          {log.new_values ? JSON.stringify(log.new_values).slice(0, 90) : '-'}
                        </td>
                        <td className="small text-muted">{log.created_at ? new Date(log.created_at).toLocaleString() : '-'}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <ConfirmDialog
        isOpen={confirmArchive}
        title={t('ব্যবহারকারী আর্কাইভ নিশ্চিতকরণ', 'Confirm User Archive')}
        message={t(
          `আপনি কি "${profile.full_name_en}" অ্যাকাউন্টটি নিষ্ক্রিয় ও আর্কাইভ করতে চান?`,
          `Deactivate and archive user account "${profile.full_name_en}"?`
        )}
        variant="danger"
        onConfirm={() => archiveMutation.mutate()}
        onCancel={() => setConfirmArchive(false)}
      />
    </div>
  );
};

export default UserDetailPage;
