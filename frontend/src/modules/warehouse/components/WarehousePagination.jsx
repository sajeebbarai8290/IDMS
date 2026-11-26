import React from 'react';
import { useLanguage } from '../../../contexts/LanguageContext';

export const WarehousePagination = ({ meta, onPageChange }) => {
  const { t } = useLanguage();
  if (!meta || !meta.last_page || meta.last_page <= 1) return null;

  const current = Number(meta.current_page || 1);
  const last = Number(meta.last_page || 1);

  return (
    <div className="card-footer bg-white d-flex justify-content-between align-items-center small">
      <span>{t(`মোট ${meta.total || 0}টি রেকর্ড`, `Total ${meta.total || 0} records`)}</span>
      <div className="btn-group btn-group-sm">
        <button
          type="button"
          className="btn btn-outline-secondary"
          disabled={current <= 1}
          onClick={() => onPageChange(current - 1)}
        >
          {t('পূর্ববর্তী', 'Prev')}
        </button>
        <span className="btn btn-light disabled">
          {current} / {last}
        </span>
        <button
          type="button"
          className="btn btn-outline-secondary"
          disabled={current >= last}
          onClick={() => onPageChange(current + 1)}
        >
          {t('পরবর্তী', 'Next')}
        </button>
      </div>
    </div>
  );
};
