import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import beneficiaryApi from '../../../api/beneficiaryApi';
import BeneficiaryFilterBar from '../components/BeneficiaryFilterBar';
import VulnerabilityBadge from '../components/VulnerabilityBadge';
import VerificationStatusBadge from '../components/VerificationStatusBadge';
import PiiRevealModal from '../components/PiiRevealModal';
import Loader from '../../../components/common/Loader';
import EmptyState from '../../../components/common/EmptyState';
import ConfirmDialog from '../../../components/common/ConfirmDialog';

export const BeneficiaryListPage = () => {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState({
    page: 1,
    per_page: 15,
  });
  const [deleteTarget, setDeleteTarget] = useState(null);

  const { data, isLoading, error } = useQuery({
    queryKey: ['households', filters],
    queryFn: async () => {
      const res = await beneficiaryApi.getHouseholds(filters);
      return res || {};
    },
    keepPreviousData: true,
  });

  const deleteMutation = useMutation({
    mutationFn: (id) => beneficiaryApi.deleteHousehold(id),
    onSuccess: () => {
      queryClient.invalidateQueries(['households']);
      setDeleteTarget(null);
    },
  });

  const handleFilterChange = (newFilters) => {
    setFilters({ ...newFilters, page: 1 });
  };

  const handleResetFilters = () => {
    setFilters({ page: 1, per_page: 15 });
  };

  const handlePageChange = (newPage) => {
    setFilters((prev) => ({ ...prev, page: newPage }));
  };

  const households = Array.isArray(data) ? data : data?.data || [];
  const pagination = data?.pagination || {};

  return (
    <div className="container-fluid py-2">
      {/* Title & Actions */}
      <div className="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
          <h4 className="fw-bold text-dark mb-1">
            <i className="bi bi-people me-2 text-primary"></i>
            উপকারভোগী মাস্টার রেজিস্ট্রি (Beneficiary Master Registry)
          </h4>
          <p className="text-muted small mb-0">
            জাতীয় পরিচয়পত্র যাচাইকৃত নাগরিক ও দুস্থ পরিবারের তালিকা
          </p>
        </div>
        <div className="d-flex flex-wrap gap-2">
          <Link to="/beneficiary/register" className="btn btn-primary btn-sm shadow-sm">
            <i className="bi bi-person-plus-fill me-1"></i>
            নতুন পরিবার নিবন্ধন
          </Link>
          <a
            href={beneficiaryApi.exportUrl(filters)}
            target="_blank"
            rel="noopener noreferrer"
            className="btn btn-outline-success btn-sm"
          >
            <i className="bi bi-download me-1"></i>
            মাস্টার রোল এক্সপোর্ট (CSV)
          </a>
        </div>
      </div>

      {/* Filter Bar */}
      <BeneficiaryFilterBar
        filters={filters}
        onFilterChange={handleFilterChange}
        onReset={handleResetFilters}
      />

      {/* Table Content */}
      <div className="card shadow-sm border-0">
        <div className="card-body p-0">
          {isLoading ? (
            <div className="py-5">
              <Loader message="উপকারভোগী তালিকা লোড হচ্ছে..." />
            </div>
          ) : error ? (
            <div className="p-4 text-center text-danger">
              <i className="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
              ডেটা লোড করতে ব্যর্থ হয়েছে: {error.message}
            </div>
          ) : households.length === 0 ? (
            <EmptyState
              title="কোনো উপকারভোগী পাওয়া যায়নি"
              description="আপনার প্রদত্ত ফিল্টার অনুযায়ী কোনো পরিবারের তথ্য মেলেনি অথবা কোনো নিবন্ধন করা হয়নি।"
              action={
                <Link to="/beneficiary/register" className="btn btn-primary btn-sm">
                  <i className="bi bi-plus-lg me-1"></i>
                  প্রথম পরিবার নিবন্ধন করুন
                </Link>
              }
            />
          ) : (
            <div className="table-responsive">
              <table className="table table-hover align-middle mb-0">
                <thead className="table-light small">
                  <tr>
                    <th>পরিবার কোড (Code)</th>
                    <th>প্রধানের নাম (Head of Household)</th>
                    <th>এনআইডি (National ID)</th>
                    <th>মোবাইল নম্বর</th>
                    <th>অধিক্ষেত্র (Jurisdiction)</th>
                    <th className="text-center">সদস্য</th>
                    <th className="text-center">স্কোর (Score)</th>
                    <th className="text-center">অবস্থা (Status)</th>
                    <th className="text-end">অ্যাকশন</th>
                  </tr>
                </thead>
                <tbody>
                  {households.map((hh) => (
                    <tr key={hh.id}>
                      {/* Household Code */}
                      <td>
                        <Link to={`/beneficiary/households/${hh.id}`} className="fw-bold text-primary font-monospace text-decoration-none">
                          {hh.household_code}
                        </Link>
                      </td>

                      {/* HoH Name */}
                      <td>
                        <div className="fw-bold text-dark">{hh.hoh_name_bn || hh.hoh_name_en}</div>
                        <small className="text-muted">{hh.hoh_name_en}</small>
                      </td>

                      {/* Masked NID */}
                      <td>
                        <span className="font-monospace text-secondary">{hh.hoh_nid || hh.hoh_nid_masked}</span>
                      </td>

                      {/* Masked Mobile */}
                      <td>
                        <small className="font-monospace text-muted">{hh.hoh_mobile || hh.hoh_mobile_masked || '—'}</small>
                      </td>

                      {/* Jurisdiction & Village */}
                      <td>
                        <div>{hh.administrative_unit?.name_bn || hh.administrative_unit?.name_en || 'N/A'}</div>
                        <small className="text-muted">{hh.village_ward_name || 'ওয়ার্ড ১'}</small>
                      </td>

                      {/* Member Count */}
                      <td className="text-center">
                        <span className="badge bg-light text-dark border">
                          <i className="bi bi-people-fill me-1 text-secondary"></i>
                          {hh.family_member_count}
                        </span>
                      </td>

                      {/* Vulnerability Score */}
                      <td className="text-center">
                        <VulnerabilityBadge score={hh.vulnerability?.score} />
                      </td>

                      {/* Verification Status */}
                      <td className="text-center">
                        <VerificationStatusBadge status={hh.verification?.status} />
                      </td>

                      {/* Action Buttons */}
                      <td className="text-end">
                        <div className="btn-group btn-group-sm">
                          <Link
                            to={`/beneficiary/households/${hh.id}`}
                            className="btn btn-outline-secondary"
                            title="বিস্তারিত দেখুন"
                          >
                            <i className="bi bi-eye"></i>
                          </Link>
                          <Link
                            to={`/beneficiary/households/${hh.id}/edit`}
                            className="btn btn-outline-secondary"
                            title="সম্পাদনা করুন"
                          >
                            <i className="bi bi-pencil"></i>
                          </Link>
                          <PiiRevealModal
                            householdId={hh.id}
                            maskedNid={hh.hoh_nid_masked}
                            maskedMobile={hh.hoh_mobile_masked}
                          />
                          <button
                            type="button"
                            className="btn btn-outline-danger"
                            title="মুছে ফেলুন"
                            onClick={() => setDeleteTarget(hh)}
                          >
                            <i className="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Pagination Footer */}
        {pagination.total > 0 && (
          <div className="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center py-2">
            <small className="text-muted">
              মোট <strong>{pagination.total}</strong>টি পরিবারের মধ্যে{' '}
              <strong>{(pagination.current_page - 1) * pagination.per_page + 1}</strong> থেকে{' '}
              <strong>{Math.min(pagination.current_page * pagination.per_page, pagination.total)}</strong> দেখানো হচ্ছে
            </small>

            <nav aria-label="Page navigation">
              <ul className="pagination pagination-sm mb-0">
                <li className={`page-item ${pagination.current_page <= 1 ? 'disabled' : ''}`}>
                  <button className="page-link" onClick={() => handlePageChange(pagination.current_page - 1)}>
                    পূর্ববর্তী
                  </button>
                </li>
                {Array.from({ length: Math.min(5, pagination.last_page) }, (_, i) => {
                  const p = i + 1;
                  return (
                    <li key={p} className={`page-item ${pagination.current_page === p ? 'active' : ''}`}>
                      <button className="page-link" onClick={() => handlePageChange(p)}>
                        {p}
                      </button>
                    </li>
                  );
                })}
                <li className={`page-item ${pagination.current_page >= pagination.last_page ? 'disabled' : ''}`}>
                  <button className="page-link" onClick={() => handlePageChange(pagination.current_page + 1)}>
                    পরবর্তী
                  </button>
                </li>
              </ul>
            </nav>
          </div>
        )}
      </div>

      {/* Delete Confirmation Dialog */}
      <ConfirmDialog
        isOpen={Boolean(deleteTarget)}
        title="উপকারভোগী পরিবার মুছে ফেলার নিশ্চিতকরণ"
        message={`আপনি কি নিশ্চিতভাবে "${deleteTarget?.hoh_name_en} (${deleteTarget?.household_code})" এর রেকর্ড মুছে ফেলতে চান?`}
        confirmLabel="হ্যাঁ, মুছে ফেলুন"
        cancelLabel="বাতিল"
        confirmVariant="danger"
        isLoading={deleteMutation.isLoading}
        onConfirm={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
};

export default BeneficiaryListPage;
