import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import {
  canExportWarehouseLedger,
  formatBdt,
  formatWarehouseApiError,
  triggerWarehouseBlobDownload,
} from '../utils/warehousePermissions';

const REPORT_TYPES = [
  { key: 'STOCK_BALANCE', bn: 'মজুদ ব্যালেন্স প্রতিবেদন', en: 'Stock Balance Report' },
  { key: 'STOCK_MOVEMENT', bn: 'মজুদ প্রবাহ (In/Out) প্রতিবেদন', en: 'Stock Movement Report' },
  { key: 'WAREHOUSE_INVENTORY', bn: 'গুদামভিত্তিক মজুদ', en: 'Warehouse-Wise Inventory' },
  { key: 'ITEM_INVENTORY', bn: 'পণ্যভিত্তিক মজুদ ও সীমা', en: 'Item-Wise Inventory' },
  { key: 'EXPIRY', bn: 'মেয়াদ ও ঝুঁকি প্রতিবেদন', en: 'Expiry Risk Report' },
  { key: 'TRANSACTION_HISTORY', bn: 'অপরিবর্তনীয় লেনদেন ইতিহাস', en: 'Transaction History' },
];

export const WarehouseReportsPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();

  const [reportType, setReportType] = useState('STOCK_BALANCE');
  const [warehouseId, setWarehouseId] = useState('');
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['warehouse', 'lookups'],
    queryFn: () => warehouseApi.getLookups(),
  });

  const { data: repRes, isLoading, error } = useQuery({
    queryKey: ['warehouse', 'reports', reportType, warehouseId],
    queryFn: () =>
      warehouseApi.getReports({
        type: reportType,
        ...(warehouseId ? { warehouse_id: warehouseId } : {}),
      }),
  });

  const handleExportCsv = async () => {
    try {
      const res = await warehouseApi.exportLedgerCsv(warehouseId ? { warehouse_id: warehouseId } : {});
      triggerWarehouseBlobDownload(res.data, `idms-warehouse-ledger-${reportType.toLowerCase()}.csv`);
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('CSV এক্সপোর্ট ব্যর্থ।', 'Export failed.')) });
    }
  };

  const rows = repRes?.data || [];
  const warehouses = lookupsRes?.data?.warehouses || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-file-earmark-spreadsheet-fill text-primary me-2"></i>
            {t('গুদাম ও মজুদ বিশ্লেষণী প্রতিবেদন (Reports)', 'Warehouse & Inventory Reports')}
          </h4>
          <p className="text-muted small mb-0">
            {t('মজুদ ব্যালেন্স, প্রবাহ, গুদাম ও পণ্যভিত্তিক পরিসংখ্যান, মেয়াদ এবং পূর্ণ লেনদেন ইতিহাস।', 'Stock balance, inflows/outflows, warehouse-wise, item-wise, expiry, and transaction audit reports.')}
          </p>
        </div>

        {canExportWarehouseLedger(user) && (
          <button type="button" className="btn btn-outline-success btn-sm" onClick={handleExportCsv}>
            <i className="bi bi-download me-1"></i>
            {t('লেজার CSV ডাউনলোড', 'Download CSV Ledger')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatWarehouseApiError(error, t('প্রতিবেদন লোড ব্যর্থ।', 'Failed to load report.'))} />}

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body py-2">
          <div className="row g-2 align-items-center">
            <div className="col-12 col-md-8 d-flex flex-wrap gap-1">
              {REPORT_TYPES.map((rt) => (
                <button
                  key={rt.key}
                  type="button"
                  className={`btn btn-sm ${reportType === rt.key ? 'btn-primary' : 'btn-outline-secondary'}`}
                  onClick={() => setReportType(rt.key)}
                >
                  {t(rt.bn, rt.en)}
                </button>
              ))}
            </div>
            <div className="col-12 col-md-4">
              <select className="form-select form-select-sm" value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)}>
                <option value="">{t('সকল আওতাভুক্ত গুদাম', 'All Scoped Warehouses')}</option>
                {warehouses.map((w) => (
                  <option key={w.id} value={w.id}>{t(w.warehouse_name_bn || w.warehouse_name, w.warehouse_name)}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0 small">
              <thead className="table-light">
                {reportType === 'STOCK_BALANCE' && (
                  <tr>
                    <th>{t('গুদাম', 'Warehouse')}</th>
                    <th>{t('পণ্য', 'Commodity')}</th>
                    <th className="text-end">{t('প্রারম্ভিক', 'Opening')}</th>
                    <th className="text-end">{t('মোট', 'Physical')}</th>
                    <th className="text-end">{t('সংরক্ষিত', 'Reserved')}</th>
                    <th className="text-end">{t('উপলব্ধ', 'Available')}</th>
                    <th className="text-end">{t('মূল্যায়ন', 'Valuation')}</th>
                  </tr>
                )}
                {reportType === 'STOCK_MOVEMENT' && (
                  <tr>
                    <th>{t('গুদাম', 'Warehouse')}</th>
                    <th>{t('পণ্য', 'Commodity')}</th>
                    <th className="text-end">{t('মোট প্রবেশ (+)', 'Inflow (+)')}</th>
                    <th className="text-end">{t('মোট নির্গমন (-)', 'Outflow (-)')}</th>
                    <th className="text-end">{t('নিট পরিবর্তন', 'Net Change')}</th>
                    <th className="text-end">{t('লেনদেন সংখ্যা', 'Tx Count')}</th>
                  </tr>
                )}
                {reportType === 'WAREHOUSE_INVENTORY' && (
                  <tr>
                    <th>{t('গুদাম ও স্তর', 'Warehouse & Tier')}</th>
                    <th>{t('এলাকা', 'Jurisdiction')}</th>
                    <th className="text-end">{t('ধারণক্ষমতা', 'Capacity (MT)')}</th>
                    <th className="text-end">{t('মোট মজুদ', 'Physical')}</th>
                    <th className="text-end">{t('উপলব্ধ', 'Available')}</th>
                    <th className="text-end">{t('মূল্যায়ন', 'Valuation')}</th>
                  </tr>
                )}
                {reportType === 'ITEM_INVENTORY' && (
                  <tr>
                    <th>{t('পণ্য', 'Item')}</th>
                    <th className="text-end">{t('ন্যূনতম সীমা', 'Min Stock')}</th>
                    <th className="text-end">{t('উপলব্ধ', 'Available')}</th>
                    <th className="text-end">{t('সংরক্ষিত', 'Reserved')}</th>
                    <th className="text-end">{t('মূল্যায়ন', 'Valuation')}</th>
                    <th>{t('অবস্থা', 'Status')}</th>
                  </tr>
                )}
                {reportType === 'EXPIRY' && (
                  <tr>
                    <th>{t('গুদাম ও ব্যাচ', 'Warehouse & Batch')}</th>
                    <th>{t('পণ্য', 'Commodity')}</th>
                    <th className="text-end">{t('পরিমাণ', 'Qty')}</th>
                    <th>{t('মেয়াদ তারিখ', 'Expiry Date')}</th>
                    <th>{t('অবস্থা', 'State')}</th>
                  </tr>
                )}
                {reportType === 'TRANSACTION_HISTORY' && (
                  <tr>
                    <th>{t('সময়', 'Timestamp')}</th>
                    <th>{t('গুদাম / ব্যাচ', 'Warehouse / Batch')}</th>
                    <th>{t('পণ্য', 'Commodity')}</th>
                    <th>{t('ধরন', 'Type')}</th>
                    <th className="text-end">{t('পরিমাণ', 'Qty')}</th>
                    <th>{t('ভাউচার', 'Voucher')}</th>
                  </tr>
                )}
              </thead>
              <tbody>
                {rows.length === 0 ? (
                  <tr><td colSpan="7" className="text-center text-muted py-4">{t('এই প্রতিবেদনের জন্য কোনো তথ্য নেই।', 'No data matches this report.')}</td></tr>
                ) : (
                  rows.map((r, idx) => {
                    if (reportType === 'STOCK_BALANCE') {
                      return (
                        <tr key={idx}>
                          <td>{r.warehouse_name}</td>
                          <td>{t(r.commodity_name_bn || r.commodity_name_en, r.commodity_name_en)} ({r.unit_of_measure})</td>
                          <td className="text-end">{r.opening_quantity}</td>
                          <td className="text-end">{r.current_quantity}</td>
                          <td className="text-end text-info">{r.reserved_quantity}</td>
                          <td className="text-end fw-bold text-success">{r.available_quantity}</td>
                          <td className="text-end">{formatBdt(r.valuation_bdt)}</td>
                        </tr>
                      );
                    }
                    if (reportType === 'STOCK_MOVEMENT') {
                      return (
                        <tr key={idx}>
                          <td>{r.warehouse_name}</td>
                          <td>{t(r.commodity_name_bn || r.commodity_name_en, r.commodity_name_en)} ({r.unit_of_measure})</td>
                          <td className="text-end text-success fw-semibold">+{r.inflow_quantity}</td>
                          <td className="text-end text-danger fw-semibold">-{r.outflow_quantity}</td>
                          <td className="text-end fw-bold">{r.net_change}</td>
                          <td className="text-end">{r.transaction_count}</td>
                        </tr>
                      );
                    }
                    if (reportType === 'WAREHOUSE_INVENTORY') {
                      return (
                        <tr key={r.warehouse_id || idx}>
                          <td>{t(r.warehouse_name_bn || r.warehouse_name, r.warehouse_name)} <span className="badge bg-light text-dark border">{r.tier}</span></td>
                          <td>{r.jurisdiction}</td>
                          <td className="text-end">{r.capacity_mt} MT</td>
                          <td className="text-end">{r.current_quantity}</td>
                          <td className="text-end fw-bold text-success">{r.available_quantity}</td>
                          <td className="text-end">{formatBdt(r.valuation_bdt)}</td>
                        </tr>
                      );
                    }
                    if (reportType === 'ITEM_INVENTORY') {
                      return (
                        <tr key={r.commodity_id || idx}>
                          <td>{t(r.name_bn, r.name_en)} (<code>{r.commodity_code}</code>)</td>
                          <td className="text-end">{r.minimum_stock_level}</td>
                          <td className="text-end fw-bold">{r.available_quantity}</td>
                          <td className="text-end text-info">{r.reserved_quantity}</td>
                          <td className="text-end">{formatBdt(r.valuation_bdt)}</td>
                          <td>
                            <span className={`badge bg-${r.stock_status === 'OUT_OF_STOCK' ? 'danger' : r.stock_status === 'LOW_STOCK' ? 'warning text-dark' : 'success'}`}>
                              {r.stock_status}
                            </span>
                          </td>
                        </tr>
                      );
                    }
                    if (reportType === 'EXPIRY') {
                      return (
                        <tr key={r.inventory_id || idx}>
                          <td>{r.warehouse_name} • <code>{r.batch_number}</code></td>
                          <td>{t(r.commodity_name_bn || r.commodity_name_en, r.commodity_name_en)}</td>
                          <td className="text-end fw-semibold">{r.current_quantity} {r.unit_of_measure}</td>
                          <td>{r.expiry_date} ({r.days_to_expiry}d)</td>
                          <td>
                            <span className={`badge bg-${r.expiry_state === 'EXPIRED' ? 'danger' : 'warning text-dark'}`}>
                              {r.expiry_state}
                            </span>
                          </td>
                        </tr>
                      );
                    }
                    return (
                      <tr key={r.id || idx}>
                        <td className="text-nowrap">{r.created_at ? new Date(r.created_at).toLocaleString() : '—'}</td>
                        <td>{r.warehouse_name} • <code>{r.batch_number}</code></td>
                        <td>{t(r.commodity_name_bn || r.commodity_name_en, r.commodity_name_en)}</td>
                        <td>{r.transaction_type}</td>
                        <td className={`text-end fw-semibold ${r.quantity >= 0 ? 'text-success' : 'text-danger'}`}>{r.quantity}</td>
                        <td><code>{r.reference_voucher_no}</code></td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};
