import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { projectApi } from '../../../api/projectApi';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import ProjectGisMap from '../components/ProjectGisMap';
import { formatProjectApiError, PROJECT_STATUS_META } from '../utils/projectPermissions';

export const ProjectMapPage = () => {
  const { t } = useLanguage();
  const navigate = useNavigate();

  const [categoryId, setCategoryId] = useState('');
  const [status, setStatus] = useState('');

  const { data: lookupsRes } = useQuery({
    queryKey: ['project', 'lookups'],
    queryFn: () => projectApi.getLookups(),
  });

  const { data: geoRes, isLoading, error } = useQuery({
    queryKey: ['project', 'geojson', categoryId, status],
    queryFn: () =>
      projectApi.getGeoJson({
        ...(categoryId ? { category_id: categoryId } : {}),
        ...(status ? { status } : {}),
      }),
  });

  const categories = lookupsRes?.data?.categories || [];
  const statuses = lookupsRes?.data?.lifecycle_statuses || [];
  const geojson = geoRes?.data || null;

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-geo-alt-fill text-primary me-2"></i>
            {t('প্রকল্প জিআইএস মানচিত্র', 'Project Portfolio GIS Map')}
          </h4>
          <p className="text-muted small mb-0">
            {t('প্রকল্পের কর্মস্থল, বহু-অবস্থান ও সীমানা বহুভুজ', 'Project sites, multi-location coordinates, and GIS boundaries')}
          </p>
        </div>

        <div className="d-flex gap-2">
          <select
            className="form-select form-select-sm"
            style={{ width: '185px' }}
            value={categoryId}
            onChange={(e) => setCategoryId(e.target.value)}
          >
            <option value="">{t('সকল ধরন', 'All Categories')}</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>{t(c.name_bn, c.name_en)}</option>
            ))}
          </select>
          <select
            className="form-select form-select-sm"
            style={{ width: '160px' }}
            value={status}
            onChange={(e) => setStatus(e.target.value)}
          >
            <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
            {statuses.map((s) => (
              <option key={s} value={s}>{t(PROJECT_STATUS_META[s]?.bn || s, PROJECT_STATUS_META[s]?.en || s)}</option>
            ))}
          </select>
        </div>
      </div>

      {error && <AlertBanner variant="danger" message={formatProjectApiError(error, t('মানচিত্র লোড ব্যর্থ', 'Failed to load map'))} />}

      {isLoading ? (
        <Loader />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="card-body">
            <ProjectGisMap
              geojson={geojson}
              height="560px"
              mode="explore"
              onSelectProject={(id) => navigate(`/project/schemes/${id}`)}
            />
          </div>
        </div>
      )}
    </div>
  );
};

export default ProjectMapPage;
