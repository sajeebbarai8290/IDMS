import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import beneficiaryApi from '../../../api/beneficiaryApi';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';

export const BeneficiaryDashboardPage = () => {
  const {
    data: metricsData,
    isLoading: loadingMetrics,
    error: metricsError,
  } = useQuery({
    queryKey: ['beneficiaryMetrics'],
    queryFn: async () => {
      const res = await beneficiaryApi.getDashboardMetrics();
      return res?.data?.metrics || res?.metrics || res?.data || res || {};
    },
    refetchInterval: 30000,
  });

  const { data: geoData, isLoading: loadingGeo } = useQuery({
    queryKey: ['beneficiaryGeo'],
    queryFn: async () => {
      const res = await beneficiaryApi.getGeoBreakdown();
      const list = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : res?.data?.data || [];
      return list || [];
    },
  });

  const { data: disasterData, isLoading: loadingDisaster } = useQuery({
    queryKey: ['beneficiaryDisasters'],
    queryFn: async () => {
      const res = await beneficiaryApi.getDisasterBreakdown();
      const list = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : res?.data?.data || [];
      return list || [];
    },
  });

  if (loadingMetrics || loadingGeo || loadingDisaster) {
    return <Loader message="উপকারভোগী ড্যাশবোর্ড লোড হচ্ছে..." />;
  }

  if (metricsError) {
    return (
      <AlertBanner
        type="danger"
        message="ড্যাশবোর্ড ডেটা লোড করতে সমস্যা হয়েছে: "
        details={metricsError.message}
      />
    );
  }

  const m = metricsData || {};

  return (
    <div className="container-fluid py-2">
      {/* Page Title & Top Actions */}
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-people-fill text-primary me-2"></i>
            উপকারভোগী ব্যবস্থাপনা ড্যাশবোর্ড (Beneficiary Dashboard)
          </h4>
          <p className="text-muted small mb-0">
            জাতীয় ত্রাণ ও সামাজিক নিরাপত্তা মাস্টার ডাটাবেজ • গণপ্রজাতন্ত্রী বাংলাদেশ সরকার
          </p>
        </div>
        <div className="d-flex flex-wrap gap-2">
          <Link to="/beneficiary/households" className="btn btn-outline-primary btn-sm">
            <i className="bi bi-list-ul me-1"></i>
            সম্পূর্ণ তালিকা (Master List)
          </Link>
          <Link to="/beneficiary/register" className="btn btn-primary btn-sm shadow-sm">
            <i className="bi bi-person-plus-fill me-1"></i>
            নতুন পরিবার নিবন্ধন
          </Link>
          <Link to="/beneficiary/import-export" className="btn btn-outline-success btn-sm">
            <i className="bi bi-file-earmark-excel me-1"></i>
            ইমপোর্ট / এক্সপোর্ট
          </Link>
          <Link to="/beneficiary/duplicates" className="btn btn-outline-danger btn-sm position-relative">
            <i className="bi bi-person-exclamation me-1"></i>
            ডুপ্লিকেট পর্যালোচনা
            {m.pending_duplicate_reviews > 0 && (
              <span className="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                {m.pending_duplicate_reviews}
              </span>
            )}
          </Link>
        </div>
      </div>

      {/* Pending Duplicate Alert Banner */}
      {m.pending_duplicate_reviews > 0 && (
        <div className="alert alert-danger d-flex align-items-center shadow-sm py-2 mb-4">
          <i className="bi bi-exclamation-octagon-fill fs-4 me-3"></i>
          <div className="flex-grow-1">
            <strong>সতর্কতা:</strong> মাস্টার ডাটাবেজে <strong>{m.pending_duplicate_reviews}টি</strong> সন্দেহভাজন
            ডুপ্লিকেট নাগরিক রেকর্ড পর্যালোচনা ও সিদ্ধান্তের অপেক্ষায় রয়েছে।
          </div>
          <Link to="/beneficiary/duplicates" className="btn btn-danger btn-sm">
            এখনই যাচাই করুন
          </Link>
        </div>
      )}

      {/* KPI Stat Cards */}
      <div className="row g-3 mb-4">
        {/* Total Households */}
        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-primary h-100">
            <div className="card-body p-3">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <small className="text-muted text-uppercase fw-bold">মোট নিবন্ধিত পরিবার</small>
                  <h3 className="fw-bold mb-0 text-dark">{m.total_households?.toLocaleString() || 0}</h3>
                  <small className="text-muted">মোট সদস্য: {m.total_individuals?.toLocaleString() || 0} জন</small>
                </div>
                <div className="rounded-circle bg-primary-subtle text-primary p-3">
                  <i className="bi bi-house-door-fill fs-3"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Approved Beneficiaries */}
        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-success h-100">
            <div className="card-body p-3">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <small className="text-muted text-uppercase fw-bold">চূড়ান্ত অনুমোদিত</small>
                  <h3 className="fw-bold mb-0 text-success">{m.verification?.approved?.toLocaleString() || 0}</h3>
                  <small className="text-muted">ত্রাণ পাওয়ার জন্য প্রস্তুত</small>
                </div>
                <div className="rounded-circle bg-success-subtle text-success p-3">
                  <i className="bi bi-patch-check-fill fs-3"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* High Vulnerability */}
        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-danger h-100">
            <div className="card-body p-3">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <small className="text-muted text-uppercase fw-bold">উচ্চ ঝুঁকিপূর্ণ পরিবার</small>
                  <h3 className="fw-bold mb-0 text-danger">
                    {m.vulnerability?.high_vulnerability_households?.toLocaleString() || 0}
                  </h3>
                  <small className="text-muted">ভুলনারেবিলিটি স্কোর &ge; ৭০</small>
                </div>
                <div className="rounded-circle bg-danger-subtle text-danger p-3">
                  <i className="bi bi-shield-shaded fs-3"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Pending Verification */}
        <div className="col-xl-3 col-md-6">
          <div className="card shadow-sm border-0 border-start border-4 border-warning h-100">
            <div className="card-body p-3">
              <div className="d-flex justify-content-between align-items-center">
                <div>
                  <small className="text-muted text-uppercase fw-bold">যাচাই অপেক্ষমান</small>
                  <h3 className="fw-bold mb-0 text-warning-emphasis">
                    {m.verification?.pending?.toLocaleString() || 0}
                  </h3>
                  <small className="text-muted">
                    মাঠ যাচাইকৃত: {m.verification?.field_verified?.toLocaleString() || 0}
                  </small>
                </div>
                <div className="rounded-circle bg-warning-subtle text-warning-emphasis p-3">
                  <i className="bi bi-clock-history fs-3"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Vulnerability & Inclusion Grid */}
      <div className="row g-3 mb-4">
        <div className="col-lg-6">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white py-3 border-0">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-graph-up-arrow text-primary me-2"></i>
                বিশেষ দুর্বলতা ও অন্তর্ভুক্তি সূচক (Vulnerability Distribution)
              </h6>
            </div>
            <div className="card-body pt-0">
              <div className="list-group list-group-flush">
                <div className="list-group-item d-flex justify-content-between align-items-center px-0">
                  <span>
                    <i className="bi bi-gender-female text-danger me-2"></i>
                    নারী প্রধান পরিবার (Female-Headed)
                  </span>
                  <span className="badge bg-danger rounded-pill fs-6">
                    {m.vulnerability?.female_headed_households?.toLocaleString() || 0}
                  </span>
                </div>
                <div className="list-group-item d-flex justify-content-between align-items-center px-0">
                  <span>
                    <i className="bi bi-universal-access text-warning-emphasis me-2"></i>
                    প্রতিবন্ধী ব্যক্তি রয়েছে (Persons with Disability)
                  </span>
                  <span className="badge bg-warning text-dark rounded-pill fs-6">
                    {m.vulnerability?.households_with_disabled?.toLocaleString() || 0}
                  </span>
                </div>
                <div className="list-group-item d-flex justify-content-between align-items-center px-0">
                  <span>
                    <i className="bi bi-person-walking text-info me-2"></i>
                    বয়োবৃদ্ধ সদস্য (Elderly Persons &ge; ৬০)
                  </span>
                  <span className="badge bg-info text-dark rounded-pill fs-6">
                    {m.vulnerability?.households_with_elderly?.toLocaleString() || 0}
                  </span>
                </div>
                <div className="list-group-item d-flex justify-content-between align-items-center px-0">
                  <span>
                    <i className="bi bi-tornado text-secondary me-2"></i>
                    বাস্তুচ্যুত বা আশ্রয়কেন্দ্রে অবস্থানরত (Displaced)
                  </span>
                  <span className="badge bg-secondary rounded-pill fs-6">
                    {m.vulnerability?.displaced_households?.toLocaleString() || 0}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Poverty Categorization */}
        <div className="col-lg-6">
          <div className="card shadow-sm border-0 h-100">
            <div className="card-header bg-white py-3 border-0">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-cash-stack text-success me-2"></i>
                দারিদ্র্য স্তর বণ্টন (Poverty Category Breakdown)
              </h6>
            </div>
            <div className="card-body pt-0">
              <div className="mb-3">
                <div className="d-flex justify-content-between mb-1">
                  <span className="small text-danger fw-bold">চরম দরিদ্র (Extreme Poor: &lt; ৫,০০০ টাকা)</span>
                  <span className="small fw-bold">{m.poverty_distribution?.extreme_poor || 0} পরিবার</span>
                </div>
                <div className="progress" style={{ height: '10px' }}>
                  <div
                    className="progress-bar bg-danger"
                    style={{
                      width: `${m.total_households ? (m.poverty_distribution?.extreme_poor / m.total_households) * 100 : 0}%`,
                    }}
                  ></div>
                </div>
              </div>

              <div className="mb-3">
                <div className="d-flex justify-content-between mb-1">
                  <span className="small text-warning-emphasis fw-bold">দরিদ্র (Poor: ৫,০০০ - ১০,০০০ টাকা)</span>
                  <span className="small fw-bold">{m.poverty_distribution?.poor || 0} পরিবার</span>
                </div>
                <div className="progress" style={{ height: '10px' }}>
                  <div
                    className="progress-bar bg-warning"
                    style={{
                      width: `${m.total_households ? (m.poverty_distribution?.poor / m.total_households) * 100 : 0}%`,
                    }}
                  ></div>
                </div>
              </div>

              <div className="mb-3">
                <div className="d-flex justify-content-between mb-1">
                  <span className="small text-info fw-bold">নিম্ন মধ্যবিত্ত (Lower Middle: ১০,০০০ - ২০,০০০ টাকা)</span>
                  <span className="small fw-bold">{m.poverty_distribution?.lower_middle || 0} পরিবার</span>
                </div>
                <div className="progress" style={{ height: '10px' }}>
                  <div
                    className="progress-bar bg-info"
                    style={{
                      width: `${m.total_households ? (m.poverty_distribution?.lower_middle / m.total_households) * 100 : 0}%`,
                    }}
                  ></div>
                </div>
              </div>

              <div className="p-3 bg-light rounded text-muted small mt-4">
                <i className="bi bi-info-circle me-1 text-primary"></i>
                দুর্যোগ পরবর্তী ত্রাণ বরাদ্দে চরম দরিদ্র ও প্রতিবন্ধী সদস্য বিশিষ্ট পরিবারকে সর্বোচ্চ অগ্রাধিকার দেওয়া হয়।
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Geographical & Disaster Breakdown Tables */}
      <div className="row g-3">
        {/* Geographic Breakdown */}
        <div className="col-lg-6">
          <div className="card shadow-sm border-0">
            <div className="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-geo-alt-fill text-danger me-2"></i>
                ভৌগোলিক এলাকাভিত্তিক বণ্টন (Geographical Area)
              </h6>
              <span className="badge bg-light text-secondary border">{geoData.length}টি এলাকা</span>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light small">
                    <tr>
                      <th>অধিক্ষেত্র (Jurisdiction)</th>
                      <th className="text-center">পরিবার</th>
                      <th className="text-center">অনুমোদিত</th>
                      <th className="text-center">গড় স্কোর</th>
                    </tr>
                  </thead>
                  <tbody>
                    {geoData.length > 0 ? (
                      geoData.map((g, idx) => (
                        <tr key={idx}>
                          <td className="fw-bold">{g.jurisdiction_name}</td>
                          <td className="text-center">{g.households_count}</td>
                          <td className="text-center text-success fw-bold">{g.approved_count}</td>
                          <td className="text-center">
                            <span className="badge bg-light text-dark border">{g.avg_vulnerability_score}</span>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan="4" className="text-center text-muted py-3">
                          কোনো ভৌগোলিক তথ্য পাওয়া যায়নি।
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        {/* Disaster Breakdown */}
        <div className="col-lg-6">
          <div className="card shadow-sm border-0">
            <div className="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
              <h6 className="fw-bold mb-0">
                <i className="bi bi-tsunami text-info me-2"></i>
                দুর্যোগভিত্তিক তালিকাভুক্তি (Disaster Linkages)
              </h6>
              <span className="badge bg-light text-secondary border">{disasterData.length}টি সংকট</span>
            </div>
            <div className="card-body p-0">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light small">
                    <tr>
                      <th>দুর্যোগের নাম (Disaster Event)</th>
                      <th className="text-center">ধরন</th>
                      <th className="text-center">তালিকাভুক্ত পরিবার</th>
                      <th className="text-center">ত্রাণ বিতরণ</th>
                    </tr>
                  </thead>
                  <tbody>
                    {disasterData.length > 0 ? (
                      disasterData.map((d, idx) => (
                        <tr key={idx}>
                          <td>
                            <div className="fw-bold">{d.name_bn || d.name_en}</div>
                            <small className="text-muted font-monospace">{d.disaster_code}</small>
                          </td>
                          <td className="text-center">
                            <span className="badge bg-light text-secondary border">{d.type}</span>
                          </td>
                          <td className="text-center fw-bold">{d.enrolled_households}</td>
                          <td className="text-center">
                            <span className="badge bg-success-subtle text-success border border-success">
                              {d.assistance_disbursed}
                            </span>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan="4" className="text-center text-muted py-3">
                          কোনো দুর্যোগ তথ্য পাওয়া যায়নি।
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BeneficiaryDashboardPage;
