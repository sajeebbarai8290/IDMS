import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { WarehousePagination } from '../components/WarehousePagination';
import { canIssueWarehouseStock, formatWarehouseApiError } from '../utils/warehousePermissions';

export const WarehouseReliefIssuePage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [selectedAlloc, setSelectedAlloc] = useState(null);
  const [issueQtyMap, setIssueQtyMap] = useState({});
  const [voucherNo, setVoucherNo] = useState('');
  const [notes, setNotes] = useState('');
  const [feedback, setFeedback] = useState(null);

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['warehouse', 'relief-allocations', page],
    queryFn: () => warehouseApi.getReliefAllocations({ page, per_page: 15 }),
  });

  const issueMutation = useMutation({
    mutationFn: ({ allocId, payload }) => warehouseApi.issueAgainstReliefAllocation(allocId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      setSelectedAlloc(null);
      setIssueQtyMap({});
      setVoucherNo('');
      setNotes('');
      setFeedback({
        variant: 'success',
        message: t('ত্রাণ বরাদ্দের বিপরীতে গুদাম থেকে মজুদ সফলভাবে ইস্যু হয়েছে।', 'Stock issued against approved relief allocation and recorded in warehouse ledger.'),
      });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('মজুদ ইস্যু ব্যর্থ হয়েছে।', 'Failed to issue stock.')) });
    },
  });

  const openIssueModal = (alloc) => {
    const defaults = {};
    (alloc.items || []).forEach((it) => {
      const rem = Math.max(0, Number(it.quantity_allocated || 0) - Number(it.quantity_distributed || 0) - Number(it.quantity_cancelled || 0));
      if (rem > 0) defaults[it.id] = rem;
    });
    setIssueQtyMap(defaults);
    setVoucherNo(`${alloc.allocation_order_no}-WH-ISSUE`);
    setNotes('');
    setSelectedAlloc(alloc);
  };

  if (isLoading) return <Loader />;

  const allocations = res?.data || [];
  const meta = res?.meta || {};

  return (
    <div className="container-fluid py-3">
      <div className="mb-3">
        <h4 className="fw-bold mb-1">
          <i className="bi bi-truck text-warning me-2"></i>
          {t('ত্রাণ বরাদ্দের বিপরীতে গুদাম স্টক ইস্যু (Relief Integration)', 'Relief Allocation Stock Issuance')}
        </h4>
        <p className="text-muted small mb-0">
          {t(
            'অনুমোদিত ত্রাণ বরাদ্দ আদেশের সাথে গুদামের সংযোগ: কেবল অবশিষ্ট বরাদ্দের পরিমাণ ইস্যুযোগ্য (ডাবল ডিডাকশন প্রতিরোধে)।',
            'Confirmed stock issuance deducts reserved warehouse lots strictly within remaining allocation quantities to prevent double-deduction.'
          )}
        </p>
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatWarehouseApiError(error, t('বরাদ্দ তালিকা লোড ব্যর্থ।', 'Failed to load allocations.'))} />}

      <div className="card border-0 shadow-sm">
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0 small">
            <thead className="table-light">
              <tr>
                <th>{t('আদেশ নং', 'Allocation Order')}</th>
                <th>{t('উৎস গুদাম ও এলাকা', 'Source Warehouse & Jurisdiction')}</th>
                <th>{t('বরাদ্দকৃত পণ্য ও অবশিষ্ট', 'Items & Remaining')}</th>
                <th>{t('অবস্থা', 'Status')}</th>
                <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
              </tr>
            </thead>
            <tbody>
              {allocations.length === 0 ? (
                <tr><td colSpan="5" className="text-center text-muted py-4">{t('কোনো অনুমোদিত ত্রাণ বরাদ্দ নেই।', 'No sanctioned/dispatched relief allocations found for scoped warehouses.')}</td></tr>
              ) : (
                allocations.map((alloc) => {
                  const hasRemaining = (alloc.items || []).some(
                    (it) => Number(it.quantity_allocated || 0) - Number(it.quantity_distributed || 0) - Number(it.quantity_cancelled || 0) > 0.001
                  );
                  return (
                    <tr key={alloc.id}>
                      <td>
                        <div className="fw-bold"><code>{alloc.allocation_order_no}</code></div>
                        <div className="text-muted">{t(alloc.disaster_event?.name_bn, alloc.disaster_event?.name_en)}</div>
                      </td>
                      <td>
                        <div className="fw-semibold">{t(alloc.warehouse?.warehouse_name_bn || alloc.warehouse?.warehouse_name, alloc.warehouse?.warehouse_name)}</div>
                        <div className="text-muted">{t(alloc.administrative_unit?.name_bn, alloc.administrative_unit?.name_en)}</div>
                      </td>
                      <td>
                        {(alloc.items || []).map((it) => {
                          const rem = Math.max(0, Number(it.quantity_allocated || 0) - Number(it.quantity_distributed || 0) - Number(it.quantity_cancelled || 0));
                          return (
                            <div key={it.id}>
                              {t(it.commodity?.name_bn, it.commodity?.name_en)}: <strong>{it.quantity_allocated}</strong>
                              {' • '}{t('ইস্যুকৃত', 'Issued')}: {it.quantity_distributed}
                              {' • '}<span className="text-primary fw-semibold">{t('অবশিষ্ট', 'Rem')}: {rem.toFixed(2)}</span>
                            </div>
                          );
                        })}
                      </td>
                      <td>
                        <span className={`badge bg-${alloc.status === 'COMPLETED' ? 'success' : alloc.status === 'DISPATCHED' ? 'info' : 'primary'}`}>
                          {alloc.status}
                        </span>
                      </td>
                      <td className="text-end">
                        {canIssueWarehouseStock(user) && ['SANCTIONED', 'DISPATCHED'].includes(alloc.status) && hasRemaining && (
                          <button type="button" className="btn btn-warning btn-sm" onClick={() => openIssueModal(alloc)}>
                            <i className="bi bi-box-arrow-up-right me-1"></i>
                            {t('মজুদ ইস্যু করুন', 'Issue Stock')}
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

      {selectedAlloc && (
        <div className="modal d-block bg-dark bg-opacity-50" tabIndex="-1">
          <div className="modal-dialog modal-lg">
            <div className="modal-content">
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  const items = Object.entries(issueQtyMap)
                    .filter(([, qty]) => Number(qty) > 0)
                    .map(([relief_allocation_item_id, quantity_to_issue]) => ({
                      relief_allocation_item_id,
                      quantity_to_issue: Number(quantity_to_issue),
                    }));
                  issueMutation.mutate({
                    allocId: selectedAlloc.id,
                    payload: {
                      reference_voucher_no: voucherNo,
                      notes,
                      items,
                    },
                  });
                }}
              >
                <div className="modal-header">
                  <h5 className="modal-title">
                    {t('বরাদ্দ আদেশ থেকে মজুদ ইস্যু:', 'Issue Warehouse Stock for')} <code>{selectedAlloc.allocation_order_no}</code>
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setSelectedAlloc(null)} />
                </div>
                <div className="modal-body">
                  <div className="row g-3 mb-3">
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('গেট পাস / ভাউচার নং', 'Issue Gate-Pass / Voucher No')}</label>
                      <input type="text" className="form-control form-control-sm" value={voucherNo} onChange={(e) => setVoucherNo(e.target.value)} />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('মন্তব্য', 'Notes')}</label>
                      <input type="text" className="form-control form-control-sm" value={notes} onChange={(e) => setNotes(e.target.value)} />
                    </div>
                  </div>
                  {(selectedAlloc.items || []).map((it) => {
                    const rem = Math.max(0, Number(it.quantity_allocated || 0) - Number(it.quantity_distributed || 0) - Number(it.quantity_cancelled || 0));
                    if (rem <= 0) return null;
                    return (
                      <div key={it.id} className="row align-items-center mb-2 border rounded p-2 bg-light">
                        <div className="col-md-7">
                          <div className="fw-semibold">{t(it.commodity?.name_bn, it.commodity?.name_en)}</div>
                          <div className="small text-muted">{t('অবশিষ্ট অনুমোদিত পরিমাণ:', 'Remaining available to issue:')} <strong>{rem.toFixed(2)}</strong> {it.commodity?.unit_of_measure}</div>
                        </div>
                        <div className="col-md-5">
                          <input
                            type="number"
                            step="0.01"
                            min="0"
                            max={rem}
                            className="form-control form-control-sm"
                            value={issueQtyMap[it.id] ?? ''}
                            onChange={(e) => setIssueQtyMap({ ...issueQtyMap, [it.id]: e.target.value })}
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-light btn-sm" onClick={() => setSelectedAlloc(null)}>{t('বাতিল', 'Cancel')}</button>
                  <button type="submit" className="btn btn-warning btn-sm" disabled={issueMutation.isPending}>
                    {issueMutation.isPending ? t('ইস্যু হচ্ছে...', 'Issuing...') : t('মজুদ ইস্যু নিশ্চিত করুন', 'Confirm Stock Issuance')}
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
