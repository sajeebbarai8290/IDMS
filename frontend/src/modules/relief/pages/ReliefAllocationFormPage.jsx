import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { reliefApi } from '../../../api/reliefApi';
import beneficiaryApi from '../../../api/beneficiaryApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { formatReliefApiError } from '../utils/reliefPermissions';

export const ReliefAllocationFormPage = () => {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [searchParams] = useSearchParams();
  const requisitionId = searchParams.get('requisition_id') || '';

  const [disasterId, setDisasterId] = useState('');
  const [adminUnitId, setAdminUnitId] = useState('');
  const [warehouseId, setWarehouseId] = useState('');
  const [pointId, setPointId] = useState('');
  const [scheduledStart, setScheduledStart] = useState('');
  const [scheduledEnd, setScheduledEnd] = useState('');
  const [duplicateWindowDays, setDuplicateWindowDays] = useState(30);
  const [remarks, setRemarks] = useState('');
  const [items, setItems] = useState([{ commodity_id: '', quantity_allocated: '' }]);
  const [selectedHouseholds, setSelectedHouseholds] = useState([]);
  const [hhSearch, setHhSearch] = useState('');
  const [errorMsg, setErrorMsg] = useState(null);

  const { data: lookupsRes, isLoading: loadingLookups } = useQuery({
    queryKey: ['relief', 'lookups'],
    queryFn: () => reliefApi.getLookups(),
  });

  const { data: adminUnitsRes, isLoading: loadingUnits } = useQuery({
    queryKey: ['admin-units', 'all'],
    queryFn: () => beneficiaryApi.getAdministrativeUnits({ per_page: 200 }),
  });

  const { data: reqDetailRes } = useQuery({
    queryKey: ['relief', 'requisitions', requisitionId],
    queryFn: () => reliefApi.getRequisition(requisitionId),
    enabled: Boolean(requisitionId),
  });

  const { data: hhRes } = useQuery({
    queryKey: ['relief', 'eligible-households', adminUnitId, hhSearch],
    queryFn: () =>
      reliefApi.getEligibleHouseholds({
        administrative_unit_id: adminUnitId || undefined,
        search: hhSearch || undefined,
      }),
    enabled: Boolean(adminUnitId),
  });

  useEffect(() => {
    const req = reqDetailRes?.data;
    if (!req) return;
    setDisasterId(req.disaster_event_id);
    setAdminUnitId(req.administrative_unit_id);
    if (Array.isArray(req.items) && req.items.length > 0) {
      setItems(
        req.items
          .filter((i) => Number(i.quantity_approved) > 0)
          .map((i) => ({
            commodity_id: i.commodity_id,
            quantity_allocated: i.quantity_approved,
          }))
      );
    }
  }, [reqDetailRes]);

  const mutation = useMutation({
    mutationFn: (payload) => reliefApi.createAllocation(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['relief'] });
      navigate(`/relief/allocations/${res.data.id}`);
    },
    onError: (err) => {
      setErrorMsg(formatReliefApiError(err, t('বরাদ্দ আদেশ তৈরি ব্যর্থ হয়েছে।', 'Failed to create allocation order.')));
    },
  });

  if (loadingLookups || loadingUnits) return <Loader />;

  const disasters = lookupsRes?.data?.disasters || [];
  const commodities = lookupsRes?.data?.commodities || [];
  const warehouses = lookupsRes?.data?.warehouses || [];
  const points = lookupsRes?.data?.distribution_points || [];
  const adminUnits = Array.isArray(adminUnitsRes?.data) ? adminUnitsRes.data : [];
  const households = hhRes?.data || [];
  const selectedWarehouse = warehouses.find((w) => w.id === warehouseId);

  const toggleHousehold = (hhId) => {
    setSelectedHouseholds((prev) =>
      prev.includes(hhId) ? prev.filter((id) => id !== hhId) : [...prev, hhId]
    );
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    setErrorMsg(null);
    const validItems = items.filter((i) => i.commodity_id && Number(i.quantity_allocated) > 0);
    if (!disasterId || !adminUnitId || !warehouseId || validItems.length === 0) {
      setErrorMsg(t('দুর্যোগ, এলাকা, উৎস গুদাম এবং অন্তত একটি পণ্য দিন।', 'Select disaster, jurisdiction, warehouse, and at least one commodity item.'));
      return;
    }

    mutation.mutate({
      relief_requisition_id: requisitionId || null,
      disaster_event_id: disasterId,
      administrative_unit_id: adminUnitId,
      distribution_point_id: pointId || null,
      warehouse_id: warehouseId,
      scheduled_start_at: scheduledStart || null,
      scheduled_end_at: scheduledEnd || null,
      duplicate_window_days: Number(duplicateWindowDays || 30),
      remarks: remarks || null,
      items: validItems.map((i) => ({
        commodity_id: i.commodity_id,
        quantity_allocated: Number(i.quantity_allocated),
      })),
      beneficiary_household_ids: selectedHouseholds,
    });
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h4 className="fw-bold mb-0">
          <i className="bi bi-truck text-primary me-2"></i>
          {t('নতুন ত্রাণ বরাদ্দ আদেশ তৈরি', 'Create Relief Allocation Order')}
        </h4>
        <Link to="/relief/allocations" className="btn btn-sm btn-outline-secondary">
          {t('তালিকায় ফিরুন', 'Back to List')}
        </Link>
      </div>

      {errorMsg && <AlertBanner variant="danger" message={errorMsg} onClose={() => setErrorMsg(null)} />}

      <form onSubmit={handleSubmit}>
        <div className="card border-0 shadow-sm mb-3">
          <div className="card-body">
            <div className="row g-3">
              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('দুর্যোগ ইভেন্ট *', 'Disaster Event *')}</label>
                <select className="form-select" value={disasterId} onChange={(e) => setDisasterId(e.target.value)} required>
                  <option value="">{t('দুর্যোগ নির্বাচন...', 'Select disaster...')}</option>
                  {disasters.map((d) => (
                    <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)} ({d.disaster_code})</option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('লক্ষ্য প্রশাসনিক এলাকা *', 'Target Jurisdiction *')}</label>
                <select className="form-select" value={adminUnitId} onChange={(e) => setAdminUnitId(e.target.value)} required>
                  <option value="">{t('এলাকা নির্বাচন...', 'Select jurisdiction...')}</option>
                  {adminUnits.map((u) => (
                    <option key={u.id} value={u.id}>{t(u.name_bn || u.name_en, u.name_en)} ({u.tier_level})</option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('উৎস গুদাম *', 'Source Warehouse *')}</label>
                <select className="form-select" value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)} required>
                  <option value="">{t('গুদাম নির্বাচন...', 'Select warehouse...')}</option>
                  {warehouses.map((w) => (
                    <option key={w.id} value={w.id}>{t(w.warehouse_name_bn || w.warehouse_name, w.warehouse_name)} ({w.tier})</option>
                  ))}
                </select>
              </div>

              <div className="col-md-4">
                <label className="form-label small fw-semibold">{t('বিতরণ কেন্দ্র', 'Distribution Center')}</label>
                <select className="form-select" value={pointId} onChange={(e) => setPointId(e.target.value)}>
                  <option value="">{t('কেন্দ্র নির্বাচন...', 'Optional distribution center...')}</option>
                  {points.map((p) => (
                    <option key={p.id} value={p.id}>{t(p.point_name_bn || p.point_name, p.point_name)}</option>
                  ))}
                </select>
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('বিতরণ শুরুর সময়', 'Scheduled Start')}</label>
                <input type="datetime-local" className="form-control" value={scheduledStart} onChange={(e) => setScheduledStart(e.target.value)} />
              </div>

              <div className="col-md-3">
                <label className="form-label small fw-semibold">{t('বিতরণ শেষের সময়', 'Scheduled End')}</label>
                <input type="datetime-local" className="form-control" value={scheduledEnd} onChange={(e) => setScheduledEnd(e.target.value)} />
              </div>

              <div className="col-md-2">
                <label className="form-label small fw-semibold">{t('ডুপ্লিকেট বিরতি (দিন)', 'Cooldown Days')}</label>
                <input type="number" min="1" max="365" className="form-control" value={duplicateWindowDays} onChange={(e) => setDuplicateWindowDays(e.target.value)} />
              </div>
            </div>

            <hr />
            <div className="d-flex justify-content-between align-items-center mb-2">
              <h6 className="fw-bold mb-0">{t('বরাদ্দকৃত পণ্য তালিকা', 'Allocated Commodity Quantities')}</h6>
              <button
                type="button"
                className="btn btn-sm btn-outline-primary"
                onClick={() => setItems((p) => [...p, { commodity_id: '', quantity_allocated: '' }])}
              >
                + {t('আইটেম যোগ', 'Add Item')}
              </button>
            </div>

            {items.map((item, idx) => {
              const stockInfo = selectedWarehouse?.stock_by_commodity?.[item.commodity_id];
              return (
                <div className="row g-2 align-items-end mb-2" key={idx}>
                  <div className="col-md-5">
                    <label className="form-label small">{t('পণ্য *', 'Commodity *')}</label>
                    <select
                      className="form-select form-select-sm"
                      value={item.commodity_id}
                      onChange={(e) =>
                        setItems((prev) => prev.map((it, i) => (i === idx ? { ...it, commodity_id: e.target.value } : it)))
                      }
                    >
                      <option value="">{t('পণ্য নির্বাচন...', 'Select commodity...')}</option>
                      {commodities.map((c) => (
                        <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)} ({c.unit_of_measure})</option>
                      ))}
                    </select>
                  </div>
                  <div className="col-md-3">
                    <label className="form-label small">{t('বরাদ্দের পরিমাণ *', 'Allocated Qty *')}</label>
                    <input
                      type="number"
                      min="0.01"
                      step="0.01"
                      className="form-control form-control-sm"
                      value={item.quantity_allocated}
                      onChange={(e) =>
                        setItems((prev) => prev.map((it, i) => (i === idx ? { ...it, quantity_allocated: e.target.value } : it)))
                      }
                    />
                  </div>
                  <div className="col-md-3">
                    {stockInfo ? (
                      <span className="badge bg-light text-dark border">
                        {t('অসংরক্ষিত মজুদ:', 'Unreserved Stock:')} {stockInfo.unreserved_quantity}
                      </span>
                    ) : warehouseId && item.commodity_id ? (
                      <span className="badge bg-warning text-dark">{t('মজুদ শূন্য', '0 in stock')}</span>
                    ) : null}
                  </div>
                  <div className="col-md-1 text-end">
                    {items.length > 1 && (
                      <button
                        type="button"
                        className="btn btn-sm btn-outline-danger"
                        onClick={() => setItems((p) => p.filter((_, i) => i !== idx))}
                      >
                        <i className="bi bi-trash"></i>
                      </button>
                    )}
                  </div>
                </div>
              );
            })}

            <div className="mt-3">
              <label className="form-label small fw-semibold">{t('মন্তব্য', 'Remarks')}</label>
              <textarea className="form-control" rows={2} value={remarks} onChange={(e) => setRemarks(e.target.value)} />
            </div>
          </div>
        </div>

        {/* Optional eligible beneficiary pre-selection */}
        {adminUnitId && (
          <div className="card border-0 shadow-sm mb-3">
            <div className="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
              <h6 className="fw-bold mb-0">
                {t('যোগ্য উপকারভোগী পরিবার নির্ধারণ (ঐচ্ছিক)', 'Pre-select Eligible Beneficiary Households (Optional)')}
                <span className="badge bg-primary ms-2">{selectedHouseholds.length}</span>
              </h6>
              <input
                type="text"
                className="form-control form-control-sm"
                style={{ width: '240px' }}
                placeholder={t('কোড বা নাম খুঁজুন...', 'Filter by code or name...')}
                value={hhSearch}
                onChange={(e) => setHhSearch(e.target.value)}
              />
            </div>
            <div className="card-body" style={{ maxHeight: '220px', overflowY: 'auto' }}>
              {households.length === 0 ? (
                <p className="text-muted small mb-0">{t('এই এলাকায় যাচাইকৃত পরিবার পাওয়া যায়নি।', 'No verified eligible households in selected jurisdiction.')}</p>
              ) : (
                <div className="row g-2">
                  {households.map((hh) => (
                    <div className="col-md-4" key={hh.id}>
                      <label className="border rounded p-2 d-flex align-items-center gap-2 small w-100 bg-light">
                        <input
                          type="checkbox"
                          checked={selectedHouseholds.includes(hh.id)}
                          onChange={() => toggleHousehold(hh.id)}
                        />
                        <div>
                          <div className="fw-semibold">{t(hh.hoh_name_bn || hh.hoh_name_en, hh.hoh_name_en)}</div>
                          <div className="text-muted">{hh.household_code} • {t('স্কোর', 'Score')}: {hh.vulnerability_score}</div>
                        </div>
                      </label>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        <div className="d-flex justify-content-end gap-2">
          <button type="submit" className="btn btn-primary" disabled={mutation.isPending}>
            <i className="bi bi-check2-square me-1"></i>
            {t('বরাদ্দ খসড়া সংরক্ষণ করুন', 'Save Draft Allocation')}
          </button>
        </div>
      </form>
    </div>
  );
};

export default ReliefAllocationFormPage;
