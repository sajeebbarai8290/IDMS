import React from 'react';
import { useQuery } from '@tanstack/react-query';
import axiosClient from '../../api/axiosClient';
import { ENDPOINTS } from '../../api/endpoints';
import { useAuth } from '../../hooks/useAuth';
import Loader from '../../components/common/Loader';
import Badge from '../../components/common/Badge';

export const DashboardPage = () => {
  const { user } = useAuth();
  const scope = user?.administrativeScope;

  const { data: systemInfo, isLoading } = useQuery({
    queryKey: ['systemInfo'],
    queryFn: () => axiosClient.get(ENDPOINTS.SYSTEM_INFO),
  });

  const { data: healthData } = useQuery({
    queryKey: ['systemHealth'],
    queryFn: () => axiosClient.get(ENDPOINTS.HEALTH),
    refetchInterval: 30000,
  });

  if (isLoading) {
    return <Loader message="ড্যাশবোর্ড তথ্য লোড হচ্ছে..." messageEn="Loading operational dashboard..." />;
  }

  const tiers = systemInfo?.supported_tiers || [];

  return (
    <div>
      {/* Welcome Banner */}
      <div className="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
          <h3 className="fw-bold text-secondary mb-1">
            স্বাগতম, {user?.name || 'কর্মকর্তা'}
          </h3>
          <p className="text-muted small mb-0">
            দায়িত্বপ্রাপ্ত অধিক্ষেত্র: <strong>{scope?.name_bn || scope?.name_en || 'জাতীয় পর্যায়'}</strong>
          </p>
        </div>
        <div className="mt-2 mt-md-0 d-flex gap-2">
          <Badge tier={scope?.tier_level || 'NATIONAL'}>
            স্তর: {scope?.tier_level || 'NATIONAL'}
          </Badge>
          <span className="badge bg-success d-inline-flex align-items-center gap-1">
            <i className="bi bi-shield-check"></i>
            {user?.roles?.[0] || 'AUTHORIZED'}
          </span>
        </div>
      </div>

      {/* Operational KPI Metric Cards */}
      <div className="row g-3 mb-4">
        <div className="col-12 col-sm-6 col-xl-3">
          <div className="idms-card p-3">
            <div className="d-flex justify-content-between align-items-center">
              <div>
                <span className="text-muted small">সক্রিয় দুর্যোগ সতর্কবার্তা</span>
                <h3 className="fw-bold text-danger my-1">০৩</h3>
                <small className="text-success fw-semibold"><i className="bi bi-broadcast"></i> বিএমডি সতর্কবার্তা জারি</small>
              </div>
              <div className="p-3 bg-danger bg-opacity-10 rounded-circle text-danger">
                <i className="bi bi-exclamation-triangle-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-sm-6 col-xl-3">
          <div className="idms-card p-3">
            <div className="d-flex justify-content-between align-items-center">
              <div>
                <span className="text-muted small">তালিকাভুক্ত পরিবার (উপকারভোগী)</span>
                <h3 className="fw-bold text-secondary my-1">১২,৪৫০</h3>
                <small className="text-muted"><i className="bi bi-shield-lock"></i> এনআইডি যাচাইকৃত</small>
              </div>
              <div className="p-3 bg-success bg-opacity-10 rounded-circle text-success">
                <i className="bi bi-people-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-sm-6 col-xl-3">
          <div className="idms-card p-3">
            <div className="d-flex justify-content-between align-items-center">
              <div>
                <span className="text-muted small">প্রস্তুতকৃত আশ্রয়কেন্দ্র</span>
                <h3 className="fw-bold text-primary my-1">৫২৮</h3>
                <small className="text-muted"><i className="bi bi-geo-alt"></i> উপকূলীয় ও প্লাবন অঞ্চল</small>
              </div>
              <div className="p-3 bg-primary bg-opacity-10 rounded-circle text-primary">
                <i className="bi bi-house-door-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>

        <div className="col-12 col-sm-6 col-xl-3">
          <div className="idms-card p-3">
            <div className="d-flex justify-content-between align-items-center">
              <div>
                <span className="text-muted small">মজুদ জরুরি খাদ্য (মে.টন)</span>
                <h3 className="fw-bold text-warning my-1">১,৮২০</h3>
                <small className="text-success"><i className="bi bi-check-circle"></i> বাফার স্টক পর্যাপ্ত</small>
              </div>
              <div className="p-3 bg-warning bg-opacity-10 rounded-circle text-warning">
                <i className="bi bi-box-seam-fill fs-3"></i>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* 7-Tier Administrative Architecture Overview */}
      <div className="row g-4">
        <div className="col-12 col-lg-8">
          <div className="idms-card">
            <div className="idms-card-header d-flex justify-content-between align-items-center">
              <span>৭-স্তরীয় সার্বভৌম প্রশাসনিক কাঠামো (7-Tier Hierarchy)</span>
              <span className="badge bg-light text-secondary border">BBS GeoCode Compliant</span>
            </div>
            <div className="p-3">
              <div className="table-responsive">
                <table className="table table-hover align-middle mb-0">
                  <thead className="table-light">
                    <tr>
                      <th style={{ width: '10%' }}>স্তর</th>
                      <th style={{ width: '25%' }}>প্রশাসনিক পর্যায়</th>
                      <th style={{ width: '35%' }}>ইংরেজি নাম</th>
                      <th style={{ width: '30%' }}>অধিক্ষেত্র স্ট্যাটাস</th>
                    </tr>
                  </thead>
                  <tbody>
                    {tiers.map((t) => {
                      const isCurrentTier = scope?.tier_level === t.code;
                      return (
                        <tr key={t.tier} className={isCurrentTier ? 'table-success' : ''}>
                          <td><strong>টি-{t.tier}</strong></td>
                          <td>
                            <span className="fw-semibold">{t.name_bn}</span>
                          </td>
                          <td className="text-muted small">{t.name_en}</td>
                          <td>
                            <Badge tier={t.code}>
                              {isCurrentTier ? 'বর্তমান অধিক্ষেত্র' : t.code}
                            </Badge>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        {/* System Readiness Panel */}
        <div className="col-12 col-lg-4">
          <div className="idms-card mb-3">
            <div className="idms-card-header d-flex justify-content-between align-items-center">
              <span>সিস্টেম অবকাঠামো অবস্থা</span>
              <span className={`badge ${healthData?.status === 'healthy' ? 'bg-success' : 'bg-warning'}`}>
                {healthData?.status?.toUpperCase() || 'STANDBY'}
              </span>
            </div>
            <div className="p-3">
              <ul className="list-group list-group-flush small">
                <li className="list-group-item d-flex justify-content-between px-0">
                  <span className="text-muted"><i className="bi bi-database me-2"></i>PostgreSQL & PostGIS:</span>
                  <span className="fw-semibold text-success">{healthData?.checks?.database?.status || 'UP'}</span>
                </li>
                <li className="list-group-item d-flex justify-content-between px-0">
                  <span className="text-muted"><i className="bi bi-hdd-network me-2"></i>Redis Cache / Queues:</span>
                  <span className="fw-semibold text-success">{healthData?.checks?.cache_redis?.status || 'UP'}</span>
                </li>
                <li className="list-group-item d-flex justify-content-between px-0">
                  <span className="text-muted"><i className="bi bi-shield-lock me-2"></i>Keycloak 24+ IAM:</span>
                  <span className="fw-semibold text-info">{healthData?.checks?.keycloak_iam?.status || 'STANDBY'}</span>
                </li>
                <li className="list-group-item d-flex justify-content-between px-0">
                  <span className="text-muted"><i className="bi bi-code-slash me-2"></i>API Framework:</span>
                  <span className="fw-semibold">Laravel 11.x REST</span>
                </li>
              </ul>
            </div>
          </div>

          <div className="idms-card bg-success text-white p-3">
            <div className="d-flex align-items-center gap-2 mb-2">
              <i className="bi bi-telephone-outbound-fill fs-4"></i>
              <h6 className="mb-0 fw-bold">জরুরি দুর্যোগ বার্তা সেবা</h6>
            </div>
            <p className="small mb-2 opacity-90">
              জরুরি প্রয়োজনে জাতীয় দুর্যোগ ব্যবস্থাপনা সমন্বয় কেন্দ্র (NEOC) এর সাথে ২৪/৭ যোগাযোগ করুন।
            </p>
            <div className="d-flex justify-content-between align-items-center pt-2 border-top border-white border-opacity-25">
              <span>টোল-ফ্রি হেল্পলাইন:</span>
              <strong className="fs-5">১০৯০ / ৯৯৯</strong>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DashboardPage;
