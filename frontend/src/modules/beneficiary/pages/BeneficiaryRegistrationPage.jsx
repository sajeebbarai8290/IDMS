import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import beneficiaryApi from '../../../api/beneficiaryApi';
import GeoLocationPicker from '../components/GeoLocationPicker';

export const BeneficiaryRegistrationPage = () => {
  const navigate = useNavigate();

  const [step, setStep] = useState(1);
  const [adminUnits, setAdminUnits] = useState([]);
  const [loadingUnits, setLoadingUnits] = useState(false);

  // Form State
  const [formData, setFormData] = useState({
    administrative_unit_id: '',
    village_ward_name: '',
    present_address: '',
    permanent_address: '',
    hoh_name_en: '',
    hoh_name_bn: '',
    hoh_nid: '',
    hoh_dob: '',
    hoh_gender: 'MALE',
    hoh_mobile: '',
    hoh_occupation: '',
    monthly_income_bdt: 5000,
    income_category: 'POOR',
    is_female_headed: false,
    has_elderly: false,
    has_disabled: false,
    has_children_infants: false,
    has_pregnant_lactating: false,
    is_displaced: false,
    latitude: 22.602145,
    longitude: 89.531201,
    members: [],
  });

  // Current Member Draft
  const [memberDraft, setMemberDraft] = useState({
    full_name_en: '',
    full_name_bn: '',
    relationship_to_hoh: 'SPOUSE',
    gender: 'FEMALE',
    age: '',
    has_disability: false,
    disability_type: '',
    is_pregnant_lactating: false,
    nid: '',
  });

  const [nidVerifyStatus, setNidVerifyStatus] = useState(null);
  const [nidVerifying, setNidVerifying] = useState(false);
  const [serverErrors, setServerErrors] = useState(null);

  useEffect(() => {
    const fetchAdminUnits = async () => {
      setLoadingUnits(true);
      try {
        const res = await beneficiaryApi.getAdministrativeUnits();
        const list = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : res?.data?.data || [];
        setAdminUnits(list);
        const union = list.find((u) => u.tier_level === 'UNION') || list[0];
        if (union) {
          setFormData((prev) => ({ ...prev, administrative_unit_id: union.id }));
        }
      } catch (err) {
        console.error('Failed to load administrative units:', err);
      } finally {
        setLoadingUnits(false);
      }
    };
    fetchAdminUnits();
  }, []);

  const handleFieldChange = (field, value) => {
    setFormData((prev) => {
      const updated = { ...prev, [field]: value };
      if (field === 'hoh_gender') {
        updated.is_female_headed = value === 'FEMALE';
      }
      return updated;
    });
  };

  const handleVerifyNid = async () => {
    if (!formData.hoh_nid) return;
    setNidVerifying(true);
    setNidVerifyStatus(null);
    try {
      const res = await beneficiaryApi.verifyNid({
        nid_number: formData.hoh_nid,
        date_of_birth: formData.hoh_dob || undefined,
      });
      const isSuccess = res?.success || res?.data?.success;
      const token = res?.verification?.verification_token || res?.data?.verification?.verification_token || 'VERIFIED';
      if (isSuccess) {
        setNidVerifyStatus({
          verified: true,
          message: 'পরিচয়পত্র সফলভাবে যাচাই হয়েছে (Token: ' + token + ')',
        });
      }
    } catch (err) {
      setNidVerifyStatus({
        verified: false,
        message: err.response?.data?.message || 'পরিচয়পত্র যাচাই ব্যর্থ হয়েছে।',
      });
    } finally {
      setNidVerifying(false);
    }
  };

  const handleAddMember = () => {
    if (!memberDraft.full_name_en) {
      alert('সদস্যের নাম আবশ্যক।');
      return;
    }
    setFormData((prev) => ({
      ...prev,
      members: [...prev.members, { ...memberDraft }],
    }));
    setMemberDraft({
      full_name_en: '',
      full_name_bn: '',
      relationship_to_hoh: 'CHILD',
      gender: 'MALE',
      age: '',
      has_disability: false,
      disability_type: '',
      is_pregnant_lactating: false,
      nid: '',
    });
  };

  const handleRemoveMember = (index) => {
    setFormData((prev) => ({
      ...prev,
      members: prev.members.filter((_, i) => i !== index),
    }));
  };

  const submitMutation = useMutation({
    mutationFn: (payload) => beneficiaryApi.createHousehold(payload),
    onSuccess: (res) => {
      const newId = res.data?.household?.id;
      navigate(`/beneficiary/households/${newId}`);
    },
    onError: (err) => {
      const details = err.response?.data?.error?.details || err.response?.data?.errors;
      setServerErrors(details || { general: [err.response?.data?.message || 'নিবন্ধন ব্যর্থ হয়েছে।'] });
    },
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    setServerErrors(null);

    // Prepare payload
    const payload = {
      ...formData,
      monthly_income_bdt: parseFloat(formData.monthly_income_bdt || 0),
      latitude: formData.latitude ? parseFloat(formData.latitude) : null,
      longitude: formData.longitude ? parseFloat(formData.longitude) : null,
      members: formData.members.map((m) => ({
        ...m,
        age: m.age ? parseInt(m.age, 10) : undefined,
      })),
    };

    submitMutation.mutate(payload);
  };

  return (
    <div className="container py-2" style={{ maxWidth: '960px' }}>
      {/* Title */}
      <div className="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-person-vcard text-primary me-2"></i>
            নতুন উপকারভোগী পরিবার নিবন্ধন (Household Registration)
          </h4>
          <p className="text-muted small mb-0">জাতীয় দুর্যোগ মাস্টার ডাটাবেজে নতুন নাগরিক অন্তর্ভুক্তিকরণ</p>
        </div>
        <Link to="/beneficiary/households" className="btn btn-outline-secondary btn-sm">
          <i className="bi bi-arrow-left me-1"></i>
          তালিকায় ফিরে যান
        </Link>
      </div>

      {/* Wizard Steps Navigation */}
      <div className="card shadow-sm border-0 mb-4">
        <div className="card-body p-2">
          <div className="row text-center g-1">
            {[
              { num: 1, label: '১. এলাকা ও অধিক্ষেত্র' },
              { num: 2, label: '২. প্রধানের তথ্য ও এনআইডি' },
              { num: 3, label: '৩. ঝুঁকি ও দুর্বলতা' },
              { num: 4, label: '৪. সদস্য তালিকা' },
              { num: 5, label: '৫. অবস্থান ও সম্পন্ন' },
            ].map((s) => (
              <div key={s.num} className="col">
                <button
                  type="button"
                  className={`btn btn-sm w-100 py-2 ${
                    step === s.num ? 'btn-primary shadow-sm' : step > s.num ? 'btn-light text-success fw-bold' : 'btn-light text-muted'
                  }`}
                  onClick={() => setStep(s.num)}
                >
                  {s.label}
                </button>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Server Validation Errors Alert */}
      {serverErrors && (
        <div className="alert alert-danger shadow-sm py-3 mb-4">
          <h6 className="fw-bold mb-2">
            <i className="bi bi-exclamation-triangle-fill me-2"></i>
            নিবন্ধনে কিছু ভুলত্রুটি পাওয়া গেছে:
          </h6>
          <ul className="mb-0 ps-3">
            {Object.entries(serverErrors).map(([field, messages]) => (
              <li key={field}>
                <strong>{field}:</strong> {Array.isArray(messages) ? messages.join(' ') : messages}
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Registration Wizard Form */}
      <form onSubmit={handleSubmit}>
        {/* STEP 1: Jurisdiction & Addresses */}
        {step === 1 && (
          <div className="card shadow-sm border-0 mb-4">
            <div className="card-header bg-white py-3 border-0">
              <h5 className="fw-bold mb-0 text-primary">
                <i className="bi bi-geo-alt me-2"></i>
                ধাপ ১: ভৌগোলিক অধিক্ষেত্র ও ঠিকানা (Jurisdiction & Address)
              </h5>
            </div>
            <div className="card-body pt-0">
              <div className="row g-3">
                <div className="col-md-6">
                  <label className="form-label fw-bold">
                    ইউনিয়ন বা পৌরসভা (Administrative Unit) <span className="text-danger">*</span>
                  </label>
                  <select
                    className="form-select"
                    value={formData.administrative_unit_id}
                    onChange={(e) => handleFieldChange('administrative_unit_id', e.target.value)}
                    required
                    disabled={loadingUnits}
                  >
                    <option value="">ইউনিয়ন নির্বাচন করুন...</option>
                    {adminUnits.map((u) => (
                      <option key={u.id} value={u.id}>
                        {u.name_bn || u.name_en} ({u.tier_level} - {u.code})
                      </option>
                    ))}
                  </select>
                  <small className="text-muted">যে অধিক্ষেত্রের অধীনে পরিবারটি নিবন্ধিত হবে।</small>
                </div>

                <div className="col-md-6">
                  <label className="form-label fw-bold">গ্রাম / পাড়া / ওয়ার্ড নম্বর</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="e.g. চালনা বাজার, ৩ নং ওয়ার্ড"
                    value={formData.village_ward_name}
                    onChange={(e) => handleFieldChange('village_ward_name', e.target.value)}
                  />
                </div>

                <div className="col-12">
                  <label className="form-label fw-bold">
                    বর্তমান ঠিকানা (Present Address) <span className="text-danger">*</span>
                  </label>
                  <textarea
                    className="form-control"
                    rows="2"
                    placeholder="হোল্ডিং নম্বর, রাস্তার নাম, পোস্ট অফিস, থানা..."
                    value={formData.present_address}
                    onChange={(e) => handleFieldChange('present_address', e.target.value)}
                    required
                  ></textarea>
                </div>

                <div className="col-12">
                  <label className="form-label fw-bold">স্থায়ী ঠিকানা (Permanent Address)</label>
                  <textarea
                    className="form-control"
                    rows="2"
                    placeholder="স্থায়ী ঠিকানা (বর্তমান ঠিকানার অনুরূপ হলে ফাঁকা রাখুন)..."
                    value={formData.permanent_address}
                    onChange={(e) => handleFieldChange('permanent_address', e.target.value)}
                  ></textarea>
                </div>
              </div>

              <div className="text-end mt-4">
                <button type="button" className="btn btn-primary" onClick={() => setStep(2)}>
                  পরবর্তী ধাপ: প্রধানের তথ্য <i className="bi bi-arrow-right ms-1"></i>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* STEP 2: Head of Household & NID */}
        {step === 2 && (
          <div className="card shadow-sm border-0 mb-4">
            <div className="card-header bg-white py-3 border-0">
              <h5 className="fw-bold mb-0 text-primary">
                <i className="bi bi-person-badge me-2"></i>
                ধাপ ২: পরিবারের প্রধানের বিবরণ ও এনআইডি (Head of Household & NID)
              </h5>
            </div>
            <div className="card-body pt-0">
              <div className="row g-3">
                <div className="col-md-6">
                  <label className="form-label fw-bold">
                    প্রধানের নাম (ইংরেজি) <span className="text-danger">*</span>
                  </label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="e.g. Md. Abdul Karim"
                    value={formData.hoh_name_en}
                    onChange={(e) => handleFieldChange('hoh_name_en', e.target.value)}
                    required
                  />
                </div>

                <div className="col-md-6">
                  <label className="form-label fw-bold">
                    প্রধানের নাম (বাংলা) <span className="text-danger">*</span>
                  </label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="e.g. মোঃ আব্দুল করিম"
                    value={formData.hoh_name_bn}
                    onChange={(e) => handleFieldChange('hoh_name_bn', e.target.value)}
                    required
                  />
                </div>

                <div className="col-md-6">
                  <label className="form-label fw-bold">
                    জাতীয় পরিচয়পত্র নম্বর (NID Number) <span className="text-danger">*</span>
                  </label>
                  <div className="input-group">
                    <input
                      type="text"
                      className="form-control font-monospace"
                      placeholder="১০, ১৩ বা ১৭ ডিজিটের এনআইডি"
                      value={formData.hoh_nid}
                      onChange={(e) => handleFieldChange('hoh_nid', e.target.value)}
                      required
                    />
                    <button
                      type="button"
                      className="btn btn-outline-primary"
                      onClick={handleVerifyNid}
                      disabled={nidVerifying || !formData.hoh_nid}
                    >
                      {nidVerifying ? (
                        <>
                          <span className="spinner-border spinner-border-sm me-1"></span>
                          যাচাই হচ্ছে...
                        </>
                      ) : (
                        <>
                          <i className="bi bi-shield-check me-1"></i>
                          এনআইডি যাচাই
                        </>
                      )}
                    </button>
                  </div>
                  {nidVerifyStatus && (
                    <div
                      className={`small mt-1 ${
                        nidVerifyStatus.verified ? 'text-success fw-bold' : 'text-danger'
                      }`}
                    >
                      <i className={`bi ${nidVerifyStatus.verified ? 'bi-check-circle' : 'bi-x-circle'} me-1`}></i>
                      {nidVerifyStatus.message}
                    </div>
                  )}
                </div>

                <div className="col-md-3">
                  <label className="form-label fw-bold">জন্ম তারিখ (Date of Birth)</label>
                  <input
                    type="date"
                    className="form-control"
                    value={formData.hoh_dob}
                    onChange={(e) => handleFieldChange('hoh_dob', e.target.value)}
                  />
                </div>

                <div className="col-md-3">
                  <label className="form-label fw-bold">
                    লিঙ্গ (Gender) <span className="text-danger">*</span>
                  </label>
                  <select
                    className="form-select"
                    value={formData.hoh_gender}
                    onChange={(e) => handleFieldChange('hoh_gender', e.target.value)}
                    required
                  >
                    <option value="MALE">পুরুষ (Male)</option>
                    <option value="FEMALE">নারী (Female)</option>
                    <option value="OTHER">অন্যান্য (Other)</option>
                  </select>
                </div>

                <div className="col-md-4">
                  <label className="form-label fw-bold">মোবাইল ফোন নম্বর</label>
                  <input
                    type="text"
                    className="form-control font-monospace"
                    placeholder="e.g. 01711223344"
                    value={formData.hoh_mobile}
                    onChange={(e) => handleFieldChange('hoh_mobile', e.target.value)}
                  />
                </div>

                <div className="col-md-4">
                  <label className="form-label fw-bold">পেশা (Occupation)</label>
                  <input
                    type="text"
                    className="form-control"
                    placeholder="e.g. দিনমজুর, জেলে, কৃষক"
                    value={formData.hoh_occupation}
                    onChange={(e) => handleFieldChange('hoh_occupation', e.target.value)}
                  />
                </div>

                <div className="col-md-4">
                  <label className="form-label fw-bold">মাসিক গড় আয় (টাকা)</label>
                  <input
                    type="number"
                    className="form-control"
                    value={formData.monthly_income_bdt}
                    onChange={(e) => handleFieldChange('monthly_income_bdt', e.target.value)}
                  />
                </div>
              </div>

              <div className="d-flex justify-content-between mt-4">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setStep(1)}>
                  <i className="bi bi-arrow-left me-1"></i> পূর্ববর্তী
                </button>
                <button type="button" className="btn btn-primary" onClick={() => setStep(3)}>
                  পরবর্তী ধাপ: ঝুঁকি ও দুর্বলতা <i className="bi bi-arrow-right ms-1"></i>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* STEP 3: Vulnerability Indicators */}
        {step === 3 && (
          <div className="card shadow-sm border-0 mb-4">
            <div className="card-header bg-white py-3 border-0">
              <h5 className="fw-bold mb-0 text-primary">
                <i className="bi bi-shield-exclamation me-2"></i>
                ধাপ ৩: ঝুঁকি ও সামাজিক সুরক্ষা নির্দেশক (Vulnerability Profile)
              </h5>
            </div>
            <div className="card-body pt-0">
              <p className="text-muted small mb-3">
                নিচের নির্দেশকগুলোর ভিত্তিতে পরিবারের স্কোর স্বয়ংক্রিয়ভাবে গণনা করা হবে:
              </p>

              <div className="row g-3">
                <div className="col-md-6">
                  <div className="card bg-light border-0 p-3 h-100">
                    <div className="form-check form-switch mb-2">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="chkFemaleHeaded"
                        checked={formData.is_female_headed}
                        onChange={(e) => handleFieldChange('is_female_headed', e.target.checked)}
                      />
                      <label className="form-check-label fw-bold" htmlFor="chkFemaleHeaded">
                        নারী প্রধান পরিবার (Female-Headed Household)
                      </label>
                    </div>
                    <small className="text-muted">
                      পরিবারে কোনো সক্ষম পুরুষ উপার্জনক্ষম ব্যক্তি না থাকলে টিক দিন (+১৫ পয়েন্ট)।
                    </small>
                  </div>
                </div>

                <div className="col-md-6">
                  <div className="card bg-light border-0 p-3 h-100">
                    <div className="form-check form-switch mb-2">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="chkDisplaced"
                        checked={formData.is_displaced}
                        onChange={(e) => handleFieldChange('is_displaced', e.target.checked)}
                      />
                      <label className="form-check-label fw-bold text-danger" htmlFor="chkDisplaced">
                        বাস্তুচ্যুত / আশ্রয়কেন্দ্রে অবস্থানরত (Displaced)
                      </label>
                    </div>
                    <small className="text-muted">
                      ঘূর্ণিঝড় বা বন্যায় বাড়িঘর হারিয়ে সাইক্লোন শেল্টারে আশ্রয় নিলে টিক দিন (+১৫ পয়েন্ট)।
                    </small>
                  </div>
                </div>

                <div className="col-md-6">
                  <div className="card bg-light border-0 p-3 h-100">
                    <div className="form-check form-switch mb-2">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="chkDisabled"
                        checked={formData.has_disabled}
                        onChange={(e) => handleFieldChange('has_disabled', e.target.checked)}
                      />
                      <label className="form-check-label fw-bold" htmlFor="chkDisabled">
                        প্রতিবন্ধী সদস্য রয়েছে (Person with Disability)
                      </label>
                    </div>
                    <small className="text-muted">
                      পরিবারে শারীরিক, দৃষ্টি, বাক বা বুদ্ধি প্রতিবন্ধী ব্যক্তি থাকলে (+২০ পয়েন্ট)।
                    </small>
                  </div>
                </div>

                <div className="col-md-6">
                  <div className="card bg-light border-0 p-3 h-100">
                    <div className="form-check form-switch mb-2">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="chkElderly"
                        checked={formData.has_elderly}
                        onChange={(e) => handleFieldChange('has_elderly', e.target.checked)}
                      />
                      <label className="form-check-label fw-bold" htmlFor="chkElderly">
                        বয়োবৃদ্ধ সদস্য রয়েছে (Elderly &ge; ৬০ বছর)
                      </label>
                    </div>
                    <small className="text-muted">
                      ৬০ বছর বা তদূর্ধ্ব প্রবীণ সদস্য থাকলে টিক দিন (+১৫ পয়েন্ট)।
                    </small>
                  </div>
                </div>

                <div className="col-md-6">
                  <div className="card bg-light border-0 p-3 h-100">
                    <div className="form-check form-switch mb-2">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="chkChildren"
                        checked={formData.has_children_infants}
                        onChange={(e) => handleFieldChange('has_children_infants', e.target.checked)}
                      />
                      <label className="form-check-label fw-bold" htmlFor="chkChildren">
                        শিশু বা দুগ্ধপোষ্য শিশু রয়েছে (&le; ৫ বছর)
                      </label>
                    </div>
                    <small className="text-muted">
                      ৫ বছরের কম বয়সী শিশু থাকলে টিক দিন (+১০ পয়েন্ট)।
                    </small>
                  </div>
                </div>

                <div className="col-md-6">
                  <div className="card bg-light border-0 p-3 h-100">
                    <div className="form-check form-switch mb-2">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="chkPregnant"
                        checked={formData.has_pregnant_lactating}
                        onChange={(e) => handleFieldChange('has_pregnant_lactating', e.target.checked)}
                      />
                      <label className="form-check-label fw-bold" htmlFor="chkPregnant">
                        গর্ভবতী বা স্তন্যদানকারী মা রয়েছে
                      </label>
                    </div>
                    <small className="text-muted">
                      গর্ভবতী বা স্তন্যদাত্রী মা পরিবারে অবস্থান করলে (+১৫ পয়েন্ট)।
                    </small>
                  </div>
                </div>
              </div>

              <div className="d-flex justify-content-between mt-4">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setStep(2)}>
                  <i className="bi bi-arrow-left me-1"></i> পূর্ববর্তী
                </button>
                <button type="button" className="btn btn-primary" onClick={() => setStep(4)}>
                  পরবর্তী ধাপ: সদস্য তালিকা <i className="bi bi-arrow-right ms-1"></i>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* STEP 4: Household Members List */}
        {step === 4 && (
          <div className="card shadow-sm border-0 mb-4">
            <div className="card-header bg-white py-3 border-0">
              <h5 className="fw-bold mb-0 text-primary">
                <i className="bi bi-people me-2"></i>
                ধাপ ৪: পরিবারের সদস্য তালিকা (Dependent Family Members)
              </h5>
            </div>
            <div className="card-body pt-0">
              {/* Members List Table */}
              {formData.members.length > 0 ? (
                <div className="table-responsive mb-4">
                  <table className="table table-bordered align-middle">
                    <thead className="table-light small">
                      <tr>
                        <th>নাম (ইংরেজি/বাংলা)</th>
                        <th>সম্পর্ক</th>
                        <th>বয়স</th>
                        <th>লিঙ্গ</th>
                        <th>প্রতিবন্ধী</th>
                        <th>স্তন্যদাত্রী</th>
                        <th className="text-end">অ্যাকশন</th>
                      </tr>
                    </thead>
                    <tbody>
                      {formData.members.map((m, idx) => (
                        <tr key={idx}>
                          <td>
                            <div className="fw-bold">{m.full_name_en}</div>
                            {m.full_name_bn && <small className="text-muted">{m.full_name_bn}</small>}
                          </td>
                          <td>{m.relationship_to_hoh}</td>
                          <td>{m.age || '—'}</td>
                          <td>{m.gender}</td>
                          <td>{m.has_disability ? <span className="badge bg-warning text-dark">হ্যাঁ</span> : 'না'}</td>
                          <td>{m.is_pregnant_lactating ? <span className="badge bg-info text-dark">হ্যাঁ</span> : 'না'}</td>
                          <td className="text-end">
                            <button
                              type="button"
                              className="btn btn-outline-danger btn-sm"
                              onClick={() => handleRemoveMember(idx)}
                            >
                              <i className="bi bi-trash"></i>
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="alert alert-light border text-center py-3 mb-4 text-muted">
                  এখনো কোনো নির্ভর সদস্য যোগ করা হয়নি। প্রধান ব্যতীত পরিবারের অন্যান্য সদস্যদের নিচে যোগ করুন।
                </div>
              )}

              {/* Add Member Sub-Form */}
              <div className="card border p-3 rounded bg-light mb-3">
                <h6 className="fw-bold mb-3 text-secondary">
                  <i className="bi bi-plus-circle me-1"></i>
                  সদস্য যোগ করুন (Add Member)
                </h6>
                <div className="row g-2">
                  <div className="col-md-4">
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder="নাম (ইংরেজি)"
                      value={memberDraft.full_name_en}
                      onChange={(e) => setMemberDraft({ ...memberDraft, full_name_en: e.target.value })}
                    />
                  </div>
                  <div className="col-md-3">
                    <input
                      type="text"
                      className="form-control form-control-sm"
                      placeholder="নাম (বাংলা)"
                      value={memberDraft.full_name_bn}
                      onChange={(e) => setMemberDraft({ ...memberDraft, full_name_bn: e.target.value })}
                    />
                  </div>
                  <div className="col-md-2">
                    <select
                      className="form-select form-select-sm"
                      value={memberDraft.relationship_to_hoh}
                      onChange={(e) => setMemberDraft({ ...memberDraft, relationship_to_hoh: e.target.value })}
                    >
                      <option value="SPOUSE">স্ত্রী/স্বামী (Spouse)</option>
                      <option value="CHILD">সন্তান (Child)</option>
                      <option value="PARENT">পিতামাতা (Parent)</option>
                      <option value="SIBLING">ভাই/বোন (Sibling)</option>
                      <option value="OTHER">অন্যান্য (Other)</option>
                    </select>
                  </div>
                  <div className="col-md-1">
                    <input
                      type="number"
                      className="form-control form-control-sm"
                      placeholder="বয়স"
                      value={memberDraft.age}
                      onChange={(e) => setMemberDraft({ ...memberDraft, age: e.target.value })}
                    />
                  </div>
                  <div className="col-md-2">
                    <select
                      className="form-select form-select-sm"
                      value={memberDraft.gender}
                      onChange={(e) => setMemberDraft({ ...memberDraft, gender: e.target.value })}
                    >
                      <option value="FEMALE">নারী</option>
                      <option value="MALE">পুরুষ</option>
                      <option value="OTHER">অন্যান্য</option>
                    </select>
                  </div>

                  <div className="col-md-4 mt-2">
                    <div className="form-check form-check-inline">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="chkMemDisability"
                        checked={memberDraft.has_disability}
                        onChange={(e) => setMemberDraft({ ...memberDraft, has_disability: e.target.checked })}
                      />
                      <label className="form-check-label small" htmlFor="chkMemDisability">
                        প্রতিবন্ধী সদস্য
                      </label>
                    </div>
                    <div className="form-check form-check-inline">
                      <input
                        className="form-check-input"
                        type="checkbox"
                        id="chkMemPregnant"
                        checked={memberDraft.is_pregnant_lactating}
                        onChange={(e) => setMemberDraft({ ...memberDraft, is_pregnant_lactating: e.target.checked })}
                      />
                      <label className="form-check-label small" htmlFor="chkMemPregnant">
                        স্তন্যদাত্রী মা
                      </label>
                    </div>
                  </div>

                  <div className="col-md-8 text-end mt-2">
                    <button type="button" className="btn btn-outline-primary btn-sm" onClick={handleAddMember}>
                      <i className="bi bi-plus-lg me-1"></i> সদস্য তালিকায় যোগ করুন
                    </button>
                  </div>
                </div>
              </div>

              <div className="d-flex justify-content-between mt-4">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setStep(3)}>
                  <i className="bi bi-arrow-left me-1"></i> পূর্ববর্তী
                </button>
                <button type="button" className="btn btn-primary" onClick={() => setStep(5)}>
                  পরবর্তী ধাপ: জিপিএস ও রিভিউ <i className="bi bi-arrow-right ms-1"></i>
                </button>
              </div>
            </div>
          </div>
        )}

        {/* STEP 5: GPS Location & Final Submission */}
        {step === 5 && (
          <div className="card shadow-sm border-0 mb-4">
            <div className="card-header bg-white py-3 border-0">
              <h5 className="fw-bold mb-0 text-primary">
                <i className="bi bi-check2-circle me-2"></i>
                ধাপ ৫: ভৌগোলিক অবস্থান ও চূড়ান্ত নিশ্চিতকরণ (GPS & Final Review)
              </h5>
            </div>
            <div className="card-body pt-0">
              {/* GPS Picker */}
              <GeoLocationPicker
                latitude={formData.latitude}
                longitude={formData.longitude}
                onChange={({ latitude, longitude }) => setFormData((prev) => ({ ...prev, latitude, longitude }))}
              />

              {/* Review Summary Card */}
              <div className="card border-0 bg-light p-3 mb-4 rounded">
                <h6 className="fw-bold text-dark border-bottom pb-2 mb-3">
                  <i className="bi bi-card-checklist me-1"></i>
                  নিবন্ধনের সারসংক্ষেপ (Review Summary)
                </h6>
                <div className="row g-2 small">
                  <div className="col-sm-6">
                    <span className="text-muted">প্রধানের নাম:</span>{' '}
                    <strong>{formData.hoh_name_en} ({formData.hoh_name_bn})</strong>
                  </div>
                  <div className="col-sm-6">
                    <span className="text-muted">জাতীয় পরিচয়পত্র:</span>{' '}
                    <code className="text-dark fs-6">{formData.hoh_nid}</code>
                  </div>
                  <div className="col-sm-6">
                    <span className="text-muted">লিঙ্গ ও ফোন:</span>{' '}
                    <strong>{formData.hoh_gender} | {formData.hoh_mobile || 'নাই'}</strong>
                  </div>
                  <div className="col-sm-6">
                    <span className="text-muted">ঠিকানা:</span>{' '}
                    <span>{formData.present_address}</span>
                  </div>
                  <div className="col-sm-6">
                    <span className="text-muted">পরিবারের মোট সদস্য:</span>{' '}
                    <strong>{formData.members.length + 1} জন</strong>
                  </div>
                  <div className="col-sm-6">
                    <span className="text-muted">মাসিক আয়:</span>{' '}
                    <strong>{formData.monthly_income_bdt} টাকা</strong>
                  </div>
                </div>
              </div>

              <div className="d-flex justify-content-between mt-4">
                <button type="button" className="btn btn-outline-secondary" onClick={() => setStep(4)}>
                  <i className="bi bi-arrow-left me-1"></i> পূর্ববর্তী ধাপ
                </button>
                <button
                  type="submit"
                  className="btn btn-success btn-lg px-4 shadow-sm"
                  disabled={submitMutation.isLoading}
                >
                  {submitMutation.isLoading ? (
                    <>
                      <span className="spinner-border spinner-border-sm me-2"></span>
                      সংরক্ষণ ও ঝুঁকি স্কোরিং হচ্ছে...
                    </>
                  ) : (
                    <>
                      <i className="bi bi-check-circle-fill me-2"></i>
                      নিবন্ধন নিশ্চিত করুন (Submit Registration)
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        )}
      </form>
    </div>
  );
};

export default BeneficiaryRegistrationPage;
