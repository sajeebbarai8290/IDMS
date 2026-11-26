import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { WarehousePagination } from '../components/WarehousePagination';
import {
  canReceiveStock,
  canVerifyGrn,
  formatBdt,
  formatWarehouseApiError,
  getTodayIsoDate,
  RECEIPT_STATUS_META,
  triggerWarehouseBlobDownload,
} from '../utils/warehousePermissions';

export const StockReceiptPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [feedback, setFeedback] = useState(null);

  const [form, setForm] = useState(() => ({
    warehouse_id: '',
    supplier_name: '',
    supplier_contact: '',
    purchase_order_no: '',
    received_date: getTodayIsoDate(),
    notes: '',
    attachment: null,
    items: [{ commodity_id: '', batch_number: '', quantity_received: '', unit_cost_bdt: '', expiry_date: '' }],
  }));

  const { data: lookupsRes } = useQuery({
    queryKey: ['warehouse', 'lookups'],
    queryFn: () => warehouseApi.getLookups(),
  });

  const { data: receiptsRes, isLoading, error } = useQuery({
    queryKey: ['warehouse', 'receipts', statusFilter, page],
    queryFn: () => warehouseApi.getReceipts({ page, per_page: 15, ...(statusFilter ? { status: statusFilter } : {}) }),
  });

  const createMutation = useMutation({
    mutationFn: (fd) => warehouseApi.createReceipt(fd),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      setShowModal(false);
      setFeedback({ variant: 'success', message: t('জিআরএন সফলভাবে তৈরি হয়েছে এবং যাচাইয়ের অপেক্ষায় আছে।', 'GRN created and awaiting 4-eye verification before crediting stock.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('জিআরএন তৈরি ব্যর্থ।', 'Failed to create GRN.')) });
    },
  });

  const verifyMutation = useMutation({
    mutationFn: (id) => warehouseApi.verifyReceipt(id, { verification_notes: 'Verified via Warehouse UI' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      setFeedback({ variant: 'success', message: t('জিআরএন যাচাইকৃত হয়েছে এবং স্টক লেজারে যুক্ত হয়েছে।', 'GRN verified and stock credited to warehouse inventory.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('যাচাই ব্যর্থ।', 'Verification failed.')) });
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }) => warehouseApi.rejectReceipt(id, { verification_notes: reason }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      setFeedback({ variant: 'warning', message: t('জিআরএন প্রত্যাখ্যান করা হয়েছে।', 'GRN rejected without crediting stock.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('প্রত্যাখ্যান ব্যর্থ।', 'Rejection failed.')) });
    },
  });

  const handleDownloadAttachment = async (receipt) => {
    try {
      const res = await warehouseApi.downloadReceiptAttachment(receipt.id);
      triggerWarehouseBlobDownload(res.data, receipt.attachment_original_name || `${receipt.receipt_no}.pdf`);
    } catch (err) {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('নথি ডাউনলোড ব্যর্থ।', 'Download failed.')) });
    }
  };

  const handleSubmitGrn = (e) => {
    e.preventDefault();
    const fd = new FormData();
    fd.append('warehouse_id', form.warehouse_id);
    fd.append('supplier_name', form.supplier_name);
    if (form.supplier_contact) fd.append('supplier_contact', form.supplier_contact);
    if (form.purchase_order_no) fd.append('purchase_order_no', form.purchase_order_no);
    fd.append('received_date', form.received_date);
    if (form.notes) fd.append('notes', form.notes);
    if (form.attachment) fd.append('attachment', form.attachment);
    form.items.forEach((item, idx) => {
      fd.append(`items[${idx}][commodity_id]`, item.commodity_id);
      fd.append(`items[${idx}][batch_number]`, item.batch_number);
      fd.append(`items[${idx}][quantity_received]`, item.quantity_received);
      if (item.unit_cost_bdt !== '') fd.append(`items[${idx}][unit_cost_bdt]`, item.unit_cost_bdt);
      if (item.expiry_date) fd.append(`items[${idx}][expiry_date]`, item.expiry_date);
    });
    createMutation.mutate(fd);
  };

  if (isLoading) return <Loader />;

  const receipts = receiptsRes?.data || [];
  const meta = receiptsRes?.meta || {};
  const lookups = lookupsRes?.data || {};
  const warehouses = lookups.warehouses || [];
  const commodities = lookups.commodities || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-box-arrow-in-down text-success me-2"></i>
            {t('ক্রয় ও মালামাল গ্রহণ (Goods Received Notes - GRN)', 'Procurement & Stock Receiving (GRN)')}
          </h4>
          <p className="text-muted small mb-0">
            {t('সরবরাহকারী তথ্য, চালান সংযুক্তি এবং ৪-চক্ষু যাচাইয়ের পর গুদামে মজুদ সংযোজন।', 'Supplier deliveries enter inventory only after independent 4-eye receiving verification.')}
          </p>
        </div>

        {canReceiveStock(user) && (
          <button type="button" className="btn btn-success btn-sm" onClick={() => setShowModal(true)}>
            <i className="bi bi-plus-circle me-1"></i>
            {t('নতুন জিআরএন রেকর্ড করুন', 'Record New GRN')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatWarehouseApiError(error, t('তালিকা লোড ব্যর্থ।', 'Failed to load GRNs.'))} />}

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body py-2">
          <select
            className="form-select form-select-sm"
            style={{ maxWidth: '280px' }}
            value={statusFilter}
            onChange={(e) => {
              setStatusFilter(e.target.value);
              setPage(1);
            }}
          >
            <option value="">{t('সকল অবস্থা', 'All GRN Statuses')}</option>
            {Object.entries(RECEIPT_STATUS_META).map(([k, v]) => (
              <option key={k} value={k}>{t(v.bn, v.en)}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0 small">
            <thead className="table-light">
              <tr>
                <th>{t('জিআরএন নং ও তারিখ', 'GRN No & Date')}</th>
                <th>{t('গুদাম', 'Warehouse')}</th>
                <th>{t('সরবরাহকারী ও পিও', 'Supplier & PO')}</th>
                <th>{t('আইটেমসমূহ', 'Line Items')}</th>
                <th>{t('অবস্থা', 'Status')}</th>
                <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
              </tr>
            </thead>
            <tbody>
              {receipts.length === 0 ? (
                <tr><td colSpan="6" className="text-center text-muted py-4">{t('কোনো জিআরএন পাওয়া যায়নি।', 'No GRNs found.')}</td></tr>
              ) : (
                receipts.map((grn) => {
                  const st = RECEIPT_STATUS_META[grn.status] || { bn: grn.status, en: grn.status, variant: 'secondary' };
                  const canVerifyThis = canVerifyGrn(user) && grn.status === 'PENDING_VERIFICATION' && grn.received_by !== user?.id;
                  return (
                    <tr key={grn.id}>
                      <td>
                        <div className="fw-bold"><code>{grn.receipt_no}</code></div>
                        <div className="text-muted">{grn.received_date}</div>
                      </td>
                      <td>{t(grn.warehouse?.warehouse_name_bn || grn.warehouse?.warehouse_name, grn.warehouse?.warehouse_name)}</td>
                      <td>
                        <div className="fw-semibold">{grn.supplier_name}</div>
                        <div className="text-muted">{grn.purchase_order_no || '—'}</div>
                      </td>
                      <td>
                        {(grn.items || []).map((it) => (
                          <div key={it.id}>
                            {t(it.commodity?.name_bn, it.commodity?.name_en)}: <strong>{it.quantity_received}</strong> {it.commodity?.unit_of_measure} ({it.batch_number})
                            {it.total_value_bdt !== null ? ` • ${formatBdt(it.total_value_bdt)}` : ''}
                          </div>
                        ))}
                      </td>
                      <td>
                        <span className={`badge bg-${st.variant}`}>{t(st.bn, st.en)}</span>
                        {grn.verifier_name && <div className="text-muted mt-1">{grn.verifier_name}</div>}
                      </td>
                      <td className="text-end text-nowrap">
                        {grn.has_attachment && (
                          <button type="button" className="btn btn-outline-secondary btn-sm me-1" onClick={() => handleDownloadAttachment(grn)}>
                            <i className="bi bi-paperclip"></i>
                          </button>
                        )}
                        {canVerifyThis && (
                          <>
                            <button
                              type="button"
                              className="btn btn-success btn-sm me-1"
                              disabled={verifyMutation.isPending}
                              onClick={() => verifyMutation.mutate(grn.id)}
                            >
                              {t('যাচাই ও মজুদযুক্ত', 'Verify')}
                            </button>
                            <button
                              type="button"
                              className="btn btn-outline-danger btn-sm"
                              disabled={rejectMutation.isPending}
                              onClick={() => rejectMutation.mutate({ id: grn.id, reason: 'Quantity/quality mismatch on inspection' })}
                            >
                              {t('প্রত্যাখ্যান', 'Reject')}
                            </button>
                          </>
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

      {showModal && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog modal-xl">
            <div className="modal-content">
              <form onSubmit={handleSubmitGrn}>
                <div className="modal-header">
                  <h5 className="modal-title">{t('নতুন মালামাল গ্রহণ নোট (GRN)', 'Record Incoming Stock Receipt (GRN)')}</h5>
                  <button type="button" className="btn-close" onClick={() => setShowModal(false)} />
                </div>
                <div className="modal-body">
                  <div className="row g-3 mb-3">
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('গ্রহণকারী গুদাম *', 'Receiving Warehouse *')}</label>
                      <select
                        className="form-select form-select-sm"
                        required
                        value={form.warehouse_id}
                        onChange={(e) => setForm({ ...form, warehouse_id: e.target.value })}
                      >
                        <option value="">{t('নির্বাচন করুন...', 'Select warehouse...')}</option>
                        {warehouses.map((w) => <option key={w.id} value={w.id}>{t(w.warehouse_name_bn || w.warehouse_name, w.warehouse_name)}</option>)}
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('সরবরাহকারী *', 'Supplier Name *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={form.supplier_name}
                        onChange={(e) => setForm({ ...form, supplier_name: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('গ্রহণের তারিখ *', 'Received Date *')}</label>
                      <input
                        type="date"
                        className="form-control form-control-sm"
                        required
                        value={form.received_date}
                        onChange={(e) => setForm({ ...form, received_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('পিও / চালান রেফারেন্স', 'PO / Receipt Ref')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={form.purchase_order_no}
                        onChange={(e) => setForm({ ...form, purchase_order_no: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('সরবরাহকারীর যোগাযোগ', 'Supplier Contact')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={form.supplier_contact}
                        onChange={(e) => setForm({ ...form, supplier_contact: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('সংযুক্ত চালান (PDF/Image)', 'Supporting Document')}</label>
                      <input
                        type="file"
                        className="form-control form-control-sm"
                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                        onChange={(e) => setForm({ ...form, attachment: e.target.files?.[0] || null })}
                      />
                    </div>
                  </div>

                  <h6 className="fw-bold small mb-2">{t('গৃহীত পণ্যসমূহ', 'Received Batch Lines')}</h6>
                  {form.items.map((item, idx) => (
                    <div key={idx} className="row g-2 align-items-end mb-2 border rounded p-2 bg-light">
                      <div className="col-md-3">
                        <label className="form-label small mb-1">{t('পণ্য *', 'Commodity *')}</label>
                        <select
                          className="form-select form-select-sm"
                          required
                          value={item.commodity_id}
                          onChange={(e) => {
                            const next = [...form.items];
                            next[idx].commodity_id = e.target.value;
                            setForm({ ...form, items: next });
                          }}
                        >
                          <option value="">{t('নির্বাচন...', 'Select...')}</option>
                          {commodities.map((c) => <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)} ({c.unit_of_measure})</option>)}
                        </select>
                      </div>
                      <div className="col-md-2">
                        <label className="form-label small mb-1">{t('ব্যাচ *', 'Batch *')}</label>
                        <input
                          type="text"
                          className="form-control form-control-sm"
                          required
                          value={item.batch_number}
                          onChange={(e) => {
                            const next = [...form.items];
                            next[idx].batch_number = e.target.value;
                            setForm({ ...form, items: next });
                          }}
                        />
                      </div>
                      <div className="col-md-2">
                        <label className="form-label small mb-1">{t('পরিমাণ *', 'Qty *')}</label>
                        <input
                          type="number"
                          step="0.01"
                          min="0.01"
                          className="form-control form-control-sm"
                          required
                          value={item.quantity_received}
                          onChange={(e) => {
                            const next = [...form.items];
                            next[idx].quantity_received = e.target.value;
                            setForm({ ...form, items: next });
                          }}
                        />
                      </div>
                      <div className="col-md-2">
                        <label className="form-label small mb-1">{t('একক মূল্য (BDT)', 'Unit Cost')}</label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          className="form-control form-control-sm"
                          value={item.unit_cost_bdt}
                          onChange={(e) => {
                            const next = [...form.items];
                            next[idx].unit_cost_bdt = e.target.value;
                            setForm({ ...form, items: next });
                          }}
                        />
                      </div>
                      <div className="col-md-2">
                        <label className="form-label small mb-1">{t('মেয়াদ', 'Expiry')}</label>
                        <input
                          type="date"
                          className="form-control form-control-sm"
                          value={item.expiry_date}
                          onChange={(e) => {
                            const next = [...form.items];
                            next[idx].expiry_date = e.target.value;
                            setForm({ ...form, items: next });
                          }}
                        />
                      </div>
                      <div className="col-md-1 text-end">
                        {form.items.length > 1 && (
                          <button
                            type="button"
                            className="btn btn-outline-danger btn-sm"
                            onClick={() => setForm({ ...form, items: form.items.filter((_, i) => i !== idx) })}
                          >
                            <i className="bi bi-trash"></i>
                          </button>
                        )}
                      </div>
                    </div>
                  ))}
                  <button
                    type="button"
                    className="btn btn-outline-primary btn-sm mt-1"
                    onClick={() =>
                      setForm({
                        ...form,
                        items: [...form.items, { commodity_id: '', batch_number: '', quantity_received: '', unit_cost_bdt: '', expiry_date: '' }],
                      })
                    }
                  >
                    <i className="bi bi-plus me-1"></i>{t('আরও পণ্য যোগ করুন', 'Add Another Line')}
                  </button>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-light btn-sm" onClick={() => setShowModal(false)}>{t('বাতিল', 'Cancel')}</button>
                  <button type="submit" className="btn btn-success btn-sm" disabled={createMutation.isPending}>
                    {createMutation.isPending ? t('জমা হচ্ছে...', 'Submitting...') : t('জিআরএন জমা দিন', 'Submit GRN')}
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
