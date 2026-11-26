import React, { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { WarehousePagination } from '../components/WarehousePagination';
import {
  canExportWarehouseLedger,
  canReceiveStock,
  canWriteOffStock,
  formatBdt,
  formatWarehouseApiError,
  triggerWarehouseBlobDownload,
  TX_TYPE_LABELS,
} from '../utils/warehousePermissions';

const EMPTY_ADJUST = {
  warehouse_id: '',
  commodity_id: '',
  batch_number: '',
  transaction_type: 'OPENING_STOCK',
  quantity: '',
  unit_cost_bdt: '',
  expiry_date: '',
  reference_voucher_no: '',
  reason_code: 'OPENING_BALANCE',
  notes: '',
  approved_by: '',
};

export const WarehouseInventoryPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();

  const [activeTab, setActiveTab] = useState('lots');
  const [warehouseFilter, setWarehouseFilter] = useState(searchParams.get('warehouse_id') || '');
  const [commodityFilter, setCommodityFilter] = useState('');
  const [txTypeFilter, setTxTypeFilter] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  const [showModal, setShowModal] = useState(false);
  const [adjustForm, setAdjustForm] = useState(EMPTY_ADJUST);
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['warehouse', 'lookups'],
    queryFn: () => warehouseApi.getLookups(),
  });

  const { data: lotsRes, isLoading: loadingLots } = useQuery({
    queryKey: ['warehouse', 'inventories', warehouseFilter, commodityFilter, search, page],
    queryFn: () =>
      warehouseApi.getInventories({
        page,
        per_page: 20,
        ...(warehouseFilter ? { warehouse_id: warehouseFilter } : {}),
        ...(commodityFilter ? { commodity_id: commodityFilter } : {}),
        ...(search ? { search } : {}),
      }),
    enabled: activeTab === 'lots',
  });

  const { data: txsRes, isLoading: loadingTxs } = useQuery({
    queryKey: ['warehouse', 'transactions', warehouseFilter, commodityFilter, txTypeFilter, search, page],
    queryFn: () =>
      warehouseApi.getTransactions({
        page,
        per_page: 20,
        ...(warehouseFilter ? { warehouse_id: warehouseFilter } : {}),
        ...(commodityFilter ? { commodity_id: commodityFilter } : {}),
        ...(txTypeFilter ? { transaction_type: txTypeFilter } : {}),
        ...(search ? { search } : {}),
      }),
    enabled: activeTab === 'ledger',
  });

  const adjustMutation = useMutation({
    mutationFn: (payload) => warehouseApi.recordAdjustment(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      setShowModal(false);
      setAdjustForm(EMPTY_ADJUST);
      setFeedback({ variant: 'success', message: t('অপরিবর্তনীয় লেজার এন্ট্রি সংরক্ষিত হয়েছে।', 'Immutable stock ledger transaction recorded.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('লেনদেন সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to record stock movement.')) });
    },
  });

  const handleExport = async () => {
    try {
      const res = await warehouseApi.exportLedgerCsv({
        ...(warehouseFilter ? { warehouse_id: warehouseFilter } : {}),
        ...(commodityFilter ? { commodity_id: commodityFilter } : {}),
        ...(txTypeFilter ? { transaction_type: txTypeFilter } : {}),
      });
      triggerWarehouseBlobDownload(res.data, 'idms-warehouse-ledger.csv');
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('CSV ডাউনলোড ব্যর্থ।', 'CSV export failed.')) });
    }
  };

  const isDualAuthType = ['WRITE_OFF_DAMAGED', 'EXPIRED_STOCK', 'STOCK_ADJUSTMENT'].includes(adjustForm.transaction_type);
  const canOpenAdjustModal = canReceiveStock(user) || canWriteOffStock(user);

  const lookups = lookupsRes?.data || {};
  const warehouses = lookups.warehouses || [];
  const commodities = lookups.commodities || [];
  const coAuthorizers = lookups.co_authorizers || [];

  const isLoading = activeTab === 'lots' ? loadingLots : loadingTxs;
  const currentRes = activeTab === 'lots' ? lotsRes : txsRes;
  const rows = currentRes?.data || [];
  const meta = currentRes?.meta || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-boxes text-primary me-2"></i>
            {t('মজুদ ও অপরিবর্তনীয় লেজার (Stock & Ledger)', 'Stock Balances & Immutable Ledger')}
          </h4>
          <p className="text-muted small mb-0">
            {t('ব্যাচভিত্তিক প্রারম্ভিক, উপলব্ধ, সংরক্ষিত, ক্ষতিগ্রস্ত ও মেয়াদোত্তীর্ণ মজুদ এবং অপরিবর্তনীয় লেনদেন ইতিহাস।', 'Batch-level stock balances (FEFO) and audit-locked immutable transaction history.')}
          </p>
        </div>

        <div className="d-flex gap-2">
          {canOpenAdjustModal && (
            <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowModal(true)}>
              <i className="bi bi-plus-slash-minus me-1"></i>
              {t('সমন্বয় / প্রারম্ভিক / রাইট-অফ', 'Adjustment / Opening / Write-off')}
            </button>
          )}
          {canExportWarehouseLedger(user) && (
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={handleExport}>
              <i className="bi bi-download me-1"></i>
              {t('CSV এক্সপোর্ট', 'Export Ledger CSV')}
            </button>
          )}
        </div>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}

      <ul className="nav nav-tabs mb-3">
        <li className="nav-item">
          <button
            type="button"
            className={`nav-link ${activeTab === 'lots' ? 'active fw-bold' : ''}`}
            onClick={() => {
              setActiveTab('lots');
              setPage(1);
            }}
          >
            <i className="bi bi-stack me-1"></i>
            {t('ব্যাচভিত্তিক মজুদ (Lots)', 'Stock Lots & Valuation')}
          </button>
        </li>
        <li className="nav-item">
          <button
            type="button"
            className={`nav-link ${activeTab === 'ledger' ? 'active fw-bold' : ''}`}
            onClick={() => {
              setActiveTab('ledger');
              setPage(1);
            }}
          >
            <i className="bi bi-journal-lock me-1"></i>
            {t('অপরিবর্তনীয় লেজার (Immutable Ledger)', 'Immutable Stock Ledger')}
          </button>
        </li>
      </ul>

      {/* Filters */}
      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body py-2">
          <div className="row g-2">
            <div className="col-12 col-md-3">
              <select
                className="form-select form-select-sm"
                value={warehouseFilter}
                onChange={(e) => {
                  setWarehouseFilter(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সকল গুদাম', 'All Warehouses')}</option>
                {warehouses.map((w) => (
                  <option key={w.id} value={w.id}>{t(w.warehouse_name_bn || w.warehouse_name, w.warehouse_name)}</option>
                ))}
              </select>
            </div>
            <div className="col-12 col-md-3">
              <select
                className="form-select form-select-sm"
                value={commodityFilter}
                onChange={(e) => {
                  setCommodityFilter(e.target.value);
                  setPage(1);
                }}
              >
                <option value="">{t('সকল পণ্য', 'All Commodities')}</option>
                {commodities.map((c) => (
                  <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
                ))}
              </select>
            </div>
            {activeTab === 'ledger' && (
              <div className="col-12 col-md-3">
                <select
                  className="form-select form-select-sm"
                  value={txTypeFilter}
                  onChange={(e) => {
                    setTxTypeFilter(e.target.value);
                    setPage(1);
                  }}
                >
                  <option value="">{t('সকল লেনদেনের ধরন', 'All Transaction Types')}</option>
                  {Object.entries(TX_TYPE_LABELS).map(([k, v]) => (
                    <option key={k} value={k}>{t(v.bn, v.en)}</option>
                  ))}
                </select>
              </div>
            )}
            <div className="col-12 col-md-3">
              <input
                type="text"
                className="form-control form-control-sm"
                placeholder={t('ব্যাচ বা ভাউচার খুঁজুন...', 'Search batch or voucher...')}
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
              />
            </div>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            {activeTab === 'lots' ? (
              <table className="table table-hover align-middle mb-0 small">
                <thead className="table-light">
                  <tr>
                    <th>{t('গুদাম ও ব্যাচ', 'Warehouse & Batch')}</th>
                    <th>{t('পণ্য', 'Commodity')}</th>
                    <th className="text-end">{t('প্রারম্ভিক', 'Opening')}</th>
                    <th className="text-end">{t('মোট', 'Physical')}</th>
                    <th className="text-end">{t('সংরক্ষিত', 'Reserved')}</th>
                    <th className="text-end">{t('উপলব্ধ', 'Available')}</th>
                    <th className="text-end">{t('ক্ষতি/মেয়াদ', 'Dmg/Exp')}</th>
                    <th className="text-end">{t('মূল্যায়ন', 'Valuation')}</th>
                    <th>{t('মেয়াদ উত্তীর্ণ', 'Expiry')}</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr><td colSpan="9" className="text-center text-muted py-4">{t('কোনো মজুদ ব্যাচ পাওয়া যায়নি।', 'No stock lots found.')}</td></tr>
                  ) : (
                    rows.map((lot) => (
                      <tr key={lot.id}>
                        <td>
                          <div className="fw-semibold">{t(lot.warehouse?.warehouse_name_bn || lot.warehouse?.warehouse_name, lot.warehouse?.warehouse_name)}</div>
                          <code>{lot.batch_number}</code>
                        </td>
                        <td>
                          <div>{t(lot.commodity?.name_bn, lot.commodity?.name_en)}</div>
                          <div className="text-muted">{lot.commodity?.unit_of_measure}</div>
                        </td>
                        <td className="text-end">{lot.opening_quantity}</td>
                        <td className="text-end fw-semibold">{lot.current_quantity}</td>
                        <td className="text-end text-info">{lot.reserved_quantity}</td>
                        <td className="text-end fw-bold text-success">{lot.available_quantity}</td>
                        <td className="text-end text-danger">
                          {lot.damaged_quantity} / {lot.expired_quantity}
                        </td>
                        <td className="text-end">{lot.valuation_bdt !== null ? formatBdt(lot.valuation_bdt) : '—'}</td>
                        <td>
                          {lot.expiry_date ? (
                            <span className={`badge bg-${lot.days_to_expiry < 0 ? 'danger' : lot.days_to_expiry <= 30 ? 'warning text-dark' : 'light text-dark border'}`}>
                              {lot.expiry_date} ({lot.days_to_expiry}d)
                            </span>
                          ) : '—'}
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            ) : (
              <table className="table table-hover align-middle mb-0 small">
                <thead className="table-light">
                  <tr>
                    <th>{t('সময়', 'Timestamp')}</th>
                    <th>{t('ধরন ও কারণ', 'Type & Reason')}</th>
                    <th>{t('গুদাম ও ব্যাচ', 'Warehouse & Batch')}</th>
                    <th>{t('পণ্য', 'Commodity')}</th>
                    <th className="text-end">{t('পরিমাণ', 'Qty')}</th>
                    <th className="text-end">{t('জের', 'Balance')}</th>
                    <th>{t('ভাউচার ও অনুমোদন', 'Voucher & Approval')}</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr><td colSpan="7" className="text-center text-muted py-4">{t('কোনো লেনদেন পাওয়া যায়নি।', 'No ledger entries found.')}</td></tr>
                  ) : (
                    rows.map((tx) => {
                      const metaLabel = TX_TYPE_LABELS[tx.transaction_type] || { bn: tx.transaction_type, en: tx.transaction_type, variant: 'secondary' };
                      return (
                        <tr key={tx.id}>
                          <td className="text-nowrap">{tx.created_at ? new Date(tx.created_at).toLocaleString() : '—'}</td>
                          <td>
                            <span className={`badge bg-${metaLabel.variant}`}>{t(metaLabel.bn, metaLabel.en)}</span>
                            {tx.reason_code && <div className="text-muted mt-1">{tx.reason_code}</div>}
                          </td>
                          <td>
                            <div>{t(tx.warehouse?.warehouse_name_bn || tx.warehouse?.warehouse_name, tx.warehouse?.warehouse_name)}</div>
                            <code>{tx.batch_number}</code>
                          </td>
                          <td>{t(tx.commodity?.name_bn, tx.commodity?.name_en)}</td>
                          <td className={`text-end fw-bold ${tx.quantity >= 0 ? 'text-success' : 'text-danger'}`}>
                            {tx.quantity > 0 ? `+${tx.quantity}` : tx.quantity}
                          </td>
                          <td className="text-end fw-semibold">{tx.balance_after}</td>
                          <td>
                            <code>{tx.reference_voucher_no}</code>
                            <div className="text-muted">
                              {tx.authorizer_name || ''} {tx.approver_name ? `• 4-eye: ${tx.approver_name}` : ''}
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            )}
          </div>
          <WarehousePagination meta={meta} onPageChange={setPage} />
        </div>
      )}

      {/* Modal for Opening Stock / Return / Adjustment / Dual-Auth Write-Off */}
      {showModal && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog modal-lg">
            <div className="modal-content">
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  adjustMutation.mutate({
                    ...adjustForm,
                    quantity: Number(adjustForm.quantity),
                    unit_cost_bdt: adjustForm.unit_cost_bdt === '' ? null : Number(adjustForm.unit_cost_bdt),
                    expiry_date: adjustForm.expiry_date || null,
                    approved_by: adjustForm.approved_by || null,
                  });
                }}
              >
                <div className="modal-header">
                  <h5 className="modal-title">{t('মজুদ সমন্বয় / প্রারম্ভিক / রাইট-অফ লেনদেন', 'Post Immutable Stock Ledger Adjustment')}</h5>
                  <button type="button" className="btn-close" onClick={() => setShowModal(false)} />
                </div>
                <div className="modal-body">
                  <div className="alert alert-info small py-2">
                    <i className="bi bi-shield-lock me-1"></i>
                    {t('ঐতিহাসিক লেজার মুছে ফেলা বা সম্পাদনা করা যায় না। রাইট-অফ ও সমন্বয়ের জন্য দ্বিতীয় অনুমোদনকারী কর্মকর্তা (4-eye SoD) বাধ্যতামূলক।', 'Historical transactions are immutable. Corrections and damaged/expired write-offs require 4-eye dual authorization.')}
                  </div>
                  <div className="row g-3">
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('লেনদেনের ধরন *', 'Transaction Type *')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={adjustForm.transaction_type}
                        onChange={(e) => setAdjustForm({ ...adjustForm, transaction_type: e.target.value })}
                      >
                        {canReceiveStock(user) && <option value="OPENING_STOCK">{t('প্রারম্ভিক মজুদ (Opening Stock)', 'Opening Stock')}</option>}
                        {canReceiveStock(user) && <option value="RETURN_IN">{t('সরবরাহ ফেরত গ্রহণ (Return In)', 'Supply Return In')}</option>}
                        {canWriteOffStock(user) && <option value="STOCK_ADJUSTMENT">{t('মজুদ সমন্বয় (+/- Adjustment)', 'Stock Adjustment (+/-)')}</option>}
                        {canWriteOffStock(user) && <option value="WRITE_OFF_DAMAGED">{t('ক্ষতিগ্রস্ত রাইট-অফ (Write-Off Damaged)', 'Write-Off Damaged Stock')}</option>}
                        {canWriteOffStock(user) && <option value="EXPIRED_STOCK">{t('মেয়াদোত্তীর্ণ রাইট-অফ (Expired)', 'Write-Off Expired Stock')}</option>}
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('গুদাম *', 'Warehouse *')}</label>
                      <select
                        className="form-select form-select-sm"
                        required
                        value={adjustForm.warehouse_id}
                        onChange={(e) => setAdjustForm({ ...adjustForm, warehouse_id: e.target.value })}
                      >
                        <option value="">{t('গুদাম নির্বাচন করুন...', 'Select warehouse...')}</option>
                        {warehouses.map((w) => <option key={w.id} value={w.id}>{t(w.warehouse_name_bn || w.warehouse_name, w.warehouse_name)}</option>)}
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('পণ্য *', 'Commodity *')}</label>
                      <select
                        className="form-select form-select-sm"
                        required
                        value={adjustForm.commodity_id}
                        onChange={(e) => setAdjustForm({ ...adjustForm, commodity_id: e.target.value })}
                      >
                        <option value="">{t('পণ্য নির্বাচন করুন...', 'Select commodity...')}</option>
                        {commodities.map((c) => <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)} ({c.unit_of_measure})</option>)}
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('ব্যাচ নম্বর *', 'Batch Number *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={adjustForm.batch_number}
                        onChange={(e) => setAdjustForm({ ...adjustForm, batch_number: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">
                        {adjustForm.transaction_type === 'STOCK_ADJUSTMENT'
                          ? t('পরিমাণ (+/-) *', 'Signed Delta (+/-) *')
                          : t('পরিমাণ *', 'Quantity *')}
                      </label>
                      <input
                        type="number"
                        step="0.01"
                        className="form-control form-control-sm"
                        required
                        value={adjustForm.quantity}
                        onChange={(e) => setAdjustForm({ ...adjustForm, quantity: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('রেফারেন্স ভাউচার *', 'Voucher Ref *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={adjustForm.reference_voucher_no}
                        onChange={(e) => setAdjustForm({ ...adjustForm, reference_voucher_no: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('কারণ কোড *', 'Reason Code *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={adjustForm.reason_code}
                        onChange={(e) => setAdjustForm({ ...adjustForm, reason_code: e.target.value })}
                      />
                    </div>
                    {isDualAuthType && (
                      <div className="col-12">
                        <label className="form-label small fw-semibold text-danger">
                          {t('সহ-অনুমোদনকারী কর্মকর্তা (4-Eye Dual Auth) *', 'Co-Authorizing Officer (4-Eye Dual Auth) *')}
                        </label>
                        <select
                          className="form-select form-select-sm"
                          required
                          value={adjustForm.approved_by}
                          onChange={(e) => setAdjustForm({ ...adjustForm, approved_by: e.target.value })}
                        >
                          <option value="">{t('দ্বিতীয় অনুমোদনকারী নির্বাচন করুন...', 'Select co-authorizing officer...')}</option>
                          {coAuthorizers.map((u) => (
                            <option key={u.id} value={u.id}>{u.full_name_en} ({u.role_code})</option>
                          ))}
                        </select>
                      </div>
                    )}
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('বিস্তারিত মন্তব্য *', 'Audit Notes *')}</label>
                      <textarea
                        rows="2"
                        className="form-control form-control-sm"
                        required
                        value={adjustForm.notes}
                        onChange={(e) => setAdjustForm({ ...adjustForm, notes: e.target.value })}
                      />
                    </div>
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-light btn-sm" onClick={() => setShowModal(false)}>{t('বাতিল', 'Cancel')}</button>
                  <button type="submit" className="btn btn-primary btn-sm" disabled={adjustMutation.isPending}>
                    {adjustMutation.isPending ? t('প্রক্রিয়াকরণ হচ্ছে...', 'Posting...') : t('লেজারে যুক্ত করুন', 'Post Ledger Entry')}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
