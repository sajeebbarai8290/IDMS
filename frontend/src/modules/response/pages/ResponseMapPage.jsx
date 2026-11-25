import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { responseApi } from '../../../api/responseApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ResponseGisMap from '../components/ResponseGisMap';
import { formatResponseApiError } from '../utils/responsePermissions';

export const ResponseMapPage = () => {
  const { t } = useLanguage();
  const [disasterId, setDisasterId] = useState('');
  const [operationId, setOperationId] = useState('');

  const { data: lookupsRes } = useQuery({
    queryKey: ['response', 'lookups'],
    queryFn: () => responseApi.getLookups(),
  });

  const { data: opsRes } = useQuery({
    queryKey: ['response', 'operations', 'map-filter'],
    queryFn: () => responseApi.getOperations({ per_page: 50 }),
  });

  const { data: geoRes, isLoading, error } = useQuery({
    queryKey: ['response', 'geojson', disasterId, operationId],
    queryFn: () =>
      responseApi.getGeoJson({
        disaster_event_id: disasterId || undefined,
        response_operation_id: operationId || undefined,
      }),
  });

  const disasters = lookupsRes?.data?.disasters || [];
  const operations = opsRes?.data || [];
  const exactAuthorized = geoRes?.data?.metadata?.exact_gps_authorized ?? false;

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-map-fill text-danger me-2"></i>
            {t('দুর্যোগ সাড়াদান জিআইএস মানচিত্র', 'Disaster Response Operations GIS Map')}
          </h4>
          <p className="text-muted small mb-0">
            {t('আক্রান্ত এলাকা, সাড়াদান কেন্দ্র (EOC), মোতায়েনকৃত দল এবং জরুরি ঘটনা', 'Affected areas, response centers (EOCs), deployed teams & incidents')}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2">
          <select className="form-select form-select-sm" style={{ width: '210px' }} value={disasterId} onChange={(e) => setDisasterId(e.target.value)}>
            <option value="">{t('সকল দুর্যোগ', 'All Disasters')}</option>
            {disasters.map((d) => <option key={d.id} value={d.id}>{t(d.name_bn || d.name_en, d.name_en)}</option>)}
          </select>
          <select className="form-select form-select-sm" style={{ width: '220px' }} value={operationId} onChange={(e) => setOperationId(e.target.value)}>
            <option value="">{t('সকল সাড়াদান অভিযান', 'All Response Operations')}</option>
            {operations.map((o) => <option key={o.id} value={o.id}>{o.operation_no} — {t(o.title_bn || o.title_en, o.title_en)}</option>)}
          </select>
          <Link to="/response/dashboard" className="btn btn-sm btn-outline-secondary">{t('ড্যাশবোর্ড', 'Dashboard')}</Link>
        </div>
      </div>

      {!exactAuthorized && (
        <AlertBanner
          variant="warning"
          message={t(
            'অবস্থান গোপনীয়তা নীতি সক্রিয়: সংবেদনশীল ঘটনা ও দলের সঠিক জিপিএস স্থানাঙ্ক আনুমানিক (~১.১ কিমি) গ্রিডে সীমিত রাখা হয়েছে।',
            'Location privacy restriction active: sensitive incident & field team coordinates are generalized (~1.1 km precision).'
          )}
        />
      )}

      {error && <AlertBanner variant="danger" message={formatResponseApiError(error, t('মানচিত্র তথ্য লোড ব্যর্থ', 'Failed to load GIS features'))} />}
      {isLoading ? (
        <Loader />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="card-body">
            <ResponseGisMap geojson={geoRes?.data} height="520px" />
          </div>
        </div>
      )}
    </div>
  );
};

export default ResponseMapPage;
