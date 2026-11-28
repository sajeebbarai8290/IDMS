import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { userManagementApi } from '../../../api/userManagementApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from '../../../components/common/ConfirmDialog';
import {
  canManageOrganizations,
  formatUserApiError,
  ORG_TYPE_META,
  TIER_LEVEL_META,
} from '../utils/userPermissions';

export const OrganizationListPage = () => {
  const { user: currentUser } = useAuth();
  const { t, lang } = useLanguage();
  const queryClient = useQueryClient();

  const [search, setSearch] = useState('');
  const [orgType, setOrgType] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [editingOrg, setEditingOrg] = useState(null);
  const [archiveTarget, setArchiveTarget] = useState(null);
  const [feedback, setFeedback] = useState(null);
  const [form, setForm] = useState({
    org_code: '',
    name_en: '',
    name_bn: '',
    org_type: 'DISTRICT_ADMIN',
    tier_level: 'DISTRICT',
    administrative_unit_id: '',
    email: '',
    phone: '',
    address: '',
  });

  const canManage = canManageOrganizations(currentUser);

  const { data: lookupsRes } = useQuery({
    queryKey: ['user-management', 'lookups'],
    queryFn: () => userManagementApi.getLookups(),
  });

  const { data: orgsRes, isLoading, error } = useQuery({
    queryKey: ['user-management', 'organizations', search, orgType],
    queryFn: () =>
      userManagementApi.getOrganizations({
        ...(search ? { search } : {}),
        ...(orgType ? { org_type: orgType } : {}),
      }),
  });

  const saveMutation = useMutation({
    mutationFn: (payload) =>
      editingOrg
        ? userManagementApi.updateOrganization(editingOrg.id, payload)
        : userManagementApi.createOrganization(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setShowForm(false);
      setEditingOrg(null);
      setForm({
        org_code: '',
        name_en: '',
        name_bn: '',
        org_type: 'DISTRICT_ADMIN',
        tier_level: 'DISTRICT',
        administrative_unit_id: '',
        email: '',
        phone: '',
        address: '',
      });
      setFeedback({ variant: 'success', message: t('সংগঠন তথ্য সংরক্ষিত হয়েছে।', 'Organization saved.') });
    },
    onError: (err) => setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to save organization.') }),
  });

  const archiveMutation = useMutation({
    mutationFn: (orgId) => userManagementApi.deleteOrganization(orgId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user-management'] });
      setArchiveTarget(null);
      setFeedback({ variant: 'success', message: t('সংগঠন আর্কাইভ করা হয়েছে।', 'Organization archived.') });
    },
    onError: (err) => {
      setArchiveTarget(null);
      setFeedback({ variant: 'danger', message: formatUserApiError(err, 'Failed to archive organization.') });
    },
  });

  const organizations = orgsRes?.data || [];
  const lookups = lookupsRes?.data || {};
  const adminUnits = lookups.administrative_units || [];

  const startEdit = (org) => {
    setEditingOrg(org);
    setForm({
      org_code: org.org_code || '',
      name_en: org.name_en || '',
      name_bn: org.name_bn || '',
      org_type: org.org_type || 'OTHER',
      tier_level: org.tier_level || 'DISTRICT',
      administrative_unit_id: org.administrative_unit_id || '',
      email: org.email || '',
      phone: org.phone || '',
      address: org.address || '',
    });
    setShowForm(true);
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold mb-0">
            <i className="bi bi-building-fill text-primary me-2"></i>
            {t('সাংগঠনিক কাঠামো ও দপ্তরসমূহ', 'Organizations & Statutory Agencies')}
          </h4>
          <small className="text-muted">
            {t('মন্ত্রণালয়, অধিদপ্তর, জেলা/উপজেলা দপ্তর এবং অংশীদার সংস্থাসমূহ', 'Ministries, Directorates, Field Administrations, and Partner Agencies')}
          </small>
        </div>
        {canManage && (
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => {
              setEditingOrg(null);
              setShowForm((v) => !v);
            }}
          >
            <i className="bi bi-plus-circle me-1"></i>
            {t('নতুন সংগঠন যোগ করুন', 'Add Organization')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      {showForm && canManage && (
        <form
          className="card shadow-sm mb-3 border-primary"
          onSubmit={(e) => {
            e.preventDefault();
            saveMutation.mutate({
              ...form,
              administrative_unit_id: form.administrative_unit_id || null,
            });
          }}
        >
          <div className="card-header bg-primary text-white fw-semibold">
            {editingOrg ? t('সংগঠন সম্পাদনা', 'Edit Organization') : t('নতুন সংগঠন নিবন্ধন', 'Register Organization')}
          </div>
          <div className="card-body row g-2">
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder="ORG-CODE *"
                required
                value={form.org_code}
                onChange={(e) => setForm({ ...form, org_code: e.target.value.toUpperCase() })}
              />
            </div>
            <div className="col-md-5">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('নাম (ইংরেজি) *', 'Organization Name (EN) *')}
                required
                value={form.name_en}
                onChange={(e) => setForm({ ...form, name_en: e.target.value })}
              />
            </div>
            <div className="col-md-4">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('নাম (বাংলা)', 'Organization Name (BN)')}
                value={form.name_bn}
                onChange={(e) => setForm({ ...form, name_bn: e.target.value })}
              />
            </div>
            <div className="col-md-3">
              <select
                className="form-select form-select-sm"
                value={form.org_type}
                onChange={(e) => setForm({ ...form, org_type: e.target.value })}
              >
                {Object.keys(ORG_TYPE_META).map((k) => (
                  <option key={k} value={k}>{lang === 'bn' ? ORG_TYPE_META[k].bn : ORG_TYPE_META[k].en}</option>
                ))}
              </select>
            </div>
            <div className="col-md-3">
              <select
                className="form-select form-select-sm"
                value={form.tier_level}
                onChange={(e) => setForm({ ...form, tier_level: e.target.value })}
              >
                {['NATIONAL', 'DIRECTORATE', 'DIVISION', 'DISTRICT', 'UPAZILA', 'UNION', 'FIELD'].map((tl) => (
                  <option key={tl} value={tl}>{TIER_LEVEL_META[tl] ? (lang === 'bn' ? TIER_LEVEL_META[tl].bn : TIER_LEVEL_META[tl].en) : tl}</option>
                ))}
              </select>
            </div>
            <div className="col-md-6">
              <select
                className="form-select form-select-sm"
                value={form.administrative_unit_id}
                onChange={(e) => setForm({ ...form, administrative_unit_id: e.target.value })}
              >
                <option value="">{t('-- অধিক্ষেত্র (ঐচ্ছিক) --', '-- Administrative Unit (Optional) --')}</option>
                {adminUnits.map((u) => (
                  <option key={u.id} value={u.id}>[{u.tier_level}] {u.name_en}</option>
                ))}
              </select>
            </div>
            <div className="col-md-4">
              <input
                type="email"
                className="form-control form-control-sm"
                placeholder={t('ইমেইল', 'Official Email')}
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
              />
            </div>
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('ফোন', 'Phone')}
                value={form.phone}
                onChange={(e) => setForm({ ...form, phone: e.target.value })}
              />
            </div>
            <div className="col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('ঠিকানা', 'Address')}
                value={form.address}
                onChange={(e) => setForm({ ...form, address: e.target.value })}
              />
            </div>
            <div className="col-md-2 d-grid">
              <button type="submit" className="btn btn-primary btn-sm" disabled={saveMutation.isPending}>
                {t('সংরক্ষণ', 'Save')}
              </button>
            </div>
          </div>
        </form>
      )}

      <div className="card shadow-sm mb-3">
        <div className="card-body py-2 row g-2">
          <div className="col-md-5">
            <input
              type="text"
              className="form-control form-control-sm"
              placeholder={t('সংগঠনের নাম বা কোড খুঁজুন...', 'Search organization name or code...')}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <div className="col-md-4">
            <select className="form-select form-select-sm" value={orgType} onChange={(e) => setOrgType(e.target.value)}>
              <option value="">{t('সকল ধরন', 'All Organization Types')}</option>
              {Object.keys(ORG_TYPE_META).map((k) => (
                <option key={k} value={k}>{lang === 'bn' ? ORG_TYPE_META[k].bn : ORG_TYPE_META[k].en}</option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader message="সংগঠন তালিকা লোড হচ্ছে..." messageEn="Loading organizations..." />
      ) : error ? (
        <AlertBanner variant="danger" message={formatUserApiError(error, 'Failed to load organizations.')} />
      ) : organizations.length === 0 ? (
        <EmptyState message={t('কোনো সংগঠন পাওয়া যায়নি।', 'No organizations found.')} />
      ) : (
        <div className="card shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('কোড', 'Code')}</th>
                  <th>{t('সংগঠনের নাম', 'Organization Name')}</th>
                  <th>{t('ধরন ও স্তর', 'Type & Tier')}</th>
                  <th>{t('অধিক্ষেত্র', 'Jurisdiction')}</th>
                  <th>{t('ব্যবহারকারী', 'Users')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
                </tr>
              </thead>
              <tbody>
                {organizations.map((org) => (
                  <tr key={org.id}>
                    <td><code>{org.org_code}</code></td>
                    <td>
                      <div className="fw-semibold">{lang === 'bn' ? (org.name_bn || org.name_en) : org.name_en}</div>
                      <div className="small text-muted">{org.email || org.phone || '-'}</div>
                    </td>
                    <td className="small">
                      <span className="badge bg-info text-dark me-1">{org.org_type}</span>
                      <span className="badge bg-light text-dark border">{org.tier_level}</span>
                    </td>
                    <td className="small">
                      {org.administrative_unit ? `${org.administrative_unit.name_en} (${org.administrative_unit.ancestry_path})` : '-'}
                    </td>
                    <td><span className="badge bg-primary">{org.users_count ?? 0}</span></td>
                    <td className="text-end text-nowrap">
                      {canManage && (
                        <>
                          <button type="button" className="btn btn-outline-secondary btn-sm me-1" onClick={() => startEdit(org)}>
                            <i className="bi bi-pencil"></i>
                          </button>
                          <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => setArchiveTarget(org)}>
                            <i className="bi bi-trash"></i>
                          </button>
                        </>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <ConfirmDialog
        isOpen={Boolean(archiveTarget)}
        title={t('সংগঠন আর্কাইভ নিশ্চিতকরণ', 'Confirm Organization Archive')}
        message={
          archiveTarget
            ? t(
                `আপনি কি "${archiveTarget.name_en}" আর্কাইভ করতে চান?`,
                `Archive organization "${archiveTarget.name_en}"?`
              )
            : ''
        }
        variant="danger"
        onConfirm={() => archiveMutation.mutate(archiveTarget.id)}
        onCancel={() => setArchiveTarget(null)}
      />
    </div>
  );
};

export default OrganizationListPage;
