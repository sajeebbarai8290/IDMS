import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { resourceApi } from '../../../api/resourceApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ResourceGisMap from '../components/ResourceGisMap';
import { formatResourceApiError, RESOURCE_STATUS_META } from '../utils/resourcePermissions';

export const ResourceMapPage = () => {
  const { t } = useLanguage();
  const navigate = useNavigate();

  const [categoryId, setCategoryId] = useState('');
  const [status, setStatus] = useState('');

  const { data: lookupsRes } = useQuery({
    queryKey: ['resource', 'lookups'],
    queryFn: () => resourceApi.getLookups(),
  });

  const { data: geoRes, isLoading, error } = useQuery({
    queryKey: ['resource', 'geojson', categoryId, status],
    queryFn: () =>
      resourceApi.getGeoJson({
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(status ? { status } : {}),
      }),
  });

  const categories = lookupsRes?.data?.categories || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-geo-alt-fill text-primary me-2"></i>
            {t('সম্পদ ভৌগোলিক বণ্টন জিআইএস মানচিত্র', 'Geographical Resource Distribution GIS Map')}
          </h4>
          <p className="text-muted small mb-0">
            {t('নিবন্ধিত সম্পদের অবস্থান এবং চলমান বরাদ্দের মানচিত্র', 'Live spatial distribution of registered resources and active field allocations')}
          </p>
        </div>

        <div className="d-flex gap-2">
          <select className="form-select form-select-sm" style={{ width: '185px' }} value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
            <option value="">{t('সকল শ্রেণি', 'All Categories')}</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
            ))}
          </select>
          <select className="form-select form-select-sm" style={{ width: '165px' }} value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
            {['AVAILABLE', 'ASSIGNED', 'IN_USE', 'UNDER_MAINTENANCE', 'DAMAGED'].map((s) => (
              <option key={s} value={s}>{t(RESOURCE_STATUS_META[s]?.bn || s, RESOURCE_STATUS_META[s]?.en || s)}</option>
            ))}
          </select>
        </div>
      </div>

      {error && <AlertBanner variant="danger" message={formatResourceApiError(error, t('মানচিত্র লোড ব্যর্থ', 'Failed to load map'))} />}

      {isLoading ? (
        <Loader />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="card-body">
            <ResourceGisMap
              geojson={geoRes?.data}
              height="560px"
              mode="explore"
              onSelectAsset={(assetId) => navigate(`/resource/assets/${assetId}`)}
            />
          </div>
        </div>
      )}
    </div>
  );
};

export default ResourceMapPage;
