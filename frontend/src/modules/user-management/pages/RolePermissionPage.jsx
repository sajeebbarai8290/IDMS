import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { userManagementApi } from '../../../api/userManagementApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { canManageRoleConfig, formatUserApiError } from '../utils/userPermissions';

export const RolePermissionPage = () => {
  const { user: currentUser } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const [selectedRoleId, setSelectedRoleId] = useState(null);
  const [editedPermsByRole, setEditedPermsByRole] = useState({});
  const [moduleFilter, setModuleFilter] = useState('');
  const [permSearch, setPermSearch] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [showNewRole, setShowNewRole] = useState(false);
  const [newRole, setNewRole] = useState({
    code: '',
    name_en: '',
    name_bn: '',
    tier_level: 'UPAZILA',
    description: '',
    mfa_required: true,
  });

  const canManage = canManageRoleConfig(currentUser);

  const { data: rolesRes, isLoading: rolesLoading } = useQuery({
    queryKey: ['user-management', 'roles'],
    queryFn: () => userManagementApi.getRoles(),
  });

  const { data: permsRes, isLoading: permsLoading } = useQuery({
    queryKey: ['user-management', 'permissions'],
    queryFn: () => userManagementApi.getPermissions(),
  });

  const roles = rolesRes?.data || [];
  const allPermissions = permsRes?.data || [];
  const activeRole = roles.find((r) => r.id === selectedRoleId) || roles[0] || null;
  const selectedPermCodes =
    (activeRole && editedPermsByRole[activeRole.id]) ||
    (activeRole?.permissions ? activeRole.permissions.map((p) => p.code) : []);

  const syncPermsMutation = useMutation({
    mutationFn: ({ roleId, permissions }) => userManagementApi.syncRolePermissions(roleId, permissions),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setFeedback({ variant: 'success', message: t('ভূমিকার অনুমতিসমূহ সংরক্ষিত হয়েছে।', 'Role permissions updated and cache invalidated.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to update permissions.') }),
  });

  const createRoleMutation = useMutation({
    mutationFn: (payload) => userManagementApi.createRole(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setShowNewRole(false);
      setNewRole({ code: '', name_en: '', name_bn: '', tier_level: 'UPAZILA', description: '', mfa_required: true });
      if (res?.data?.id) setSelectedRoleId(res.data.id);
      setFeedback({ variant: 'success', message: t('নতুন ভূমিকা তৈরি করা হয়েছে।', 'Custom role created.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to create role.') }),
  });

  const deleteRoleMutation = useMutation({
    mutationFn: (roleId) => userManagementApi.deleteRole(roleId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setSelectedRoleId(null);
      setFeedback({ variant: 'success', message: t('ভূমিকা মুছে ফেলা হয়েছে।', 'Custom role deleted.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Cannot delete role.') }),
  });

  if (rolesLoading || permsLoading) {
    return <Loader message="ভূমিকা ও অনুমতি ম্যাট্রিক্স লোড হচ্ছে..." messageEn="Loading roles and permissions..." />;
  }

  const modules = Array.from(new Set(allPermissions.map((p) => p.module_code)));
  const filteredPermissions = allPermissions.filter((p) => {
    if (moduleFilter && p.module_code !== moduleFilter) return false;
    if (permSearch) {
      const term = permSearch.toLowerCase();
      return (
        p.code.toLowerCase().includes(term) ||
        p.name_en.toLowerCase().includes(term) ||
        p.name_bn.toLowerCase().includes(term)
      );
    }
    return true;
  });

  const grouped = filteredPermissions.reduce((acc, p) => {
    acc[p.module_code] = acc[p.module_code] || [];
    acc[p.module_code].push(p);
    return acc;
  }, {});

  const togglePermission = (code) => {
    if (!canManage || !activeRole) return;
    const next = selectedPermCodes.includes(code)
      ? selectedPermCodes.filter((c) => c !== code)
      : [...selectedPermCodes, code];
    setEditedPermsByRole((prev) => ({ ...prev, [activeRole.id]: next }));
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold mb-0">
            <i className="bi bi-shield-lock-fill text-primary me-2"></i>
            {t('ভূমিকা ও অনুমতি ম্যাট্রিক্স (RBAC)', 'Roles & Permission Matrix')}
          </h4>
          <small className="text-muted">
            {t('মডিউল-ভিত্তিক অনুমোদন, সংবেদনশীল ডাটা ও জিআইএস প্রবেশাধিকার নিয়ন্ত্রণ', 'Configure roles and module-level CRUD, approve, verify, export, PII, and GIS permissions')}
          </small>
        </div>
        {canManage && (
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => setShowNewRole((v) => !v)}
          >
            <i className="bi bi-plus-circle me-1"></i>
            {t('নতুন কনফিগারযোগ্য ভূমিকা', 'New Custom Role')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {showNewRole && canManage && (
        <form
          className="card shadow-sm mb-3 border-primary"
          onSubmit={(e) => {
            e.preventDefault();
            createRoleMutation.mutate(newRole);
          }}
        >
          <div className="card-header bg-primary text-white fw-semibold">
            {t('নতুন কাস্টম ভূমিকা তৈরি', 'Create Configurable Role')}
          </div>
          <div className="card-body row g-2">
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder="ROLE_RELIEF_COORDINATOR"
                required
                value={newRole.code}
                onChange={(e) => setNewRole({ ...newRole, code: e.target.value.toUpperCase() })}
              />
            </div>
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('নাম (ইংরেজি) *', 'Role Name (EN) *')}
                required
                value={newRole.name_en}
                onChange={(e) => setNewRole({ ...newRole, name_en: e.target.value })}
              />
            </div>
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('নাম (বাংলা) *', 'Role Name (BN) *')}
                required
                value={newRole.name_bn}
                onChange={(e) => setNewRole({ ...newRole, name_bn: e.target.value })}
              />
            </div>
            <div className="col-md-3">
              <select
                className="form-select form-select-sm"
                value={newRole.tier_level}
                onChange={(e) => setNewRole({ ...newRole, tier_level: e.target.value })}
              >
                {['NATIONAL', 'DIRECTORATE', 'DIVISION', 'DISTRICT', 'UPAZILA', 'UNION', 'FIELD'].map((tl) => (
                  <option key={tl} value={tl}>{tl}</option>
                ))}
              </select>
            </div>
            <div className="col-md-8">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('বিবরণ', 'Role description')}
                value={newRole.description}
                onChange={(e) => setNewRole({ ...newRole, description: e.target.value })}
              />
            </div>
            <div className="col-md-2 d-flex align-items-center">
              <div className="form-check">
                <input
                  className="form-check-input"
                  type="checkbox"
                  id="newRoleMfa"
                  checked={newRole.mfa_required}
                  onChange={(e) => setNewRole({ ...newRole, mfa_required: e.target.checked })}
                />
                <label className="form-check-label small" htmlFor="newRoleMfa">MFA</label>
              </div>
            </div>
            <div className="col-md-2 d-grid">
              <button type="submit" className="btn btn-primary btn-sm" disabled={createRoleMutation.isPending}>
                {t('তৈরি করুন', 'Create')}
              </button>
            </div>
          </div>
        </form>
      )}

      <div className="row g-3">
        {/* Role Catalog Sidebar */}
        <div className="col-lg-4">
          <div className="card shadow-sm">
            <div className="card-header bg-white fw-semibold">
              {t(`সিস্টেম ও কাস্টম ভূমিকা (${roles.length})`, `Roles Catalog (${roles.length})`)}
            </div>
            <div className="list-group list-group-flush" style={{ maxHeight: '72vh', overflowY: 'auto' }}>
              {roles.map((r) => (
                <button
                  key={r.id}
                  type="button"
                  onClick={() => setSelectedRoleId(r.id)}
                  className={`list-group-item list-group-item-action text-start ${activeRole?.id === r.id ? 'active' : ''}`}
                >
                  <div className="d-flex justify-content-between align-items-center">
                    <strong className="small">{lang === 'bn' ? r.name_bn : r.name_en}</strong>
                    <span className={`badge ${activeRole?.id === r.id ? 'bg-light text-primary' : 'bg-secondary'}`}>
                      {r.permissions_count ?? r.permissions?.length ?? 0}
                    </span>
                  </div>
                  <div className="small opacity-75 d-flex justify-content-between">
                    <span>{r.code}</span>
                    <span>{r.tier_level} {r.is_system ? '' : '• Custom'}</span>
                  </div>
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Permission Matrix Panel */}
        <div className="col-lg-8">
          {activeRole && (
            <div className="card shadow-sm">
              <div className="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                  <h6 className="fw-bold mb-0">
                    {lang === 'bn' ? activeRole.name_bn : activeRole.name_en} ({activeRole.code})
                  </h6>
                  <small className="text-muted">
                    {activeRole.description} • {t('ব্যবহারকারী:', 'Users:')} {activeRole.users_count ?? 0}
                  </small>
                </div>
                <div className="d-flex gap-2">
                  {canManage && !activeRole.is_system && (
                    <button
                      type="button"
                      className="btn btn-outline-danger btn-sm"
                      onClick={() => deleteRoleMutation.mutate(activeRole.id)}
                    >
                      {t('মুছুন', 'Delete Role')}
                    </button>
                  )}
                  {canManage && (
                    <button
                      type="button"
                      className="btn btn-success btn-sm"
                      disabled={syncPermsMutation.isPending}
                      onClick={() => syncPermsMutation.mutate({ roleId: activeRole.id, permissions: selectedPermCodes })}
                    >
                      <i className="bi bi-check2-circle me-1"></i>
                      {t('অনুমতি সংরক্ষণ করুন', 'Save Permissions')}
                    </button>
                  )}
                </div>
              </div>

              <div className="card-body">
                <div className="row g-2 mb-3">
                  <div className="col-md-5">
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder={t('অনুমতি কোড বা নাম খুঁজুন...', 'Filter permissions...')}
                      value={permSearch}
                      onChange={(e) => setPermSearch(e.target.value)}
                    />
                  </div>
                  <div className="col-md-4">
                    <select
                      className="form-select form-select-sm"
                      value={moduleFilter}
                      onChange={(e) => setModuleFilter(e.target.value)}
                    >
                      <option value="">{t('সকল মডিউল', 'All Modules')}</option>
                      {modules.map((m) => (
                        <option key={m} value={m}>{m.toUpperCase()}</option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-3 text-end small text-muted align-self-center">
                    {t(`নির্বাচিত: ${selectedPermCodes.length}`, `Selected: ${selectedPermCodes.length}`)}
                  </div>
                </div>

                {Object.entries(grouped).map(([mod, perms]) => (
                  <div key={mod} className="mb-3 border rounded p-2">
                    <div className="fw-bold small text-uppercase text-primary mb-2 border-bottom pb-1">
                      {mod} ({perms.length})
                    </div>
                    <div className="row g-2">
                      {perms.map((p) => {
                        const checked = selectedPermCodes.includes(p.code);
                        return (
                          <div key={p.id} className="col-md-6">
                            <div className="form-check">
                              <input
                                className="form-check-input"
                                type="checkbox"
                                id={`perm-${p.code}`}
                                disabled={!canManage}
                                checked={checked}
                                onChange={() => togglePermission(p.code)}
                              />
                              <label className="form-check-label small" htmlFor={`perm-${p.code}`}>
                                <span className="fw-semibold">{lang === 'bn' ? p.name_bn : p.name_en}</span>
                                {p.is_sensitive && (
                                  <span className="badge bg-danger ms-1" style={{ fontSize: '0.65rem' }}>SENSITIVE</span>
                                )}
                                <div className="text-muted" style={{ fontSize: '0.72rem' }}>
                                  <code>{p.code}</code> • {p.action_type}
                                </div>
                              </label>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default RolePermissionPage;
