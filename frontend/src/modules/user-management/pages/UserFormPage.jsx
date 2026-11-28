import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { userManagementApi } from '../../../api/userManagementApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { formatUserApiError } from '../utils/userPermissions';

const UserFormInner = ({ id, isEdit, initialUser, lookups }) => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { t, lang } = useLanguage();

  const initialRoles =
    isEdit && initialUser
      ? Array.isArray(initialUser.roles) && initialUser.roles.length > 0
        ? initialUser.roles.map((r) => r.code)
        : initialUser.role_code
          ? [initialUser.role_code]
          : ['ROLE_FIELD_ENUMERATOR']
      : ['ROLE_FIELD_ENUMERATOR'];

  const [form, setForm] = useState({
    full_name_en: initialUser?.full_name_en || '',
    full_name_bn: initialUser?.full_name_bn || '',
    email: initialUser?.email || '',
    employee_id: initialUser?.employee_id || '',
    mobile_number: initialUser?.mobile_number || '',
    designation: initialUser?.designation || '',
    designation_bn: initialUser?.designation_bn || '',
    organization_id: initialUser?.organization_id || '',
    department_en: initialUser?.department_en || '',
    department_bn: initialUser?.department_bn || '',
    administrative_unit_id: initialUser?.administrative_unit_id || '',
    role_codes: initialRoles,
    account_status: initialUser?.account_status || 'ACTIVE',
    keycloak_sub: initialUser?.keycloak_sub || '',
    keycloak_username: initialUser?.keycloak_username || '',
    mfa_enforced: initialUser ? Boolean(initialUser.mfa_enforced) : true,
    official_order_ref: '',
    sync_with_keycloak: true,
  });
  const [photoFile, setPhotoFile] = useState(null);
  const [errorMsg, setErrorMsg] = useState('');

  const saveMutation = useMutation({
    mutationFn: async (payload) => {
      const res = isEdit
        ? await userManagementApi.updateUser(id, payload)
        : await userManagementApi.createUser(payload);

      const savedId = res?.data?.id || id;
      if (photoFile && savedId) {
        const fd = new FormData();
        fd.append('photo', photoFile);
        await userManagementApi.uploadPhoto(savedId, fd);
      }
      return savedId;
    },
    onSuccess: (savedId) => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      navigate(`/user-management/users/${savedId}`);
    },
    onError: (err) => {
      setErrorMsg(formatUserApiError(err, t('সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save user profile.')));
    },
  });

  const roles = lookups.roles || [];
  const organizations = lookups.organizations || [];
  const adminUnits = lookups.administrative_units || [];

  const toggleRole = (code) => {
    setForm((prev) => {
      const exists = prev.role_codes.includes(code);
      const next = exists
        ? prev.role_codes.filter((c) => c !== code)
        : [...prev.role_codes, code];
      return { ...prev, role_codes: next.length ? next : prev.role_codes };
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setErrorMsg('');
    const payload = {
      ...form,
      organization_id: form.organization_id || null,
      keycloak_sub: form.keycloak_sub || null,
      employee_id: form.employee_id || null,
      role_code: form.role_codes[0],
    };
    saveMutation.mutate(payload);
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 className="fw-bold mb-0">
            {isEdit
              ? t('ব্যবহারকারী প্রোফাইল হালনাগাদ', 'Update User Profile')
              : t('নতুন ব্যবহারকারী প্রভিশনিং', 'Provision New System User')}
          </h4>
          <small className="text-muted">
            {t(
              'কীক্লক আইডেন্টিটি ফেডারেশন (পাসওয়ার্ড স্থানীয়ভাবে সংরক্ষিত হয় না)',
              'Federated with Keycloak IAM (passwords are never stored locally in Laravel)'
            )}
          </small>
        </div>
        <Link to="/user-management/users" className="btn btn-outline-secondary btn-sm">
          <i className="bi bi-arrow-left me-1"></i>
          {t('তালিকায় ফিরুন', 'Back to Directory')}
        </Link>
      </div>

      {errorMsg && <AlertBanner variant="danger" message={errorMsg} onClose={() => setErrorMsg('')} />}

      <form onSubmit={handleSubmit} className="card shadow-sm">
        <div className="card-body">
          <div className="row g-3">
            <div className="col-md-6">
              <label className="form-label fw-semibold">{t('পূর্ণ নাম (ইংরেজি) *', 'Full Name (English) *')}</label>
              <input
                type="text"
                className="form-control"
                required
                value={form.full_name_en}
                onChange={(e) => setForm({ ...form, full_name_en: e.target.value })}
              />
            </div>
            <div className="col-md-6">
              <label className="form-label fw-semibold">{t('পূর্ণ নাম (বাংলা)', 'Full Name (Bangla)')}</label>
              <input
                type="text"
                className="form-control"
                value={form.full_name_bn}
                onChange={(e) => setForm({ ...form, full_name_bn: e.target.value })}
              />
            </div>

            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('সরকারি ইমেইল *', 'Official Email *')}</label>
              <input
                type="email"
                className="form-control"
                required
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('মোবাইল নম্বর', 'Mobile Number')}</label>
              <input
                type="text"
                className="form-control"
                placeholder="+8801..."
                value={form.mobile_number}
                onChange={(e) => setForm({ ...form, mobile_number: e.target.value })}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('কর্মকর্তা / কর্মচারী আইডি', 'Employee / Officer ID')}</label>
              <input
                type="text"
                className="form-control"
                placeholder="MODMR-1024"
                value={form.employee_id}
                onChange={(e) => setForm({ ...form, employee_id: e.target.value })}
              />
            </div>

            <div className="col-md-6">
              <label className="form-label fw-semibold">{t('পদবি (ইংরেজি)', 'Designation (English)')}</label>
              <input
                type="text"
                className="form-control"
                value={form.designation}
                onChange={(e) => setForm({ ...form, designation: e.target.value })}
              />
            </div>
            <div className="col-md-6">
              <label className="form-label fw-semibold">{t('পদবি (বাংলা)', 'Designation (Bangla)')}</label>
              <input
                type="text"
                className="form-control"
                value={form.designation_bn}
                onChange={(e) => setForm({ ...form, designation_bn: e.target.value })}
              />
            </div>

            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('সংগঠন', 'Organization')}</label>
              <select
                className="form-select"
                value={form.organization_id}
                onChange={(e) => setForm({ ...form, organization_id: e.target.value })}
              >
                <option value="">{t('-- সংগঠন নির্বাচন করুন --', '-- Select Organization --')}</option>
                {organizations.map((o) => (
                  <option key={o.id} value={o.id}>
                    {lang === 'bn' ? (o.name_bn || o.name_en) : o.name_en} ({o.org_code})
                  </option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('বিভাগ / ইউনিট (ইংরেজি)', 'Department / Unit (EN)')}</label>
              <input
                type="text"
                className="form-control"
                value={form.department_en}
                onChange={(e) => setForm({ ...form, department_en: e.target.value })}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('বিভাগ / ইউনিট (বাংলা)', 'Department / Unit (BN)')}</label>
              <input
                type="text"
                className="form-control"
                value={form.department_bn}
                onChange={(e) => setForm({ ...form, department_bn: e.target.value })}
              />
            </div>

            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('প্রাথমিক ভৌগোলিক অধিক্ষেত্র *', 'Primary Geographical Scope *')}</label>
              <select
                className="form-select"
                required
                value={form.administrative_unit_id}
                onChange={(e) => setForm({ ...form, administrative_unit_id: e.target.value })}
              >
                <option value="">{t('-- অধিক্ষেত্র নির্বাচন করুন --', '-- Select Administrative Unit --')}</option>
                {adminUnits.map((u) => (
                  <option key={u.id} value={u.id}>
                    [{u.tier_level}] {lang === 'bn' ? u.name_bn : u.name_en} ({u.ancestry_path})
                  </option>
                ))}
              </select>
            </div>

            {!isEdit && (
              <div className="col-md-4">
                <label className="form-label fw-semibold">{t('প্রাথমিক অ্যাকাউন্ট অবস্থা', 'Initial Account Status')}</label>
                <select
                  className="form-select"
                  value={form.account_status}
                  onChange={(e) => setForm({ ...form, account_status: e.target.value })}
                >
                  <option value="ACTIVE">{t('সক্রিয় (Active)', 'ACTIVE')}</option>
                  <option value="PENDING">{t('অপেক্ষমাণ (Pending)', 'PENDING')}</option>
                </select>
              </div>
            )}

            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('কীক্লক ইউজারনেম', 'Keycloak Username')}</label>
              <input
                type="text"
                className="form-control"
                value={form.keycloak_username}
                onChange={(e) => setForm({ ...form, keycloak_username: e.target.value })}
              />
            </div>
            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('কীক্লক UUID (ঐচ্ছিক)', 'Keycloak Sub UUID (Optional)')}</label>
              <input
                type="text"
                className="form-control"
                placeholder={t('খালি রাখলে সিঙ্ক থেকে সংযুক্ত হবে', 'Leave blank to auto-provision/link')}
                value={form.keycloak_sub}
                onChange={(e) => setForm({ ...form, keycloak_sub: e.target.value })}
              />
            </div>

            <div className="col-md-4">
              <label className="form-label fw-semibold">{t('প্রোফাইল ছবি (JPG/PNG/WebP)', 'Profile Photograph')}</label>
              <input
                type="file"
                className="form-control"
                accept="image/jpeg,image/png,image/webp"
                onChange={(e) => setPhotoFile(e.target.files?.[0] || null)}
              />
            </div>

            <div className="col-12">
              <label className="form-label fw-semibold">
                {t('সিস্টেম ভূমিকাসমূহ (প্রথম নির্বাচিতটি মূল ভূমিকা) *', 'Assigned Roles (First selected is primary) *')}
              </label>
              <div className="border rounded p-2 bg-light" style={{ maxHeight: 210, overflowY: 'auto' }}>
                <div className="row g-2">
                  {roles.map((r) => {
                    const checked = form.role_codes.includes(r.code);
                    return (
                      <div key={r.id} className="col-md-4 col-lg-3">
                        <div className="form-check">
                          <input
                            className="form-check-input"
                            type="checkbox"
                            id={`role-${r.code}`}
                            checked={checked}
                            onChange={() => toggleRole(r.code)}
                          />
                          <label className="form-check-label small" htmlFor={`role-${r.code}`}>
                            <strong>{lang === 'bn' ? r.name_bn : r.name_en}</strong>
                            <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                              {r.code} ({r.tier_level})
                            </div>
                          </label>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>

            <div className="col-md-6">
              <div className="form-check form-switch">
                <input
                  className="form-check-input"
                  type="checkbox"
                  id="mfaEnforcedSwitch"
                  checked={form.mfa_enforced}
                  onChange={(e) => setForm({ ...form, mfa_enforced: e.target.checked })}
                />
                <label className="form-check-label" htmlFor="mfaEnforcedSwitch">
                  {t('কীক্লক টিওটিপি এমএফএ বাধ্যতামূলক করুন', 'Enforce Mandatory Keycloak MFA (TOTP)')}
                </label>
              </div>
            </div>

            <div className="col-md-6">
              <div className="form-check form-switch">
                <input
                  className="form-check-input"
                  type="checkbox"
                  id="kcSyncSwitch"
                  checked={form.sync_with_keycloak}
                  onChange={(e) => setForm({ ...form, sync_with_keycloak: e.target.checked })}
                />
                <label className="form-check-label" htmlFor="kcSyncSwitch">
                  {t('সংরক্ষণের পর কীক্লকের সাথে সিঙ্ক করুন', 'Provision / Synchronize with Keycloak on Save')}
                </label>
              </div>
            </div>
          </div>
        </div>

        <div className="card-footer bg-white d-flex justify-content-end gap-2">
          <Link to="/user-management/users" className="btn btn-outline-secondary">
            {t('বাতিল', 'Cancel')}
          </Link>
          <button type="submit" className="btn btn-primary" disabled={saveMutation.isPending}>
            {saveMutation.isPending
              ? t('সংরক্ষণ হচ্ছে...', 'Saving...')
              : isEdit
                ? t('হালনাগাদ করুন', 'Update Profile')
                : t('ব্যবহারকারী তৈরি করুন', 'Provision User')}
          </button>
        </div>
      </form>
    </div>
  );
};

export const UserFormPage = () => {
  const { id } = useParams();
  const isEdit = Boolean(id);

  const { data: lookupsRes, isLoading: lookupsLoading } = useQuery({
    queryKey: ['user-management', 'lookups'],
    queryFn: () => userManagementApi.getLookups(),
  });

  const { data: userRes, isLoading: userLoading } = useQuery({
    queryKey: ['user-management', 'user', id],
    queryFn: () => userManagementApi.getUser(id),
    enabled: isEdit,
  });

  if (lookupsLoading || (isEdit && userLoading)) {
    return <Loader message="ফর্ম লোড হচ্ছে..." messageEn="Loading user form..." />;
  }

  return (
    <UserFormInner
      key={isEdit ? `edit-${id}-${userRes?.data?.updated_at || ''}` : 'create-new'}
      id={id}
      isEdit={isEdit}
      initialUser={isEdit ? userRes?.data : null}
      lookups={lookupsRes?.data || {}}
    />
  );
};

export default UserFormPage;
