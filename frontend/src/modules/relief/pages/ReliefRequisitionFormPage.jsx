import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { reliefApi } from '../../../api/reliefApi';
import beneficiaryApi from '../../../api/beneficiaryApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { formatReliefApiError } from '../utils/reliefPermissions';

export const ReliefRequisitionFormPage = () => {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [disasterId, setDisasterId] = useState('');
  const [adminUnitId, setAdminUnitId] = useState('');
  const [requestingOrg, setRequestingOrg] = useState('');
  const [estimatedBeneficiaries, setEstimatedBeneficiaries] = useState(100);
  const [priority, setPriority] = useState('HIGH');
  const [requestDate, setRequestDate] = useState(new Date().toISOString().slice(0, 10));
  const [neededByDate, setNeededByDate] = useState('');
  const [justification, setJustification] = useState('');
  const [supportingDoc, setSupportingDoc] = useState(null);
  const [items, setItems] = useState([{ commodity_id: '', quantity_requested: '', notes: '' }]);
  const [errorMsg, setErrorMsg] = useState(null);

  const { data: lookupsRes, isLoading: loadingLookups } = useQuery({
    queryKey: ['relief', 'lookups'],
    queryFn: () => reliefApi.getLookups(),
  });

  const { data: adminUnitsRes, isLoading: loadingUnits } = useQuery({
    queryKey: ['admin-units', 'all'],
    queryFn: () => beneficiaryApi.getAdministrativeUnits({ per_page: 200 }),
  });

  const mutation = useMutation({
    mutationFn: (formData) => reliefApi.createRequisition(formData),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['relief'] });
      navigate(`/relief/requisitions/${res.data.id}`);
    },
    onError: (err) => {
      setErrorMsg(formatReliefApiError(err, t('চাহিদাপত্র সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save requisition.')));
    },
  });

  if (loadingLookups || loadingUnits) return <Loader />;

  const disasters = lookupsRes?.data?.disasters || [];
  const commodities = lookupsRes?.data?.commodities || [];
  const adminUnits = Array.isArray(adminUnitsRes?.data) ? adminUnitsRes.data : [];

  const updateItem = (idx, field, val) => {
    setItems((prev) => prev.map((it, i) => (i === idx ? { ...it, [field]: val } : it)));
  };

  const handleSubmit = (submitImmediately) => {
    setErrorMsg(null);
    const validItems = items.filter((i) => i.commodity_id && Number(i.quantity_requested) > 0);
    if (!disasterId || !adminUnitId || validItems.length === 0) {
      setErrorMsg(t('দুর্যোগ, এলাকা এবং অন্তত একটি ত্রাণ সামগ্রী নির্বাচন করুন।', 'Select disaster, jurisdiction, and at least one commodity item.'));
      return;
    }

    const fd = new FormData();
    fd.append('disaster_event_id', disasterId);
    fd.append('administrative_unit_id', adminUnitId);
    if (requestingOrg) fd.append('requesting_organization', requestingOrg);
    fd.append('estimated_beneficiaries', String(estimatedBeneficiaries));
    fd.append('priority', priority);
    fd.append('request_date', requestDate);
    if (neededByDate) fd.append('needed_by_date', neededByDate);
    if (justification) fd.append('justification', justification);
    fd.append('submit_immediately', submitImmediately ? '1' : '0');
    fd.append('items', JSON.stringify(validItems));
    if (supportingDoc) fd.append('supporting_doc', supportingDoc);

    mutation.mutate(fd);
  };

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h4 className="fw-bold mb-0">
          <i className="bi bi-plus-circle text-success me-2"></i>
          {t('নতুন ত্রাণ চাহিদাপত্র তৈরি', 'Create Relief Requisition')}
        </h4>
        <Link to="/relief/requisitions" className="btn btn-sm btn-outline-secondary">
          {t('তালিকায় ফিরুন', 'Back to List')}
        </Link>
      </div>

      {errorMsg && <AlertBanner variant="danger" message={errorMsg} onClose={() => setErrorMsg(null)} />}

      <div className="card border-0 shadow-sm">
        <div className="card-body">
          <div className="row g-3 mb-3">
            <div className="col-md-6">
              <label className="form-label fw-semibold small">{t('দুর্যোগ ইভেন্ট *', 'Disaster Event *')}</label>
              <select className="form-select" value={disasterId} onChange={(e) => setDisasterId(e.target.value)}>
                <option value="">{t('দুর্যোগ নির্বাচন করুন...', 'Select disaster...')}</option>
                {disasters.map((d) => (
                  <option key={d.id} value={d.id}>
                    {t(d.name_bn || d.name_en, d.name_en)} ({d.disaster_code})
                  </option>
                ))}
              </select>
            </div>

            <div className="col-md-6">
              <label className="form-label fw-semibold small">{t('চাহিদাকারী প্রশাসনিক এলাকা *', 'Administrative Unit *')}</label>
              <select className="form-select" value={adminUnitId} onChange={(e) => setAdminUnitId(e.target.value)}>
                <option value="">{t('এলাকা নির্বাচন করুন...', 'Select jurisdiction...')}</option>
                {adminUnits.map((u) => (
                  <option key={u.id} value={u.id}>
                    {t(u.name_bn || u.name_en, u.name_en)} ({u.tier_level})
                  </option>
                ))}
              </select>
            </div>

            <div className="col-md-4">
              <label className="form-label fw-semibold small">{t('চাহিদাকারী অফিস / কমিটি', 'Requesting Office / Committee')}</label>
              <input
                type="text"
                className="form-control"
                placeholder={t('যেমন: দাকোপ উপজেলা দুর্যোগ কমিটি', 'e.g. Dacope Upazila DMC')}
                value={requestingOrg}
                onChange={(e) => setRequestingOrg(e.target.value)}
              />
            </div>

            <div className="col-md-2">
              <label className="form-label fw-semibold small">{t('অগ্রাধিকার *', 'Priority *')}</label>
              <select className="form-select" value={priority} onChange={(e) => setPriority(e.target.value)}>
                <option value="LOW">{t('সাধারণ', 'Low')}</option>
                <option value="MEDIUM">{t('মাঝারি', 'Medium')}</option>
                <option value="HIGH">{t('উচ্চ', 'High')}</option>
                <option value="CRITICAL">{t('অতি জরুরি', 'Critical')}</option>
              </select>
            </div>

            <div className="col-md-2">
              <label className="form-label fw-semibold small">{t('অনুমানিত উপকারভোগী *', 'Est. Beneficiaries *')}</label>
              <input
                type="number"
                min="1"
                className="form-control"
                value={estimatedBeneficiaries}
                onChange={(e) => setEstimatedBeneficiaries(Number(e.target.value))}
              />
            </div>

            <div className="col-md-2">
              <label className="form-label fw-semibold small">{t('চাহিদার তারিখ *', 'Request Date *')}</label>
              <input type="date" className="form-control" value={requestDate} onChange={(e) => setRequestDate(e.target.value)} />
            </div>

            <div className="col-md-2">
              <label className="form-label fw-semibold small">{t('প্রয়োজনীয় তারিখ', 'Needed By')}</label>
              <input type="date" className="form-control" value={neededByDate} onChange={(e) => setNeededByDate(e.target.value)} />
            </div>

            <div className="col-md-8">
              <label className="form-label fw-semibold small">{t('চাহিদার যৌক্তিকতা', 'Justification')}</label>
              <textarea
                className="form-control"
                rows={2}
                value={justification}
                onChange={(e) => setJustification(e.target.value)}
              />
            </div>

            <div className="col-md-4">
              <label className="form-label fw-semibold small">{t('সহায়ক নথি (PDF/JPG/PNG)', 'Supporting Doc (PDF/Image)')}</label>
              <input
                type="file"
                className="form-control"
                accept=".pdf,.jpg,.jpeg,.png"
                onChange={(e) => setSupportingDoc(e.target.files?.[0] || null)}
              />
            </div>
          </div>

          <hr />
          <div className="d-flex justify-content-between align-items-center mb-2">
            <h6 className="fw-bold mb-0">{t('ত্রাণ সামগ্রী তালিকা', 'Requested Relief Commodities')}</h6>
            <button
              type="button"
              className="btn btn-sm btn-outline-primary"
              onClick={() => setItems((p) => [...p, { commodity_id: '', quantity_requested: '', notes: '' }])}
            >
              <i className="bi bi-plus me-1"></i>
              {t('আইটেম যোগ করুন', 'Add Item')}
            </button>
          </div>

          {items.map((item, idx) => {
            const selectedCommodity = commodities.find((c) => c.id === item.commodity_id);
            return (
              <div className="row g-2 align-items-end mb-2" key={idx}>
                <div className="col-md-5">
                  <label className="form-label small">{t('পণ্য / সহায়তা *', 'Commodity *')}</label>
                  <select
                    className="form-select form-select-sm"
                    value={item.commodity_id}
                    onChange={(e) => updateItem(idx, 'commodity_id', e.target.value)}
                  >
                    <option value="">{t('পণ্য নির্বাচন...', 'Select commodity...')}</option>
                    {commodities.map((c) => (
                      <option key={c.id} value={c.id}>
                        {t(c.name_bn, c.name_en)} ({c.unit_of_measure})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="col-md-3">
                  <label className="form-label small">
                    {t('পরিমাণ', 'Quantity')} {selectedCommodity ? `(${selectedCommodity.unit_of_measure})` : ''} *
                  </label>
                  <input
                    type="number"
                    min="0.01"
                    step="0.01"
                    className="form-control form-control-sm"
                    value={item.quantity_requested}
                    onChange={(e) => updateItem(idx, 'quantity_requested', e.target.value)}
                  />
                </div>
                <div className="col-md-3">
                  <label className="form-label small">{t('মন্তব্য', 'Notes')}</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    value={item.notes}
                    onChange={(e) => updateItem(idx, 'notes', e.target.value)}
                  />
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

          <div className="d-flex justify-content-end gap-2 mt-4">
            <button
              type="button"
              className="btn btn-outline-secondary"
              disabled={mutation.isPending}
              onClick={() => handleSubmit(false)}
            >
              {t('খসড়া হিসেবে সংরক্ষণ', 'Save as Draft')}
            </button>
            <button
              type="button"
              className="btn btn-success"
              disabled={mutation.isPending}
              onClick={() => handleSubmit(true)}
            >
              <i className="bi bi-send-check me-1"></i>
              {t('চাহিদাপত্র জমা দিন', 'Submit Requisition')}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReliefRequisitionFormPage;
