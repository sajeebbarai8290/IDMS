import React, { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import beneficiaryApi from '../../../api/beneficiaryApi';
import VulnerabilityBadge from '../components/VulnerabilityBadge';
import VerificationStatusBadge from '../components/VerificationStatusBadge';
import PiiRevealModal from '../components/PiiRevealModal';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';

export const BeneficiaryDetailPage = () => {
  const { id } = useParams();
  const queryClient = useQueryClient();

  const [addMemberModal, setAddMemberModal] = useState(false);
  const [memberDraft, setMemberDraft] = useState({
    full_name_en: '',
    full_name_bn: '',
    relationship_to_hoh: 'SPOUSE',
    gender: 'FEMALE',
    age: '',
    has_disability: false,
    is_pregnant_lactating: false,
  });

  const [linkDisasterModal, setLinkDisasterModal] = useState(false);
  const [selectedDisasterId, setSelectedDisasterId] = useState('');
  const [disasterCriteria, setDisasterCriteria] = useState('');

  const [verifyModal, setVerifyModal] = useState(false);
  const [verifyStage, setVerifyStage] = useState('FIELD_SURVEY');
  const [verifyStatus, setVerifyStatus] = useState('VERIFIED');
  const [verifyRemarks, setVerifyRemarks] = useState('');

  const [approvalModal, setApprovalModal] = useState(false);
  const [approvalAction, setApprovalAction] = useState('APPROVE');
  const [approvalRemarks, setApprovalRemarks] = useState('');

  const { data: householdData, isLoading, error } = useQuery({
    queryKey: ['household', id],
    queryFn: async () => {
      const res = await beneficiaryApi.getHousehold(id);
      return res?.household || res?.data?.household || res?.data || res || null;
    },
  });

  const { data: disastersList } = useQuery({
    queryKey: ['disasters'],
    queryFn: async () => {
      const res = await beneficiaryApi.getDisasters();
      const list = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : res?.data?.data || [];
      return list || [];
    },
  });

  // Mutations
  const addMemberMutation = useMutation({
    mutationFn: (data) => beneficiaryApi.addMember(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['household', id]);
      setAddMemberModal(false);
      setMemberDraft({
        full_name_en: '',
        full_name_bn: '',
        relationship_to_hoh: 'CHILD',
        gender: 'MALE',
        age: '',
        has_disability: false,
        is_pregnant_lactating: false,
      });
    },
  });

  const removeMemberMutation = useMutation({
    mutationFn: (memberId) => beneficiaryApi.removeMember(id, memberId),
    onSuccess: () => {
      queryClient.invalidateQueries(['household', id]);
    },
  });

  const verifyStepMutation = useMutation({
    mutationFn: (data) => beneficiaryApi.verifyStep(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['household', id]);
      setVerifyModal(false);
      setVerifyRemarks('');
    },
  });

  const approvalMutation = useMutation({
    mutationFn: (data) => beneficiaryApi.finalApproval(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['household', id]);
      setApprovalModal(false);
      setApprovalRemarks('');
    },
  });

  const linkDisasterMutation = useMutation({
    mutationFn: (data) => beneficiaryApi.linkDisaster(id, data),
    onSuccess: () => {
      queryClient.invalidateQueries(['household', id]);
      setLinkDisasterModal(false);
      setDisasterCriteria('');
    },
  });

  const disburseMutation = useMutation({
    mutationFn: (eligibilityId) => beneficiaryApi.disburseAssistance(id, eligibilityId),
    onSuccess: () => {
      queryClient.invalidateQueries(['household', id]);
    },
  });

  if (isLoading) {
    return <Loader message="উপকারভোগী পরিবারের বিবরণ লোড হচ্ছে..." />;
  }

  if (error || !householdData) {
    return (
      <div className="container py-4">
        <AlertBanner
          type="danger"
          message="বিবরণ লোড করতে ব্যর্থ হয়েছে: "
          details={error?.response?.data?.message || error?.message || 'পরিবারটি খুঁজে পাওয়া যায়নি।'}
        />
        <Link to="/beneficiary/households" className="btn btn-secondary btn-sm mt-3">
          তালিকাভুক্তিতে ফিরে যান
        </Link>
      </div>
    );
  }

  const h = householdData;

  return (
    <div className="container-fluid py-2" style={{ maxWidth: '1200px' }}>
      {/* Breadcrumb & Actions */}
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <nav aria-label="breadcrumb">
            <ol className="breadcrumb mb-1 small">
              <li className="breadcrumb-item">
                <Link to="/beneficiary/dashboard">উপকারভোগী</Link>
              </li>
              <li className="breadcrumb-item">
                <Link to="/beneficiary/households">মাস্টার রেজিস্ট্রি</Link>
              </li>
              <li className="breadcrumb-item active font-monospace" aria-current="page">
                {h.household_code}
              </li>
            </ol>
          </nav>
          <h4 className="fw-bold text-dark mb-0">
            {h.hoh_name_bn || h.hoh_name_en}
            <span className="fs-6 font-monospace text-muted ms-2">({h.household_code})</span>
          </h4>
        </div>

        {/* Action Buttons Toolbar */}
        <div className="d-flex flex-wrap gap-2">
          <PiiRevealModal
            householdId={h.id}
            maskedNid={h.hoh_nid_masked}
            maskedMobile={h.hoh_mobile_masked}
          />

          <Link to={`/beneficiary/households/${h.id}/edit`} className="btn btn-outline-secondary btn-sm">
            <i className="bi bi-pencil me-1"></i> সম্পাদনা
          </Link>

          <button
            type="button"
            className="btn btn-outline-primary btn-sm"
            onClick={() => setVerifyModal(true)}
          >
            <i className="bi bi-check-circle me-1"></i> মাঠ যাচাই (Verify Step)
          </button>

          <button
            type="button"
            className="btn btn-success btn-sm shadow-sm"
            onClick={() => {
              setApprovalAction('APPROVE');
              setApprovalModal(true);
            }}
          >
            <i className="bi bi-patch-check-fill me-1"></i> চূড়ান্ত অনুমোদন
          </button>

          <button
            type="button"
            className="btn btn-outline-danger btn-sm"
            onClick={() => {
              setApprovalAction('REJECT');
              setApprovalModal(true);
            }}
          >
            <i className="bi bi-x-circle me-1"></i> বাতিল
          </button>
        </div>
      </div>

      {/* Duplicate Suspect Warning if any */}
      {h.duplicate_suspects && h.duplicate_suspects.length > 0 && (
        <div className="alert alert-danger shadow-sm py-2 mb-3 d-flex align-items-center">
          <i className="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
          <div className="flex-grow-1">
            <strong>ডুপ্লিকেট সন্দেহ সংকেত:</strong> এই পরিবারের সাথে মাস্টার ডাটাবেজের{' '}
            <strong>{h.duplicate_suspects.length}টি</strong> রেকর্ড হুবহু বা আংশিক মিলে গেছে।
          </div>
          <Link to="/beneficiary/duplicates" className="btn btn-danger btn-sm">
            পর্যালোচনা করুন
          </Link>
        </div>
      )}

      {/* Main Profile Info Row */}
      <div className="row g-3 mb-4">
        {/* Left Card: HoH Details */}
        <div className="col-lg-8">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
              <h5 className="fw-bold mb-0 text-dark">
                <i className="bi bi-person-circle text-primary me-2"></i>
                পরিবারের প্রধানের তথ্য (Head of Household)
              </h5>
              <div className="d-flex gap-2 align-items-center">
                <VerificationStatusBadge status={h.verification?.status} />
                <VulnerabilityBadge score={h.vulnerability?.score} />
              </div>
            </div>
            <div className="card-body pt-0">
              <div className="row g-3">
                <div className="col-md-6">
                  <div className="p-3 bg-light rounded">
                    <small className="text-muted d-block">নাম (ইংরেজি ও বাংলা):</small>
                    <strong className="fs-6 text-dark d-block">{h.hoh_name_en}</strong>
                    <span className="text-secondary">{h.hoh_name_bn || '—'}</span>
                  </div>
                </div>

                <div className="col-md-6">
                  <div className="p-3 bg-light rounded">
                    <small className="text-muted d-block">জাতীয় পরিচয়পত্র (NID):</small>
                    <strong className="fs-6 font-monospace text-dark d-block">
                      {h.hoh_nid || h.hoh_nid_masked}
                    </strong>
                    {h.verification?.nid_verified ? (
                      <span className="text-success small fw-bold">
                        <i className="bi bi-shield-check me-1"></i> এনআইডি যাচাইকৃত
                      </span>
                    ) : (
                      <span className="text-warning-emphasis small">
                        <i className="bi bi-shield-exclamation me-1"></i> যাচাই হয়নি
                      </span>
                    )}
                  </div>
                </div>

                <div className="col-md-4">
                  <small className="text-muted d-block">লিঙ্গ ও জন্ম তারিখ:</small>
                  <strong>{h.hoh_gender}</strong> | {h.hoh_dob || '—'}
                </div>

                <div className="col-md-4">
                  <small className="text-muted d-block">মোবাইল নম্বর:</small>
                  <span className="font-monospace fw-bold">{h.hoh_mobile || h.hoh_mobile_masked || '—'}</span>
                </div>

                <div className="col-md-4">
                  <small className="text-muted d-block">পেশা ও মাসিক আয়:</small>
                  <strong>{h.hoh_occupation || 'দিনমজুর'}</strong> ({h.monthly_income_bdt} টাকা)
                </div>

                <div className="col-md-6">
                  <small className="text-muted d-block">বর্তমান ঠিকানা:</small>
                  <div>{h.present_address}</div>
                  <small className="text-secondary">গ্রাম/ওয়ার্ড: {h.village_ward_name || 'ওয়ার্ড ১'}</small>
                </div>

                <div className="col-md-6">
                  <small className="text-muted d-block">অধিক্ষেত্র (Jurisdiction):</small>
                  <strong className="text-primary">{h.administrative_unit?.name_bn || h.administrative_unit?.name_en}</strong>
                  <div className="small text-muted font-monospace">{h.administrative_unit?.ancestry_path}</div>
                </div>
              </div>

              {/* Vulnerability Badges Tags */}
              <div className="mt-3 pt-3 border-top">
                <small className="text-muted d-block mb-2 fw-bold">শনাক্তকৃত সুরক্ষা নির্দেশক:</small>
                <div className="d-flex flex-wrap gap-2">
                  {h.vulnerability?.is_female_headed && (
                    <span className="badge bg-danger-subtle text-danger border border-danger">
                      <i className="bi bi-gender-female me-1"></i> নারী প্রধান পরিবার
                    </span>
                  )}
                  {h.vulnerability?.has_disabled && (
                    <span className="badge bg-warning-subtle text-warning-emphasis border border-warning">
                      <i className="bi bi-universal-access me-1"></i> প্রতিবন্ধী ব্যক্তি রয়েছে
                    </span>
                  )}
                  {h.vulnerability?.has_elderly && (
                    <span className="badge bg-info-subtle text-info-emphasis border border-info">
                      <i className="bi bi-person-walking me-1"></i> প্রবীণ সদস্য রয়েছে
                    </span>
                  )}
                  {h.vulnerability?.has_children_infants && (
                    <span className="badge bg-primary-subtle text-primary border border-primary">
                      <i className="bi bi-person-arms-up me-1"></i> শিশু সদস্য
                    </span>
                  )}
                  {h.vulnerability?.is_displaced && (
                    <span className="badge bg-danger-subtle text-danger border border-danger">
                      <i className="bi bi-tornado me-1"></i> বাস্তুচ্যুত পরিবার
                    </span>
                  )}
                  <span className="badge bg-light text-secondary border">
                    আয় স্তর: {h.income_category}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Right Card: Location & Verification Summary */}
        <div className="col-lg-4">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white py-3 border-0">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-pin-map-fill text-danger me-2"></i>
                ভৌগোলিক স্থানাঙ্ক ও নিরীক্ষণ
              </h6>
            </div>
            <div className="card-body pt-0">
              <div className="bg-light p-3 rounded mb-3 text-center">
                <div className="font-monospace fs-6 mb-1">
                  Lat: {h.coordinates?.latitude || 'N/A'}, Lng: {h.coordinates?.longitude || 'N/A'}
                </div>
                {h.coordinates?.latitude && h.coordinates?.longitude ? (
                  <a
                    href={`https://www.google.com/maps?q=${h.coordinates.latitude},${h.coordinates.longitude}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="btn btn-outline-primary btn-sm mt-2"
                  >
                    <i className="bi bi-map me-1"></i> গুগল ম্যাপে অবস্থান দেখুন
                  </a>
                ) : (
                  <small className="text-muted">জিপিএস স্থানাঙ্ক সংযুক্ত নেই</small>
                )}
              </div>

              <div className="small">
                <div className="d-flex justify-content-between py-1 border-bottom">
                  <span className="text-muted">নিবন্ধনকারী:</span>
                  <span className="fw-bold">{h.creator?.name || 'অফিসার'}</span>
                </div>
                <div className="d-flex justify-content-between py-1 border-bottom">
                  <span className="text-muted">নিবন্ধন তারিখ:</span>
                  <span>{new Date(h.created_at).toLocaleDateString()}</span>
                </div>
                <div className="d-flex justify-content-between py-1 border-bottom">
                  <span className="text-muted">এনআইডি যাচাই:</span>
                  <span className={h.verification?.nid_verified ? 'text-success fw-bold' : 'text-danger'}>
                    {h.verification?.nid_verified ? 'সম্পন্ন' : 'অসম্পন্ন'}
                  </span>
                </div>
                <div className="d-flex justify-content-between py-1 border-bottom">
                  <span className="text-muted">মাঠ যাচাই:</span>
                  <span className={h.verification?.field_verified ? 'text-success fw-bold' : 'text-warning-emphasis'}>
                    {h.verification?.field_verified ? 'সম্পন্ন' : 'অপেক্ষমান'}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs: Family Members, Verification Trail, Disaster Aid */}
      <div className="card shadow-sm border-0 mb-4">
        <div className="card-header bg-white py-2 border-0">
          <ul className="nav nav-tabs card-header-tabs" role="tablist">
            <li className="nav-item">
              <button className="nav-link active fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#tabMembers">
                <i className="bi bi-people me-2"></i>
                পরিবারের সদস্য তালিকা ({h.members?.length || 0})
              </button>
            </li>
            <li className="nav-item">
              <button className="nav-link fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#tabVerifications">
                <i className="bi bi-clock-history me-2"></i>
                যাচাই ও অনুমোদনের ইতিহাস ({h.verification_history?.length || 0})
              </button>
            </li>
            <li className="nav-item">
              <button className="nav-link fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#tabDisasters">
                <i className="bi bi-tsunami me-2"></i>
                দুর্যোগ ও ত্রাণ সহায়তা ({h.disaster_eligibilities?.length || 0})
              </button>
            </li>
          </ul>
        </div>

        <div className="card-body p-3">
          <div className="tab-content">
            {/* Tab 1: Members */}
            <div className="tab-pane fade show active" id="tabMembers">
              <div className="d-flex justify-content-between align-items-center mb-3">
                <h6 className="fw-bold mb-0">নির্ভরশীল সদস্যদের রোস্টার</h6>
                <button
                  type="button"
                  className="btn btn-outline-primary btn-sm"
                  onClick={() => setAddMemberModal(true)}
                >
                  <i className="bi bi-plus-lg me-1"></i> সদস্য যোগ করুন
                </button>
              </div>

              {h.members && h.members.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0">
                    <thead className="table-light small">
                      <tr>
                        <th>নাম (ইংরেজি ও বাংলা)</th>
                        <th>সম্পর্ক</th>
                        <th>বয়স</th>
                        <th>লিঙ্গ</th>
                        <th>এনআইডি (মাস্কড)</th>
                        <th>প্রতিবন্ধী</th>
                        <th>স্তন্যদাত্রী মা</th>
                        <th className="text-end">অ্যাকশন</th>
                      </tr>
                    </thead>
                    <tbody>
                      {h.members.map((m) => (
                        <tr key={m.id}>
                          <td>
                            <div className="fw-bold">{m.full_name_en}</div>
                            {m.full_name_bn && <small className="text-muted">{m.full_name_bn}</small>}
                          </td>
                          <td>
                            <span className="badge bg-light text-dark border">{m.relationship_to_hoh}</span>
                          </td>
                          <td>{m.age ?? '—'}</td>
                          <td>{m.gender}</td>
                          <td>
                            <code className="text-secondary">{m.nid || m.nid_masked || '—'}</code>
                          </td>
                          <td>
                            {m.has_disability ? (
                              <span className="badge bg-warning text-dark">
                                হ্যাঁ {m.disability_type ? `(${m.disability_type})` : ''}
                              </span>
                            ) : (
                              'না'
                            )}
                          </td>
                          <td>
                            {m.is_pregnant_lactating ? (
                              <span className="badge bg-info text-dark">হ্যাঁ</span>
                            ) : (
                              'না'
                            )}
                          </td>
                          <td className="text-end">
                            <button
                              type="button"
                              className="btn btn-outline-danger btn-sm"
                              title="সদস্য মুছে ফেলুন"
                              onClick={() => removeMemberMutation.mutate(m.id)}
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
                <div className="text-center py-4 text-muted">
                  পরিবারের কোনো নির্ভরশীল সদস্য এখনো যোগ করা হয়নি।
                </div>
              )}
            </div>

            {/* Tab 2: Verification History */}
            <div className="tab-pane fade" id="tabVerifications">
              <h6 className="fw-bold mb-3">যাচাইকরণ কার্যবিবরণী ও অডিট হিস্টোরি</h6>
              {h.verification_history && h.verification_history.length > 0 ? (
                <div className="timeline">
                  {h.verification_history.map((v) => (
                    <div key={v.id} className="p-3 mb-2 rounded border bg-light">
                      <div className="d-flex justify-content-between align-items-center mb-1">
                        <strong className="text-primary">{v.stage}</strong>
                        <span
                          className={`badge ${
                            v.status === 'VERIFIED' ? 'bg-success' : 'bg-danger'
                          }`}
                        >
                          {v.status}
                        </span>
                      </div>
                      <div className="small text-muted mb-1">
                        যাচাইকারী: <strong>{v.verified_by}</strong> •{' '}
                        {v.verified_at ? new Date(v.verified_at).toLocaleString() : ''}
                      </div>
                      {v.remarks && <p className="mb-0 small text-dark">{v.remarks}</p>}
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-4 text-muted">
                  এখনো কোনো যাচাই কার্যক্রম রেকর্ড করা হয়নি।
                </div>
              )}
            </div>

            {/* Tab 3: Disaster Eligibility */}
            <div className="tab-pane fade" id="tabDisasters">
              <div className="d-flex justify-content-between align-items-center mb-3">
                <h6 className="fw-bold mb-0">সংযুক্ত দুর্যোগ ঘোষণা ও ত্রাণ গ্রহণ রেকর্ড</h6>
                <button
                  type="button"
                  className="btn btn-outline-primary btn-sm"
                  onClick={() => setLinkDisasterModal(true)}
                >
                  <i className="bi bi-plus-circle me-1"></i> দুর্যোগে অন্তর্ভুক্ত করুন
                </button>
              </div>

              {h.disaster_eligibilities && h.disaster_eligibilities.length > 0 ? (
                <div className="table-responsive">
                  <table className="table table-hover align-middle mb-0">
                    <thead className="table-light small">
                      <tr>
                        <th>দুর্যোগ কোড</th>
                        <th>দুর্যোগের নাম</th>
                        <th>যোগ্যতা অবস্থা</th>
                        <th className="text-center">ত্রাণ গ্রহণের সংখ্যা</th>
                        <th>সর্বশেষ বিতরণ</th>
                        <th className="text-end">অ্যাকশন</th>
                      </tr>
                    </thead>
                    <tbody>
                      {h.disaster_eligibilities.map((e) => (
                        <tr key={e.id}>
                          <td className="font-monospace fw-bold">{e.disaster?.code}</td>
                          <td>{e.disaster?.name_bn || e.disaster?.name_en}</td>
                          <td>
                            <span className="badge bg-primary-subtle text-primary border border-primary">
                              {e.eligibility_status}
                            </span>
                          </td>
                          <td className="text-center fw-bold fs-6">{e.assistance_count} বার</td>
                          <td>{e.last_assistance_at ? new Date(e.last_assistance_at).toLocaleDateString() : '—'}</td>
                          <td className="text-end">
                            <button
                              type="button"
                              className="btn btn-outline-success btn-sm"
                              onClick={() => disburseMutation.mutate(e.id)}
                              title="ত্রাণ বিতরণ রেকর্ড করুন"
                            >
                              <i className="bi bi-box-seam me-1"></i> ত্রাণ বিতরণ রেকর্ড
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <div className="text-center py-4 text-muted">
                  এই পরিবারটি বর্তমানে কোনো দুর্যোগ তালিকায় অন্তর্ভুক্ত নেই।
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* MODAL: Add Member */}
      {addMemberModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog">
            <div className="modal-content shadow-lg">
              <div className="modal-header">
                <h5 className="modal-title fw-bold">পরিবারের নতুন সদস্য যোগ করুন</h5>
                <button type="button" className="btn-close" onClick={() => setAddMemberModal(false)}></button>
              </div>
              <div className="modal-body">
                <div className="mb-2">
                  <label className="form-label small fw-bold">নাম (ইংরেজি) *</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    value={memberDraft.full_name_en}
                    onChange={(e) => setMemberDraft({ ...memberDraft, full_name_en: e.target.value })}
                    required
                  />
                </div>
                <div className="mb-2">
                  <label className="form-label small fw-bold">নাম (বাংলা)</label>
                  <input
                    type="text"
                    className="form-control form-control-sm"
                    value={memberDraft.full_name_bn}
                    onChange={(e) => setMemberDraft({ ...memberDraft, full_name_bn: e.target.value })}
                  />
                </div>
                <div className="row g-2 mb-2">
                  <div className="col-6">
                    <label className="form-label small fw-bold">সম্পর্ক</label>
                    <select
                      className="form-select form-select-sm"
                      value={memberDraft.relationship_to_hoh}
                      onChange={(e) => setMemberDraft({ ...memberDraft, relationship_to_hoh: e.target.value })}
                    >
                      <option value="SPOUSE">স্ত্রী / স্বামী</option>
                      <option value="CHILD">সন্তান</option>
                      <option value="PARENT">পিতামাতা</option>
                      <option value="SIBLING">ভাই / বোন</option>
                      <option value="OTHER">অন্যান্য</option>
                    </select>
                  </div>
                  <div className="col-3">
                    <label className="form-label small fw-bold">বয়স</label>
                    <input
                      type="number"
                      className="form-control form-control-sm"
                      value={memberDraft.age}
                      onChange={(e) => setMemberDraft({ ...memberDraft, age: e.target.value })}
                    />
                  </div>
                  <div className="col-3">
                    <label className="form-label small fw-bold">লিঙ্গ</label>
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
                </div>

                <div className="mb-2 form-check">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id="chkDisab"
                    checked={memberDraft.has_disability}
                    onChange={(e) => setMemberDraft({ ...memberDraft, has_disability: e.target.checked })}
                  />
                  <label className="form-check-label small" htmlFor="chkDisab">
                    প্রতিবন্ধী ব্যক্তি রয়েছে
                  </label>
                </div>
                <div className="mb-2 form-check">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id="chkPreg"
                    checked={memberDraft.is_pregnant_lactating}
                    onChange={(e) => setMemberDraft({ ...memberDraft, is_pregnant_lactating: e.target.checked })}
                  />
                  <label className="form-check-label small" htmlFor="chkPreg">
                    গর্ভবতী বা স্তন্যদাত্রী মা
                  </label>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setAddMemberModal(false)}>
                  বাতিল
                </button>
                <button
                  type="button"
                  className="btn btn-primary btn-sm"
                  onClick={() => addMemberMutation.mutate(memberDraft)}
                  disabled={addMemberMutation.isLoading}
                >
                  সংরক্ষণ করুন
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: Link Disaster */}
      {linkDisasterModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog">
            <div className="modal-content shadow-lg">
              <div className="modal-header">
                <h5 className="modal-title fw-bold">দুর্যোগ ঘোষণায় পরিবারকে অন্তর্ভুক্ত করুন</h5>
                <button type="button" className="btn-close" onClick={() => setLinkDisasterModal(false)}></button>
              </div>
              <div className="modal-body">
                <div className="mb-3">
                  <label className="form-label small fw-bold">দুর্যোগ নির্বাচন করুন *</label>
                  <select
                    className="form-select"
                    value={selectedDisasterId}
                    onChange={(e) => setSelectedDisasterId(e.target.value)}
                  >
                    <option value="">বাছাই করুন...</option>
                    {disastersList?.map((d) => (
                      <option key={d.id} value={d.id}>
                        {d.disaster_code} - {d.name_bn || d.name_en}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="mb-3">
                  <label className="form-label small fw-bold">অন্তর্ভুক্তির কারণ / ক্ষয়ক্ষতির বর্ণনা</label>
                  <textarea
                    className="form-control"
                    rows="3"
                    placeholder="বাড়িঘর বিধ্বস্ত, আবাদি জমি প্লাবিত..."
                    value={disasterCriteria}
                    onChange={(e) => setDisasterCriteria(e.target.value)}
                  ></textarea>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setLinkDisasterModal(false)}>
                  বাতিল
                </button>
                <button
                  type="button"
                  className="btn btn-primary btn-sm"
                  disabled={!selectedDisasterId || linkDisasterMutation.isLoading}
                  onClick={() =>
                    linkDisasterMutation.mutate({
                      disaster_event_id: selectedDisasterId,
                      eligibility_criteria: disasterCriteria,
                    })
                  }
                >
                  তালিকাভুক্ত করুন
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: Verify Step */}
      {verifyModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog">
            <div className="modal-content shadow-lg">
              <div className="modal-header">
                <h5 className="modal-title fw-bold">মাঠপর্যায়ে যাচাইকরণ রেকর্ড (Verify Step)</h5>
                <button type="button" className="btn-close" onClick={() => setVerifyModal(false)}></button>
              </div>
              <div className="modal-body">
                <div className="mb-3">
                  <label className="form-label small fw-bold">যাচাই ধাপ (Stage)</label>
                  <select
                    className="form-select"
                    value={verifyStage}
                    onChange={(e) => setVerifyStage(e.target.value)}
                  >
                    <option value="FIELD_SURVEY">মাঠ জরিপ (Field Survey)</option>
                    <option value="ADDRESS_CHECK">ঠিকানা যাচাই (Address Check)</option>
                    <option value="NID_CHECK">এনআইডি যাচাই (NID Verification)</option>
                  </select>
                </div>
                <div className="mb-3">
                  <label className="form-label small fw-bold">ফলাফল (Status)</label>
                  <select
                    className="form-select"
                    value={verifyStatus}
                    onChange={(e) => setVerifyStatus(e.target.value)}
                  >
                    <option value="VERIFIED">সঠিক ও অনুমোদিত (Verified)</option>
                    <option value="REJECTED">ত্রুটিপূর্ণ / বাতিল (Rejected)</option>
                  </select>
                </div>
                <div className="mb-3">
                  <label className="form-label small fw-bold">মন্তব্য ও পর্যবেক্ষণ (Remarks)</label>
                  <textarea
                    className="form-control"
                    rows="3"
                    value={verifyRemarks}
                    onChange={(e) => setVerifyRemarks(e.target.value)}
                    placeholder="মাঠ পরিদর্শন সাপেক্ষে মন্তব্য লিখুন..."
                  ></textarea>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setVerifyModal(false)}>
                  বাতিল
                </button>
                <button
                  type="button"
                  className="btn btn-primary btn-sm"
                  disabled={verifyStepMutation.isLoading}
                  onClick={() =>
                    verifyStepMutation.mutate({
                      stage: verifyStage,
                      status: verifyStatus,
                      remarks: verifyRemarks,
                    })
                  }
                >
                  সংরক্ষণ করুন
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* MODAL: Approval / Rejection */}
      {approvalModal && (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
          <div className="modal-dialog">
            <div className={`modal-content shadow-lg border-${approvalAction === 'APPROVE' ? 'success' : 'danger'}`}>
              <div className={`modal-header bg-${approvalAction === 'APPROVE' ? 'success' : 'danger'}-subtle`}>
                <h5 className="modal-title fw-bold">
                  {approvalAction === 'APPROVE' ? 'উপকারভোগী চূড়ান্ত অনুমোদন' : 'উপকারভোগী বাতিলকরণ'}
                </h5>
                <button type="button" className="btn-close" onClick={() => setApprovalModal(false)}></button>
              </div>
              <div className="modal-body">
                <p>
                  আপনি কি নিশ্চিতভাবে এই পরিবারকে ত্রাণ সহায়তার জন্য{' '}
                  <strong className={approvalAction === 'APPROVE' ? 'text-success' : 'text-danger'}>
                    {approvalAction === 'APPROVE' ? 'অনুমোদন' : 'বাতিল'}
                  </strong>{' '}
                  করতে চান?
                </p>
                <div className="mb-3">
                  <label className="form-label small fw-bold">মন্তব্য / সিদ্ধান্তের বিবরণ</label>
                  <textarea
                    className="form-control"
                    rows="3"
                    value={approvalRemarks}
                    onChange={(e) => setApprovalRemarks(e.target.value)}
                    placeholder="অনুমোদনের কারণ বা রেফারেন্স নম্বর লিখুন..."
                  ></textarea>
                </div>
              </div>
              <div className="modal-footer">
                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setApprovalModal(false)}>
                  বাতিল
                </button>
                <button
                  type="button"
                  className={`btn btn-${approvalAction === 'APPROVE' ? 'success' : 'danger'} btn-sm`}
                  disabled={approvalMutation.isLoading}
                  onClick={() =>
                    approvalMutation.mutate({
                      action: approvalAction,
                      remarks: approvalRemarks,
                    })
                  }
                >
                  নিশ্চিত করুন
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default BeneficiaryDetailPage;
