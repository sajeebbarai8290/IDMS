import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { WarehousePagination } from '../components/WarehousePagination';
import {
  canManageFacilities,
  formatBdt,
  formatWarehouseApiError,
  WAREHOUSE_TIER_LABELS,
} from '../utils/warehousePermissions';

export const WarehouseFacilityListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();

  const [search, setSearch] = useState('');
  const [tier, setTier] = useState('');
  const [page, setPage] = useState(1);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['warehouse', 'facilities', search, tier, page],
    queryFn: () =>
      warehouseApi.getFacilities({
        page,
        per_page: 12,
        ...(search ? { search } : {}),
        ...(tier ? { tier } : {}),
      }),
  });

  if (isLoading) return <Loader />;

  const facilities = res?.data || [];
  const meta = res?.meta || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-houses-fill text-primary me-2"></i>
            {t('গুদাম ও সংরক্ষণাগার তালিকা (Facilities)', 'Warehouse Facilities Register')}
          </h4>
          <p className="text-muted small mb-0">
            {t('জাতীয়, আঞ্চলিক, জেলা ও উপজেলা পর্যায়ের গুদাম, ধারণক্ষমতা এবং দায়িত্বপ্রাপ্ত কর্মকর্তা।', 'Registered depots across the administrative hierarchy with capacity and responsible officers.')}
          </p>
        </div>

        {canManageFacilities(user) && (
          <Link to="/warehouse/facilities/new" className="btn btn-primary btn-sm">
            <i className="bi bi-plus-circle me-1"></i>
            {t('নতুন গুদাম নিবন্ধন', 'Register Warehouse')}
          </Link>
        )}
      </div>

      {error && (
        <AlertBanner
          variant="danger"
          message={formatWarehouseApiError(error, t('গুদামের তালিকা লোড করতে ব্যর্থ।', 'Failed to load warehouses.'))}
        />
      )}

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body py-2">
          <div className="row g-2 align-items-center">
            <div className="col-12 col-md-5">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('কোড, নাম বা সংস্থা দ্বারা অনুসন্ধান...', 'Search by code, name, or organization...')}
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
              />
            </div>
            <div className="col-12 col-md-3">
              <select
                className="form-select form-select-sm"
                value={tier}
                onChange={(e) => {
                  setTier(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সকল স্তর (All Tiers)', 'All Tiers')}</option>
                {Object.entries(WAREHOUSE_TIER_LABELS).map(([key, val]) => (
                  <option key={key} value={key}>{t(val.bn, val.en)}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0 small">
            <thead className="table-light">
              <tr>
                <th>{t('কোড ও নাম', 'Code & Name')}</th>
                <th>{t('স্তর ও ধরন', 'Tier & Type')}</th>
                <th>{t('প্রশাসনিক এলাকা', 'Jurisdiction')}</th>
                <th>{t('দায়িত্বপ্রাপ্ত কর্মকর্তা', 'Responsible Officer')}</th>
                <th className="text-end">{t('ধারণক্ষমতা', 'Capacity (MT)')}</th>
                <th className="text-end">{t('মজুদ / মূল্যায়ন', 'Stock / Value')}</th>
                <th>{t('অবস্থা', 'Status')}</th>
                <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
              </tr>
            </thead>
            <tbody>
              {facilities.length === 0 ? (
                <tr>
                  <td colSpan="8" className="text-center text-muted py-4">
                    {t('কোনো গুদাম পাওয়া যায়নি।', 'No warehouse facilities found.')}
                  </td>
                </tr>
              ) : (
                facilities.map((wh) => {
                  const tierMeta = WAREHOUSE_TIER_LABELS[wh.tier] || { bn: wh.tier, en: wh.tier, variant: 'secondary' };
                  const summary = wh.stock_summary || {};
                  return (
                    <tr key={wh.id}>
                      <td>
                        <div className="fw-bold">{t(wh.warehouse_name_bn || wh.warehouse_name, wh.warehouse_name)}</div>
                        <div className="text-muted"><code>{wh.warehouse_code}</code> • {wh.owning_organization || 'MoDMR'}</div>
                      </td>
                      <td>
                        <span className={`badge bg-${tierMeta.variant} me-1`}>{t(tierMeta.bn, tierMeta.en)}</span>
                        <div className="text-muted mt-1">{wh.warehouse_type || 'RELIEF_GODOWN'}</div>
                      </td>
                      <td>
                        <div>{t(wh.administrative_unit?.name_bn, wh.administrative_unit?.name_en)}</div>
                        <div className="text-muted">{wh.address || '—'}</div>
                      </td>
                      <td>
                        <div>{wh.responsible_officer_name || '—'}</div>
                        <div className="text-muted">{wh.responsible_officer_contact || wh.contact_phone || '—'}</div>
                      </td>
                      <td className="text-end fw-semibold">{Number(wh.capacity_mt || 0).toLocaleString()} MT</td>
                      <td className="text-end">
                        <div>{Number(summary.total_available_quantity || 0).toLocaleString()} {t('উপলব্ধ', 'avail')}</div>
                        <div className="text-muted">{formatBdt(summary.total_valuation_bdt || 0)}</div>
                      </td>
                      <td>
                        <span className={`badge bg-${wh.is_active ? 'success' : 'secondary'}`}>
                          {wh.is_active ? t('সক্রিয়', 'Active') : t('নিষ্ক্রিয়', 'Inactive')}
                        </span>
                      </td>
                      <td className="text-end text-nowrap">
                        <Link to={`/warehouse/inventory?warehouse_id=${wh.id}`} className="btn btn-outline-info btn-sm me-1">
                          {t('মজুদ', 'Stock')}
                        </Link>
                        {canManageFacilities(user) && (
                          <Link to={`/warehouse/facilities/${wh.id}/edit`} className="btn btn-outline-primary btn-sm">
                            {t('সম্পাদনা', 'Edit')}
                          </Link>
                        )}
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
        <WarehousePagination meta={meta} onPageChange={setPage} />
      </div>
    </div>
  );
};
