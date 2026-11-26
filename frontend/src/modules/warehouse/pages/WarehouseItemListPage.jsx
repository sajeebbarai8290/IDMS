import React, { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { warehouseApi } from '../../../api/warehouseApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { WarehousePagination } from '../components/WarehousePagination';
import { canManageItems, formatWarehouseApiError } from '../utils/warehousePermissions';

const EMPTY_ITEM = {
  relief_category_id: '',
  commodity_code: '',
  name_en: '',
  name_bn: '',
  category: 'FOOD_GRAIN',
  unit_of_measure: 'KG',
  description: '',
  minimum_stock_level: 0,
  is_perishable: false,
  is_active: true,
};

export const WarehouseItemListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();

  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState(EMPTY_ITEM);
  const [feedback, setFeedback] = useState(null);

  const { data: lookupsRes } = useQuery({
    queryKey: ['warehouse', 'lookups'],
    queryFn: () => warehouseApi.getLookups(),
  });

  const { data: res, isLoading, error } = useQuery({
    queryKey: ['warehouse', 'items', search, page],
    queryFn: () => warehouseApi.getItems({ page, per_page: 20, ...(search ? { search } : {}) }),
  });

  const saveMutation = useMutation({
    mutationFn: (payload) => (editingId ? warehouseApi.updateItem(editingId, payload) : warehouseApi.createItem(payload)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['warehouse'] });
      setShowModal(false);
      setEditingId(null);
      setForm(EMPTY_ITEM);
      setFeedback({ variant: 'success', message: t('পণ্য তথ্য সংরক্ষিত হয়েছে।', 'Inventory item saved successfully.') });
    },
    onError: (err) => {
      setFeedback({ variant: 'danger', message: formatWarehouseApiError(err, t('সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save item.')) });
    },
  });

  const openCreate = () => {
    setEditingId(null);
    setForm(EMPTY_ITEM);
    setShowModal(true);
  };

  const openEdit = (item) => {
    setEditingId(item.id);
    setForm({
      relief_category_id: item.relief_category_id || '',
      commodity_code: item.commodity_code,
      name_en: item.name_en,
      name_bn: item.name_bn,
      category: item.category,
      unit_of_measure: item.unit_of_measure,
      description: item.description || '',
      minimum_stock_level: item.minimum_stock_level ?? 0,
      is_perishable: Boolean(item.is_perishable),
      is_active: Boolean(item.is_active),
    });
    setShowModal(true);
  };

  if (isLoading) return <Loader />;

  const items = res?.data || [];
  const meta = res?.meta || {};
  const categories = lookupsRes?.data?.relief_categories || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-tags-fill text-primary me-2"></i>
            {t('ত্রাণ সামগ্রী ও পণ্য ক্যাটালগ (Items)', 'Inventory Item Catalog')}
          </h4>
          <p className="text-muted small mb-0">
            {t('বাংলা ও ইংরেজি নাম, পরিমাপ একক, ন্যূনতম মজুদ সীমা এবং মেয়াদ ট্র্যাকিং।', 'Bilingual commodity catalog with measurement units, minimum stock levels, and expiry tracking.')}
          </p>
        </div>

        {canManageItems(user) && (
          <button type="button" className="btn btn-primary btn-sm" onClick={openCreate}>
            <i className="bi bi-plus-circle me-1"></i>
            {t('নতুন পণ্য যুক্ত করুন', 'Add Inventory Item')}
          </button>
        )}
      </div>

      {feedback && <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />}
      {error && <AlertBanner variant="danger" message={formatWarehouseApiError(error, t('পণ্য তালিকা লোড ব্যর্থ।', 'Failed to load items.'))} />}

      <div className="card border-0 shadow-sm mb-3">
        <div className="card-body py-2">
          <input
            type="text"
            className="form-control form-control-sm"
            style={{ maxWidth: '380px' }}
            placeholder={t('কোড, নাম বা শ্রেণি দিয়ে খুঁজুন...', 'Search by code, Bangla/English name, category...')}
            value={search}
            onChange={(e) => {
              setSearch(e.target.value);
              setPage(1);
            }}
          />
        </div>
      </div>

      <div className="card border-0 shadow-sm">
        <div className="table-responsive">
          <table className="table table-hover align-middle mb-0 small">
            <thead className="table-light">
              <tr>
                <th>{t('কোড', 'Code')}</th>
                <th>{t('নাম (বাংলা ও ইংরেজি)', 'Name (BN / EN)')}</th>
                <th>{t('শ্রেণি ও একক', 'Category & Unit')}</th>
                <th className="text-end">{t('ন্যূনতম সীমা', 'Min Stock Level')}</th>
                <th className="text-end">{t('আওতাভুক্ত উপলব্ধ', 'Scoped Available')}</th>
                <th>{t('মেয়াদ', 'Expiry')}</th>
                <th className="text-end">{t('কার্যক্রম', 'Actions')}</th>
              </tr>
            </thead>
            <tbody>
              {items.length === 0 ? (
                <tr><td colSpan="7" className="text-center text-muted py-4">{t('কোনো পণ্য নেই।', 'No items found.')}</td></tr>
              ) : (
                items.map((item) => {
                  const isLow = item.minimum_stock_level > 0 && (item.total_available_quantity ?? 0) < item.minimum_stock_level;
                  return (
                    <tr key={item.id}>
                      <td><code>{item.commodity_code}</code></td>
                      <td>
                        <div className="fw-bold">{item.name_bn}</div>
                        <div className="text-muted">{item.name_en}</div>
                      </td>
                      <td>
                        <span className="badge bg-light text-dark border me-1">{item.category}</span>
                        <span className="badge bg-info text-dark">{item.unit_of_measure}</span>
                      </td>
                      <td className="text-end">{Number(item.minimum_stock_level || 0).toLocaleString()}</td>
                      <td className="text-end">
                        <span className={`fw-bold ${isLow ? 'text-danger' : 'text-success'}`}>
                          {Number(item.total_available_quantity || 0).toLocaleString()}
                        </span>
                        {isLow && <span className="badge bg-danger ms-1">{t('কম', 'LOW')}</span>}
                      </td>
                      <td>
                        <span className={`badge bg-${item.is_perishable ? 'warning text-dark' : 'secondary'}`}>
                          {item.is_perishable ? t('মেয়াদযুক্ত', 'Perishable') : t('সাধারণ', 'Non-perishable')}
                        </span>
                      </td>
                      <td className="text-end">
                        {canManageItems(user) && (
                          <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => openEdit(item)}>
                            {t('সম্পাদনা', 'Edit')}
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
                  saveMutation.mutate({
                    ...form,
                    relief_category_id: form.relief_category_id || null,
                    minimum_stock_level: Number(form.minimum_stock_level || 0),
                  });
                }}
              >
                <div className="modal-header">
                  <h5 className="modal-title">
                    {editingId ? t('পণ্য তথ্য হালনাগাদ', 'Update Inventory Item') : t('নতুন পণ্য নিবন্ধন', 'New Inventory Item')}
                  </h5>
                  <button type="button" className="btn-close" onClick={() => setShowModal(false)} />
                </div>
                <div className="modal-body">
                  <div className="row g-3">
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('পণ্য কোড *', 'Item Code *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={form.commodity_code}
                        onChange={(e) => setForm({ ...form, commodity_code: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('ত্রাণ শ্রেণি', 'Relief Category')}</label>
                      <select
                        className="form-select form-select-sm"
                        value={form.relief_category_id}
                        onChange={(e) => setForm({ ...form, relief_category_id: e.target.value })}
                      >
                        <option value="">—</option>
                        {categories.map((c) => <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>)}
                      </select>
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('ক্যাটাগরি কোড *', 'Category *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={form.category}
                        onChange={(e) => setForm({ ...form, category: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('নাম (বাংলা) *', 'Name (BN) *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={form.name_bn}
                        onChange={(e) => setForm({ ...form, name_bn: e.target.value })}
                      />
                    </div>
                    <div className="col-md-6">
                      <label className="form-label small fw-semibold">{t('নাম (ইংরেজি) *', 'Name (EN) *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={form.name_en}
                        onChange={(e) => setForm({ ...form, name_en: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('পরিমাপ একক *', 'Unit *')}</label>
                      <input
                        type="text"
                        className="form-control form-control-sm"
                        required
                        value={form.unit_of_measure}
                        onChange={(e) => setForm({ ...form, unit_of_measure: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4">
                      <label className="form-label small fw-semibold">{t('ন্যূনতম মজুদ সীমা', 'Minimum Stock Level')}</label>
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        className="form-control form-control-sm"
                        value={form.minimum_stock_level}
                        onChange={(e) => setForm({ ...form, minimum_stock_level: e.target.value })}
                      />
                    </div>
                    <div className="col-md-4 d-flex align-items-end gap-3">
                      <div className="form-check">
                        <input
                          id="itemPerishChk"
                          type="checkbox"
                          className="form-check-input"
                          checked={form.is_perishable}
                          onChange={(e) => setForm({ ...form, is_perishable: e.target.checked })}
                        />
                        <label htmlFor="itemPerishChk" className="form-check-label small">{t('মেয়াদযুক্ত', 'Perishable')}</label>
                      </div>
                      <div className="form-check">
                        <input
                          id="itemActChk"
                          type="checkbox"
                          className="form-check-input"
                          checked={form.is_active}
                          onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
                        />
                        <label htmlFor="itemActChk" className="form-check-label small">{t('সক্রিয়', 'Active')}</label>
                      </div>
                    </div>
                    <div className="col-12">
                      <label className="form-label small fw-semibold">{t('বিবরণ', 'Description')}</label>
                      <textarea
                        rows="2"
                        className="form-control form-control-sm"
                        value={form.description}
                        onChange={(e) => setForm({ ...form, description: e.target.value })}
                      />
                    </div>
                  </div>
                </div>
                <div className="modal-footer">
                  <button type="button" className="btn btn-light btn-sm" onClick={() => setShowModal(false)}>{t('বাতিল', 'Cancel')}</button>
                  <button type="submit" className="btn btn-primary btn-sm" disabled={saveMutation.isPending}>
                    {saveMutation.isPending ? t('সংরক্ষণ হচ্ছে...', 'Saving...') : t('সংরক্ষণ করুন', 'Save Item')}
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
