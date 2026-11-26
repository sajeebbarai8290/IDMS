import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { WarehousePagination } from '../components/WarehousePagination';
import {
  canApproveTransfer,
  canRequestTransfer,
  formatWarehouseApiError,
  getTodayIsoDate,
  TRANSFER_STATUS_META,
} from '../utils/warehousePermissions';

export const WarehouseTransferPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [feedback, setFeedback] = useState(null);

  const [form, setForm] = useState(() => ({
    source_warehouse_id: '',
    destination_warehouse_id: '',
    requested_date: getTodayIsoDate(),
    expected_delivery_date: '',
    vehicle_info: '',
    notes: '',
    items: [{ commodity_id: '', quantity_requested: '' }],
  }));

  const { data: lookupsRes } = useQuery({
    queryKey: ['warehouse', 'lookups'],
    queryFn: () => warehouseApi.getLookups(),
  });

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['warehouse', 'transfers', statusFilter, page],
    queryFn: () => warehouseApi.getTransfers({ page, per_page: 15, ...(statusFilter ? { status: statusFilter } : {}) }),
  });

  const createMutation = useMutation({
    mutationFn: (payload) => warehouseApi.createTransfer(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      setShowModal(false);
      setFeedback({ variant: 'success', message: t('গুদাম স্থানান্তর অনুরোধ জমা হয়েছে।', 'Inter-warehouse transfer requested.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('স্থানান্তর অনুরোধ ব্যর্থ।', 'Failed to request transfer.')) });
    },
  });

  const actionMutation = useMutation({
    mutationFn: async ({ type, id }) => {
      if (type === 'approve') return warehouseApi.approveTransfer(id);
      if (type === 'dispatch') return warehouseApi.dispatchTransfer(id, { dispatch_reference: `WB-${Date.now().toString().slice(-6)}` });
      if (type === 'receive') return warehouseApi.receiveTransfer(id, { notes: 'Confirmed received at destination warehouse' });
      return warehouseApi.cancelTransfer(id, { cancellation_reason: 'Cancelled by authorizing officer' });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      setFeedback({ variant: 'success', message: t('স্থানান্তর ধাপ সফলভাবে সম্পন্ন হয়েছে।', 'Transfer workflow step completed.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('কার্যক্রম ব্যর্থ হয়েছে।', 'Transfer action failed.')) });
    },
  });

  if (isLoading) return <Loader />;

  const transfers = res?.data || [];
  const meta = res?.meta || {};
  const lookups = lookupsRes?.data || {};
  const warehouses = lookups.warehouses || [];
  const commodities = lookups.commodities || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-arrow-left-right text-primary me-2"></i>
            {t('আন্তঃগুদাম মজুদ স্থানান্তর (Warehouse Transfers)', 'Inter-Warehouse Stock Transfers')}
          </h4>
          <p className="text-muted small mb-0">
            {t('অনুরোধ → ৪-চক্ষু অনুমোদন → উৎস গুদাম থেকে প্রেরণ → গন্তব্য গুদামে গ্রহণ নিশ্চিতকরণ।', 'Controlled 4-step workflow: Request -> 4-Eye Approval -> Source Dispatch -> Destination Receipt.')}
          </p>
        </div>

        {canRequestTransfer(user) && (
          <button type="button" className="btn btn-primary btn-sm" onClick={() => setShowModal(true)}>
            <i className="bi bi-plus-circle me-1"></i>
            {t('নতুন স্থানান্তর অনুরোধ', 'New Transfer Request')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatWarehouseApiError(error, t('তালিকা লোড ব্যর্থ।', 'Failed to load transfers.'))} />}

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
            <option value="">{t('সকল অবস্থা', 'All Transfer Statuses')}</option>
            {Object.entries(TRANSFER_STATUS_META).map(([k, v]) => (
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
                <th>{t('স্থানান্তর নং ও তারিখ', 'Transfer No')}</th>
                <th>{t('উৎস → গন্তব্য গুদাম', 'Source -> Destination')}</th>
                <th>{t('পণ্য ও পরিমাণ', 'Items')}</th>
                <th>{t('অবস্থা', 'Status')}</th>
                <th className="text-end">{t('কার্যক্রম', 'Workflow Actions')}</th>
              </tr>
            </thead>
            <tbody>
              {transfers.length === 0 ? (
                <tr><td colSpan="5" className="text-center text-muted py-4">{t('কোনো স্থানান্তর রেকর্ড নেই।', 'No warehouse transfers found.')}</td></tr>
              ) : (
                transfers.map((trf) => {
                  const st = TRANSFER_STATUS_META[trf.status] || { bn: trf.status, en: trf.status, variant: 'secondary' };
                  const canApproveThis = canApproveTransfer(user) && trf.status === 'REQUESTED' && trf.requested_by !== user?.id;
                  const canDispatchThis = canRequestTransfer(user) && trf.status === 'APPROVED';
                  const canReceiveThis = canRequestTransfer(user) && trf.status === 'DISPATCHED';
                  const canCancelThis = canApproveTransfer(user) && ['REQUESTED', 'APPROVED'].includes(trf.status);

                  return (
                    <tr key={trf.id}>
                      <td>
                        <div className="fw-bold"><code>{trf.transfer_no}</code></div>
                        <div className="text-muted">{trf.requested_date}</div>
                      </td>
                      <td>
                        <div>
                          <span className="fw-semibold">{t(trf.source_warehouse?.warehouse_name_bn || trf.source_warehouse?.warehouse_name, trf.source_warehouse?.warehouse_name)}</span>
                          <i className="bi bi-arrow-right mx-2 text-primary"></i>
                          <span className="fw-semibold">{t(trf.destination_warehouse?.warehouse_name_bn || trf.destination_warehouse?.warehouse_name, trf.destination_warehouse?.warehouse_name)}</span>
                        </div>
                        {trf.vehicle_info && <div className="text-muted">{trf.vehicle_info}</div>}
                      </td>
                      <td>
                        {(trf.items || []).map((it) => (
                          <div key={it.id}>
                            {t(it.commodity?.name_bn, it.commodity?.name_en)}: <strong>{it.quantity_requested}</strong>
                            {it.quantity_dispatched > 0 && ` / ${t('প্রেরিত', 'sent')} ${it.quantity_dispatched}`}
                            {it.quantity_received > 0 && ` / ${t('গৃহীত', 'rcvd')} ${it.quantity_received}`}
                          </div>
                        ))}
                      </td>
                      <td>
                        <span className={`badge bg-${st.variant}`}>{t(st.bn, st.en)}</span>
                        {trf.approver_name && <div className="text-muted mt-1">{t('অনুমোদক', 'Approved by')}: {trf.approver_name}</div>}
                      </td>
                      <td className="text-end text-nowrap">
                        {canApproveThis && (
                          <button
                            type="button"
                            className="btn btn-primary btn-sm me-1"
                            disabled={actionMutation.isPending}
                            onClick={() => actionMutation.mutate({ type: 'approve', id: trf.id })}
                          >
                            {t('অনুমোদন', 'Approve')}
                          </button>
                        )}
                        {canDispatchThis && (
                          <button
                            type="button"
                            className="btn btn-info btn-sm me-1 text-white"
                            disabled={actionMutation.isPending}
                            onClick={() => actionMutation.mutate({ type: 'dispatch', id: trf.id })}
                          >
                            {t('প্রেরণ করুন', 'Dispatch')}
                          </button>
                        )}
                        {canReceiveThis && (
                          <button
                            type="button"
                            className="btn btn-success btn-sm me-1"
                            disabled={actionMutation.isPending}
                            onClick={() => actionMutation.mutate({ type: 'receive', id: trf.id })}
                          >
                            {t('গ্রহণ নিশ্চিত', 'Confirm Receipt')}
                          </button>
                        )}
                        {canCancelThis && (
                          <button
                            type="button"
                            className="btn btn-outline-danger btn-sm"
                            disabled={actionMutation.isPending}
                            onClick={() => actionMutation.mutate({ type: 'cancel', id: trf.id })}
                          >
                            {t('বাতিল', 'Cancel')}
                          </button>
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
          <div className="modal-dialog modal-lg">
            <div className="modal-content">
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  createMutation.mutate({
                    ...form,
                    expected_delivery_date: form.expected_delivery_date || null,
                    items: form.items.map((it) => ({
                      commodity_id: it.commodity_id,
                      quantity_requested: Number(it.quantity_requested),
                    })),
                  });
                }}
              >
                <div className="modal-header">
                  <h5 className="modal-title">{t('নতুন আন্তঃগুদাম স্থানান্তর অনুরোধ', 'New Warehouse Transfer Request')}</h5>
                  <button type="button" className="btn-close" onClick={() => setShowModal(false)} />
                </div>
                <div className="modal-body">
                  <div className="row g-3 mb-3">
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('উৎস গুদাম *', 'Source Warehouse *')}</label>
                      <select
                        className="form-select form-select-sm"
                        required
                        value={form.source_warehouse_id}
                        onChange={(e) => setForm({ ...form, source_warehouse_id: e.target.value })}
                      >
                        <option value="">{t('নির্বাচন...', 'Select source...')}</option>
                        {warehouses.map((w) => <option key={w.id} value={w.id}>{t(w.warehouse_name_bn || w.warehouse_name, w.warehouse_name)}</option>)}
                      </select>
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('গন্তব্য গুদাম *', 'Destination Warehouse *')}</label>
                      <select
                        className="form-select form-select-sm"
                        required
                        value={form.destination_warehouse_id}
                        onChange={(e) => setForm({ ...form, destination_warehouse_id: e.target.value })}
                      >
                        <option value="">{t('নির্বাচন...', 'Select destination...')}</option>
                        {warehouses.map((w) => <option key={w.id} value={w.id}>{t(w.warehouse_name_bn || w.warehouse_name, w.warehouse_name)}</option>)}
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('অনুরোধের তারিখ *', 'Request Date *')}</label>
                      <input
                        type="date"
                        className="form-control form-control-sm"
                        required
                        value={form.requested_date}
                        onChange={(e) => setForm({ ...form, requested_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('সম্ভাব্য পৌঁছানোর তারিখ', 'Expected Delivery')}</label>
                      <input
                        type="date"
                        className="form-control form-control-sm"
                        value={form.expected_delivery_date}
                        onChange={(e) => setForm({ ...form, expected_delivery_date: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('যানবাহন / কনভয়', 'Vehicle Info')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        value={form.vehicle_info}
                        onChange={(e) => setForm({ ...form, vehicle_info: e.target.value })}
                      />
                    </div>
                  </div>

                  {form.items.map((item, idx) => (
                    <div key={idx} className="row g-2 align-items-end mb-2">
                      <div className="col-md-7">
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
                      <div className="col-md-4">
                        <label className="form-label small mb-1">{t('পরিমাণ *', 'Quantity *')}</label>
                        <input
                          type="number"
                          step="0.01"
                          min="0.01"
                          className="form-control form-control-sm"
                          required
                          value={item.quantity_requested}
                          onChange={(e) => {
                            const next = [...form.items];
                            next[idx].quantity_requested = e.target.value;
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
                    className="btn btn-outline-primary btn-sm"
                    onClick={() => setForm({ ...form, items: [...form.items, { commodity_id: '', quantity_requested: '' }] })}
                  >
                    <i className="bi bi-plus me-1"></i>{t('পণ্য যোগ করুন', 'Add Commodity')}
                  </button>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-light btn-sm" onClick={() => setShowModal(false)}>{t('বাতিল', 'Cancel')}</button>
                  <button type="submit" className="btn btn-primary btn-sm" disabled={createMutation.isPending}>
                    {createMutation.isPending ? t('জমা হচ্ছে...', 'Submitting...') : t('অনুরোধ পাঠান', 'Submit Request')}
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
