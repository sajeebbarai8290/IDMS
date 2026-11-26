import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { WarehouseGisMap } from '../components/WarehouseGisMap';
import {
  canExportWarehouseLedger,
  canManageFacilities,
  canReceiveStock,
  canRequestTransfer,
  formatBdt,
  formatWarehouseApiError,
  triggerWarehouseBlobDownload,
  TX_TYPE_LABELS,
} from '../utils/warehousePermissions';

export const WarehouseDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const [alertTab, setAlertTab] = useState('low_stock');
  const [feedback, setFeedback] = useState(null);

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['warehouse', 'dashboard'],
    queryFn: () => warehouseApi.getDashboard(),
  });

  const { data: alertsRes } = useQuery({
    queryKey: ['warehouse', 'alerts'],
    queryFn: () => warehouseApi.getAlerts({ days: 30 }),
  });

  const { data: geoRes } = useQuery({
    queryKey: ['warehouse', 'geojson'],
    queryFn: () => warehouseApi.getGeoJson(),
  });

  const handleExportCsv = async () => {
    try {
      const res = await warehouseApi.exportLedgerCsv();
      triggerWarehouseBlobDownload(res.data, 'idms-warehouse-ledger.csv');
    } catch (err) {
      setFeedback({
        variant: 'danger',
        message: formatWarehouseApiError(err, t('লেজার এক্সপোর্ট ব্যর্থ হয়েছে।', 'Failed to export stock ledger.')),
      });
    }
  };

  if (isLoading) return <Loader />;

  const kpis = dashRes?.data?.kpis || {};
  const recentTxs = dashRes?.data?.recent_transactions || [];
  const alertsData = alertsRes?.data || {
    summary: {},
    low_stock: [],
    out_of_stock: [],
    expiring_soon: [],
    expired: [],
    unusual_adjustments: [],
  };
  const alertsSummary = alertsData.summary || {};

  const activeAlertList = alertsData[alertTab] || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-building-fill-check text-primary me-2"></i>
            {t('গুদাম ও মজুদ ব্যবস্থাপনা ড্যাশবোর্ড (M08)', 'Warehouse & Inventory Dashboard (M08)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'কেন্দ্রীয় (CSD), আঞ্চলিক (LSD), জেলা ও উপজেলা গুদামের রিয়েল-টাইম মজুদ, জিআরএন, স্থানান্তর ও ত্রাণ বরাদ্দ সমন্বয়।',
              'Real-time inventory, GRN receiving, inter-warehouse transfers, expiry tracking, and relief dispatch coordination.'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          {canReceiveStock(user) && (
            <Link to="/warehouse/receipts" className="btn btn-success btn-sm">
              <i className="bi bi-box-arrow-in-down me-1"></i>
              {t('নতুন জিআরএন / মালামাল গ্রহণ', 'Receive Stock (GRN)')}
            </Link>
          )}
          {canRequestTransfer(user) && (
            <Link to="/warehouse/transfers" className="btn btn-primary btn-sm">
              <i className="bi bi-arrow-left-right me-1"></i>
              {t('গুদাম স্থানান্তর', 'Transfers')}
            </Link>
          )}
          {canManageFacilities(user) && (
            <Link to="/warehouse/facilities/new" className="btn btn-outline-primary btn-sm">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন গুদাম', 'Add Warehouse')}
            </Link>
          )}
          {canExportWarehouseLedger(user) && (
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={handleExportCsv}>
              <i className="bi bi-download me-1"></i>
              {t('লেজার CSV', 'Export Ledger CSV')}
            </button>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && (
        <AlertBanner
          variant="danger"
          message={formatWarehouseApiError(error, t('ড্যাশবোর্ড ডেটা লোড করতে ব্যর্থ।', 'Failed to load warehouse dashboard.'))}
        />
      )}

      {/* KPI Cards */}
      <div className="row g-3 mb-3">
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div className="card-body py-2 px-3">
              <div className="small text-muted">{t('সক্রিয় গুদাম', 'Active Warehouses')}</div>
              <div className="fs-4 fw-bold text-primary">
                {kpis.active_warehouses ?? 0} <span className="fs-6 text-muted fw-normal">/ {kpis.total_warehouses ?? 0}</span>
              </div>
              <div className="small text-muted">{Number(kpis.total_capacity_mt || 0).toLocaleString()} MT</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div className="card-body py-2 px-3">
              <div className="small text-muted">{t('উপলব্ধ মজুদ', 'Available Stock')}</div>
              <div className="fs-4 fw-bold text-success">{Number(kpis.total_available_stock || 0).toLocaleString()}</div>
              <div className="small text-muted">
                {t('মোট', 'Physical')}: {Number(kpis.total_physical_stock || 0).toLocaleString()}
              </div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card border-0 shadow-sm h-100 border-start border-4 border-info">
            <div className="card-body py-2 px-3">
              <div className="small text-muted">{t('সংরক্ষিত (Reserved)', 'Reserved Stock')}</div>
              <div className="fs-4 fw-bold text-info">{Number(kpis.total_reserved_stock || 0).toLocaleString()}</div>
              <div className="small text-muted">{t('ত্রাণ বরাদ্দের জন্য', 'For sanctioned relief')}</div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div className="card-body py-2 px-3">
              <div className="small text-muted">{t('যাচাই অপেক্ষমাণ GRN', 'Pending GRNs')}</div>
              <div className="fs-4 fw-bold text-warning">{kpis.pending_grn_verifications ?? 0}</div>
              <div className="small text-muted">
                {t('চলমান স্থানান্তর', 'Active Transfers')}: {kpis.active_transfers ?? 0}
              </div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div className="card-body py-2 px-3">
              <div className="small text-muted">{t('মজুদ সতর্কতা', 'Stock Alerts')}</div>
              <div className="fs-4 fw-bold text-danger">{kpis.total_alerts ?? 0}</div>
              <div className="small text-muted">
                {t('মেয়াদোত্তীর্ণ/ক্ষতি', 'Dmg/Exp')}: {Number((kpis.total_damaged_stock || 0) + (kpis.total_expired_stock || 0)).toLocaleString()}
              </div>
            </div>
          </div>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="card border-0 shadow-sm h-100 border-start border-4 border-dark">
            <div className="card-body py-2 px-3">
              <div className="small text-muted">{t('মজুদ মূল্যায়ন', 'Stock Valuation')}</div>
              <div className="fs-5 fw-bold text-dark">{formatBdt(kpis.total_valuation_bdt)}</div>
              <div className="small text-muted">{t('সক্রিয় ব্যাচ সমষ্টি', 'Active batch valuation')}</div>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content Grid: Alerts & GIS Map */}
      <div className="row g-3 mb-3">
        <div className="col-12 col-lg-6">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                {t('রিয়েল-টাইম মজুদ ও মেয়াদ সতর্কতা', 'Real-Time Stock & Expiry Alerts')}
              </h6>
              <Link to="/warehouse/reports" className="btn btn-link btn-sm p-0 text-decoration-none">
                {t('প্রতিবেদন দেখুন →', 'View Reports →')}
              </Link>
            </div>
            <div className="card-body">
              <ul className="nav nav-pills nav-fill small mb-3 gap-1">
                <li className="nav-item">
                  <button
                    type="button"
                    className={`nav-link py-1 ${alertTab === 'low_stock' ? 'active' : 'bg-light'}`}
                    onClick={() => setAlertTab('low_stock')}
                  >
                    {t('কম মজুদ', 'Low Stock')} ({alertsSummary.low_stock_count ?? 0})
                  </button>
                </li>
                <li className="nav-item">
                  <button
                    type="button"
                    className={`nav-link py-1 ${alertTab === 'out_of_stock' ? 'active bg-danger' : 'bg-light text-danger'}`}
                    onClick={() => setAlertTab('out_of_stock')}
                  >
                    {t('শূন্য মজুদ', 'Out of Stock')} ({alertsSummary.out_of_stock_count ?? 0})
                  </button>
                </li>
                <li className="nav-item">
                  <button
                    type="button"
                    className={`nav-link py-1 ${alertTab === 'expiring_soon' ? 'active bg-warning text-dark' : 'bg-light text-dark'}`}
                    onClick={() => setAlertTab('expiring_soon')}
                  >
                    {t('শীঘ্রই মেয়াদ শেষ', 'Expiring')} ({alertsSummary.expiring_soon_count ?? 0})
                  </button>
                </li>
                <li className="nav-item">
                  <button
                    type="button"
                    className={`nav-link py-1 ${alertTab === 'expired' ? 'active bg-dark' : 'bg-light text-dark'}`}
                    onClick={() => setAlertTab('expired')}
                  >
                    {t('মেয়াদোত্তীর্ণ', 'Expired')} ({alertsSummary.expired_count ?? 0})
                  </button>
                </li>
                <li className="nav-item">
                  <button
                    type="button"
                    className={`nav-link py-1 ${alertTab === 'unusual_adjustments' ? 'active bg-secondary' : 'bg-light text-dark'}`}
                    onClick={() => setAlertTab('unusual_adjustments')}
                  >
                    {t('বড় সমন্বয়', 'Adjustments')} ({alertsSummary.unusual_adjustment_count ?? 0})
                  </button>
                </li>
              </ul>

              {activeAlertList.length === 0 ? (
                <div className="text-center text-muted py-4 small">
                  <i className="bi bi-check-circle text-success fs-4 d-block mb-1"></i>
                  {t('এই বিভাগে কোনো সতর্কতা নেই।', 'No active alerts in this category.')}
                </div>
              ) : (
                <div className="table-responsive" style={{ maxHeight: '300px', overflowY: 'auto' }}>
                  <table className="table table-sm table-hover align-middle mb-0 small">
                    <thead className="table-light">
                      <tr>
                        <th>{t('পণ্য', 'Commodity')}</th>
                        <th>{t('গুদাম', 'Warehouse')}</th>
                        <th className="text-end">{t('পরিমাণ / সীমা', 'Qty / Detail')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {activeAlertList.slice(0, 12).map((item, idx) => (
                        <tr key={idx}>
                          <td>
                            <div className="fw-semibold">{t(item.commodity_name_bn || item.commodity_name_en, item.commodity_name_en)}</div>
                            <div className="text-muted">{item.batch_number || item.reference_voucher_no || item.commodity_code}</div>
                          </td>
                          <td>{t(item.warehouse_name_bn || item.warehouse_name, item.warehouse_name)}</td>
                          <td className="text-end">
                            {alertTab === 'expiring_soon' || alertTab === 'expired' ? (
                              <span className={`badge bg-${item.days_to_expiry < 0 ? 'danger' : 'warning text-dark'}`}>
                                {item.expiry_date} ({item.days_to_expiry}d)
                              </span>
                            ) : alertTab === 'unusual_adjustments' ? (
                              <span className="badge bg-dark">{item.quantity} ({item.reason_code})</span>
                            ) : (
                              <span>
                                <strong>{item.available_quantity}</strong> / <span className="text-muted">min {item.minimum_stock_level}</span>
                              </span>
                            )}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-12 col-lg-6">
          <div className="card border-0 shadow-sm h-100">
            <div className="card-header bg-white py-2 d-flex justify-content-between align-items-center">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-geo-alt-fill text-success me-2"></i>
                {t('গুদাম নেটওয়ার্ক জিআইএস মানচিত্র', 'Warehouse Network GIS Map')}
              </h6>
              <Link to="/warehouse/facilities" className="btn btn-link btn-sm p-0 text-decoration-none">
                {t('সকল গুদাম →', 'All Facilities →')}
              </Link>
            </div>
            <div className="card-body p-2">
              <WarehouseGisMap geojson={geoRes?.data} height="340px" mode="explore" />
            </div>
          </div>
        </div>
      </div>

      {/* Recent Immutable Stock Transactions */}
      <div className="card border-0 shadow-sm">
        <div className="card-header bg-white py-2 d-flex justify-content-between align-items-center">
          <h6 className="fw-bold mb-0">
            <i className="bi bi-journal-check text-primary me-2"></i>
            {t('সাম্প্রতিক অপরিবর্তনীয় মজুদ লেজার লেনদেন', 'Recent Immutable Stock Ledger Transactions')}
          </h6>
          <Link to="/warehouse/inventory" className="btn btn-outline-primary btn-sm">
            {t('পূর্ণ মজুদ ও লেজার', 'Open Inventory & Ledger')}
          </Link>
        </div>
        <div className="table-responsive">
          <table className="table table-hover table-sm align-middle mb-0 small">
            <thead className="table-light">
              <tr>
                <th>{t('তারিখ/সময়', 'Timestamp')}</th>
                <th>{t('ধরন', 'Type')}</th>
                <th>{t('গুদাম', 'Warehouse')}</th>
                <th>{t('পণ্য', 'Commodity')}</th>
                <th className="text-end">{t('পরিমাণ', 'Quantity')}</th>
                <th className="text-end">{t('পরবর্তী জের', 'Balance After')}</th>
                <th>{t('ভাউচার রেফারেন্স', 'Reference')}</th>
              </tr>
            </thead>
            <tbody>
              {recentTxs.length === 0 ? (
                <tr>
                  <td colSpan="7" className="text-center text-muted py-4">
                    {t('কোনো সাম্প্রতিক লেনদেন পাওয়া যায়নি।', 'No recent transactions recorded.')}
                  </td>
                </tr>
              ) : (
                recentTxs.map((tx) => {
                  const meta = TX_TYPE_LABELS[tx.transaction_type] || {
                    bn: tx.transaction_type,
                    en: tx.transaction_type,
                    variant: 'secondary',
                  };
                  return (
                    <tr key={tx.id}>
                      <td className="text-nowrap">{tx.created_at ? new Date(tx.created_at).toLocaleString() : '—'}</td>
                      <td>
                        <span className={`badge bg-${meta.variant}`}>{t(meta.bn, meta.en)}</span>
                      </td>
                      <td>{tx.warehouse_name}</td>
                      <td>{t(tx.commodity_name_bn || tx.commodity_name_en, tx.commodity_name_en)}</td>
                      <td className={`text-end fw-semibold ${tx.quantity >= 0 ? 'text-success' : 'text-danger'}`}>
                        {tx.quantity > 0 ? `+${tx.quantity}` : tx.quantity} {tx.unit_of_measure}
                      </td>
                      <td className="text-end">{tx.balance_after}</td>
                      <td><code>{tx.reference_voucher_no}</code></td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};
