import React from 'react';
import { useQuery } from '@tanstack/react-query';
import axiosClient from '../../api/axiosClient';
import { ENDPOINTS } from '../../api/endpoints';
import Loader from '../../components/common/Loader';
import Button from '../../components/common/Button';
import AlertBanner from '../../components/common/AlertBanner';

export const HealthPage = () => {
  const { data, isLoading, isError, error, refetch, isFetching } = useQuery({
    queryKey: ['systemHealthDetail'],
    queryFn: () => axiosClient.get(ENDPOINTS.HEALTH),
  });

  if (isLoading) {
    return <Loader message="সিস্টেম স্বাস্থ্য পর্যবেক্ষণ করা হচ্ছে..." messageEn="Checking system health & infrastructure status..." />;
  }

  const checks = data?.checks || {};
  const isHealthy = data?.status === 'healthy';

  return (
    <div>
      <div className="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 pb-2 border-bottom">
        <div>
          <h3 className="fw-bold text-secondary mb-1">
            সিস্টেম স্বাস্থ্য ও অবকাঠামো মনিটর (System Health)
          </h3>
          <p className="text-muted small mb-0">
            রিয়েল-টাইম ডাটাবেস, পোস্টজিআইএস, রেডিস এবং কীক্লোক আইএএম অবস্থা
          </p>
        </div>
        <div className="mt-2 mt-md-0">
          <Button
            variant="outline-success"
            size="sm"
            icon="bi-arrow-clockwise"
            onClick={() => refetch()}
            isLoading={isFetching}
          >
            পুনরায় যাচাই করুন (Refresh)
          </Button>
        </div>
      </div>

      {isError && (
        <AlertBanner
          variant="danger"
          title="সার্ভার সংযোগে ত্রুটি"
          message={error?.message || 'ব্যাকএন্ড এপিআই সার্ভারে সংযোগ স্থাপন করা যায়নি।'}
        />
      )}

      {/* Main Status Hero */}
      <div className={`alert ${isHealthy ? 'alert-success' : 'alert-warning'} p-4 rounded-3 border-0 shadow-sm mb-4`}>
        <div className="d-flex align-items-center gap-3">
          <div className={`p-3 rounded-circle ${isHealthy ? 'bg-success text-white' : 'bg-warning text-dark'}`}>
            <i className={`bi ${isHealthy ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} fs-2`}></i>
          </div>
          <div>
            <h4 className="fw-bold mb-1">
              সিস্টেম স্ট্যাটাস: {isHealthy ? 'সকল সেবা সচল (Operational)' : 'আংশিক সচল (Degraded / Standby)'}
            </h4>
            <div className="small opacity-75">
              সার্ভিস: {data?.service} | সংস্করণ: {data?.version} | পরিবেশ: {data?.environment} | পিএইচপি: {data?.php_version}
            </div>
            <div className="small text-muted mt-1">
              যাচাইয়ের সময়: {data?.timestamp}
            </div>
          </div>
        </div>
      </div>

      {/* Infrastructure Details */}
      <div className="row g-4">
        {/* PostgreSQL & PostGIS */}
        <div className="col-12 col-md-4">
          <div className="idms-card h-100 p-4">
            <div className="d-flex align-items-center justify-content-between mb-3">
              <div className="d-flex align-items-center gap-2">
                <i className="bi bi-database-fill fs-3 text-primary"></i>
                <h5 className="fw-bold mb-0">PostgreSQL 16</h5>
              </div>
              <span className={`badge ${checks.database?.status === 'UP' ? 'bg-success' : 'bg-danger'}`}>
                {checks.database?.status || 'DOWN'}
              </span>
            </div>
            <ul className="list-unstyled small text-secondary mb-0">
              <li className="mb-2">
                <strong>ড্রাইভার: </strong>{checks.database?.connection || 'pgsql'}
              </li>
              <li className="mb-2">
                <strong>লেটেন্সি: </strong>{checks.database?.latency_ms ? `${checks.database.latency_ms} ms` : 'N/A'}
              </li>
              <li className="mb-2">
                <strong>PostGIS এক্সটেনশন: </strong>
                {checks.database?.postgis_enabled ? (
                  <span className="badge bg-success ms-1">সক্রিয় (Active)</span>
                ) : (
                  <span className="badge bg-info ms-1">কনফিগারড (Ready for Migration)</span>
                )}
              </li>
              <li className="text-muted">
                স্পেশাল কোঅর্ডিনেট সিস্টেম: WGS84 (EPSG:4326) / BTM (EPSG:3106)
              </li>
            </ul>
          </div>
        </div>

        {/* Redis Cache & Queues */}
        <div className="col-12 col-md-4">
          <div className="idms-card h-100 p-4">
            <div className="d-flex align-items-center justify-content-between mb-3">
              <div className="d-flex align-items-center gap-2">
                <i className="bi bi-hdd-network-fill fs-3 text-warning"></i>
                <h5 className="fw-bold mb-0">Redis 7 (Predis)</h5>
              </div>
              <span className={`badge ${checks.cache_redis?.status === 'UP' ? 'bg-success' : 'bg-danger'}`}>
                {checks.cache_redis?.status || 'DOWN'}
              </span>
            </div>
            <ul className="list-unstyled small text-secondary mb-0">
              <li className="mb-2">
                <strong>ক্যাশ ড্রাইভার: </strong>{checks.cache_redis?.driver || 'redis'}
              </li>
              <li className="mb-2">
                <strong>লেটেন্সি: </strong>{checks.cache_redis?.latency_ms ? `${checks.cache_redis.latency_ms} ms` : 'N/A'}
              </li>
              <li className="mb-2">
                <strong>সার্ভিস দায়িত্ব: </strong>সেশন, জেডব্লিউকেএস ক্যাশ, ব্যাকগ্রাউন্ড কিউ
              </li>
              <li className="text-muted">
                ক্লায়েন্ট: predis/predis (PHP ৮.৪ সামঞ্জস্যপূর্ণ)
              </li>
            </ul>
          </div>
        </div>

        {/* Keycloak Sovereign IAM */}
        <div className="col-12 col-md-4">
          <div className="idms-card h-100 p-4">
            <div className="d-flex align-items-center justify-content-between mb-3">
              <div className="d-flex align-items-center gap-2">
                <i className="bi bi-shield-lock-fill fs-3 text-success"></i>
                <h5 className="fw-bold mb-0">Keycloak 24+ IAM</h5>
              </div>
              <span className={`badge ${checks.keycloak_iam?.status === 'UP' ? 'bg-success' : 'bg-info'}`}>
                {checks.keycloak_iam?.status || 'STANDBY'}
              </span>
            </div>
            <ul className="list-unstyled small text-secondary mb-0">
              <li className="mb-2">
                <strong>রিয়েলম: </strong>{checks.keycloak_iam?.realm || 'idms-gov-realm'}
              </li>
              <li className="mb-2">
                <strong>টোকেন স্ট্যান্ডার্ড: </strong>RFC 7519 (RS256 JWT)
              </li>
              <li className="mb-2">
                <strong>নিরাপত্তা স্তর: </strong>MFA (TOTP + সরকারি SMS গেটওয়ে)
              </li>
              <li className="text-muted">
                সার্ভিস টাইপ: কেন্দ্রীভূত পরিচয় প্রমাণীকরণ (Centralized IdP)
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

export default HealthPage;
