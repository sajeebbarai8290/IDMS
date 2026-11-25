import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { responseApi } from '../../../api/responseApi';
import beneficiaryApi from '../../../api/beneficiaryApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { formatResponseApiError } from '../utils/responsePermissions';

const OperationFormFields = ({ isEdit, id, initialData, disasters, centers, adminUnits }) => {
  const { t } = useLanguage();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [form, setForm] = useState(() => ({
    disaster_event_id: initialData?.disaster_event_id || '',
    administrative_unit_id: initialData?.administrative_unit_id || '',
    response_center_id: initialData?.response_center_id || '',
    title_en: initialData?.title_en || '',
    title_bn: initialData?.title_bn || '',
    objectives: initialData?.objectives || '',
    description: initialData?.description || '',
    priority: initialData?.priority || 'HIGH',
    severity_level: initialData?.severity_level || 'DANGER',
    start_date: initialData?.start_date || new Date().toISOString().slice(0, 10),
    expected_completion_date: initialData?.expected_completion_date || '',
    lead_organization: initialData?.lead_organization || 'Department of Disaster Management (DDM)',
    commander_name: initialData?.commander_name || '',
  }));
  const [errorMsg, setErrorMsg] = useState(null);

  const mutation = useMutation({
    mutationFn: (payload) =>
      isEdit ? responseApi.updateOperation(id, payload) : responseApi.createOperation(payload),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['response'] });
      navigate(`/response/operations/${res.data.id}`);
    },
    onError: (err) => {
      setErrorMsg(formatResponseApiError(err, t('অভিযান সংরক্ষণ ব্যর্থ হয়েছে।', 'Failed to save operation.')));
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    setErrorMsg(null);
    mutation.mutate({
      ...form,
      response_center_id: form.response_center_id || null,
      expected_completion_date: form.expected_completion_date || null,
    });
  };

  return (
    <>
      {errorMsg && <AlertBanner variant="danger" message={errorMsg} onClose={() => setErrorMsg(null)} />}

      <form className="card border-0 shadow-sm" onSubmit={handleSubmit}>
        <div className="card-body">
          <div className="row g-3">
            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('দুর্যোগ ইভেন্ট *', 'Disaster Event *')}</label>
              <select className="form-select" required value={form.disaster_event_id} onChange={(e) => setForm((p) => ({ ...p, disaster_event_id: e.target.value }))}>
                <option value="">{t('দুর্যোগ নির্বাচন...', 'Select disaster...')}</option>
                {disasters.map((d) => (
                  <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)} ({d.disaster_code})</option>
                ))}
              </select>
            </div>

            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('প্রশাসনিক এলাকা *', 'Jurisdiction *')}</label>
              <select className="form-select" required value={form.administrative_unit_id} onChange={(e) => setForm((p) => ({ ...p, administrative_unit_id: e.target.value }))}>
                <option value="">{t('এলাকা নির্বাচন...', 'Select jurisdiction...')}</option>
                {adminUnits.map((u) => (
                  <option key={u.id} value={u.id}>{t(u.name_bn || u.name_en, u.name_en)} ({u.tier_level})</option>
                ))}
              </select>
            </div>

            <div className="col-md-4">
              <label className="form-label small fw-semibold">{t('সমন্বয় কেন্দ্র (EOC)', 'Response Center (EOC)')}</label>
              <select className="form-select" value={form.response_center_id} onChange={(e) => setForm((p) => ({ ...p, response_center_id: e.target.value }))}>
                <option value="">{t('ঐচ্ছিক কেন্দ্র নির্বাচন...', 'Optional response center...')}</option>
                {centers.map((c) => (
                  <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)} ({c.center_code})</option>
                ))}
              </select>
            </div>

            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('অভিযানের শিরোনাম (ইংরেজি) *', 'Operation Title (EN) *')}</label>
              <input className="form-control" required value={form.title_en} onChange={(e) => setForm((p) => ({ ...p, title_en: e.target.value }))} />
            </div>

            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('অভিযানের শিরোনাম (বাংলা)', 'Operation Title (BN)')}</label>
              <input className="form-control" value={form.title_bn} onChange={(e) => setForm((p) => ({ ...p, title_bn: e.target.value }))} />
            </div>

            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('অগ্রাধিকার *', 'Priority *')}</label>
              <select className="form-select" value={form.priority} onChange={(e) => setForm((p) => ({ ...p, priority: e.target.value }))}>
                <option value="CRITICAL">{t('অতি জরুরি', 'Critical')}</option>
                <option value="HIGH">{t('উচ্চ', 'High')}</option>
                <option value="MEDIUM">{t('মাঝারি', 'Medium')}</option>
                <option value="LOW">{t('সাধারণ', 'Low')}</option>
              </select>
            </div>

            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('তীব্রতা *', 'Severity *')}</label>
              <select className="form-select" value={form.severity_level} onChange={(e) => setForm((p) => ({ ...p, severity_level: e.target.value }))}>
                <option value="ADVISORY">Advisory</option>
                <option value="WARNING">Warning</option>
                <option value="DANGER">Danger</option>
                <option value="GREAT_DANGER">Great Danger</option>
                <option value="CATASTROPHIC">Catastrophic</option>
              </select>
            </div>

            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('শুরুর তারিখ *', 'Start Date *')}</label>
              <input type="date" className="form-control" required value={form.start_date} onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))} />
            </div>

            <div className="col-md-3">
              <label className="form-label small fw-semibold">{t('সম্ভাব্য সমাপ্তি', 'Expected Completion')}</label>
              <input type="date" className="form-control" value={form.expected_completion_date} onChange={(e) => setForm((p) => ({ ...p, expected_completion_date: e.target.value }))} />
            </div>

            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('নেতৃত্বদানকারী সংস্থা *', 'Lead Organization *')}</label>
              <input className="form-control" required value={form.lead_organization} onChange={(e) => setForm((p) => ({ ...p, lead_organization: e.target.value }))} />
            </div>

            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('অপারেশন কমান্ডার / দায়িত্বপ্রাপ্ত কর্মকর্তা', 'Commander / Lead Officer')}</label>
              <input className="form-control" value={form.commander_name} onChange={(e) => setForm((p) => ({ ...p, commander_name: e.target.value }))} />
            </div>

            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('লক্ষ্য ও উদ্দেশ্য', 'Objectives')}</label>
              <textarea className="form-control" rows={2} value={form.objectives} onChange={(e) => setForm((p) => ({ ...p, objectives: e.target.value }))} />
            </div>

            <div className="col-md-6">
              <label className="form-label small fw-semibold">{t('অপারেশন বিবরণ', 'Description')}</label>
              <textarea className="form-control" rows={2} value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} />
            </div>
          </div>

          <div className="d-flex justify-content-end mt-4">
            <button type="submit" className="btn btn-danger" disabled={mutation.isPending}>
              <i className="bi bi-check2-circle me-1"></i>
              {isEdit ? t('পরিবর্তন সংরক্ষণ করুন', 'Update Operation') : t('পরিকল্পিত অভিযান সংরক্ষণ করুন', 'Save Planned Operation')}
            </button>
          </div>
        </div>
      </form>
    </>
  );
};

export const ResponseOperationFormPage = () => {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const { t } = useLanguage();

  const { data: lookupsRes, isLoading: loadingLookups } = useQuery({
    queryKey: ['response', 'lookups'],
    queryFn: () => responseApi.getLookups(),
  });

  const { data: unitsRes, isLoading: loadingUnits } = useQuery({
    queryKey: ['admin-units', 'all'],
    queryFn: () => beneficiaryApi.getAdministrativeUnits({ per_page: 200 }),
  });

  const { data: existingRes, isLoading: loadingExisting } = useQuery({
    queryKey: ['response', 'operations', id],
    queryFn: () => responseApi.getOperation(id),
    enabled: isEdit,
  });

  if (loadingLookups || loadingUnits || (isEdit && loadingExisting)) return <Loader />;

  const disasters = lookupsRes?.data?.disasters || [];
  const centers = lookupsRes?.data?.response_centers || [];
  const adminUnits = Array.isArray(unitsRes?.data) ? unitsRes.data : [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h4 className="fw-bold mb-0">
          <i className="bi bi-plus-circle text-danger me-2"></i>
          {isEdit ? t('দুর্যোগ সাড়াদান অভিযান সম্পাদনা', 'Edit Response Operation') : t('নতুন দুর্যোগ সাড়াদান অভিযান তৈরি', 'Create Response Operation')}
        </h4>
        <Link to={isEdit ? `/response/operations/${id}` : '/response/operations'} className="btn btn-sm btn-outline-secondary">
          {t('ফিরে যান', 'Back')}
        </Link>
      </div>

      <OperationFormFields
        key={existingRes?.data?.id || 'new'}
        isEdit={isEdit}
        id={id}
        initialData={existingRes?.data || null}
        disasters={disasters}
        centers={centers}
        adminUnits={adminUnits}
      />
    </div>
  );
};

export default ResponseOperationFormPage;
