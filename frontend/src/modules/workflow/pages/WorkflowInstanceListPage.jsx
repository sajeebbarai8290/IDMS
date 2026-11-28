import React, { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { workflowApi } from '../../../api/workflowApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import { canSubmitWorkflow, formatWorkflowApiError } from '../utils/workflowPermissions';
import { OverdueBadge, WorkflowPriorityBadge, WorkflowStateBadge } from '../components/WorkflowStatusBadges';

const QUEUE_TABS = [
  { key: 'my_pending', bn: 'আমার অপেক্ষমাণ', en: 'My Pending' },
  { key: 'submitted_by_me', bn: 'আমার দাখিলকৃত', en: 'Submitted by Me' },
  { key: 'approved', bn: 'অনুমোদিত', en: 'Approved' },
  { key: 'rejected', bn: 'প্রত্যাখ্যাত', en: 'Rejected' },
  { key: 'overdue', bn: 'মেয়াদোত্তীর্ণ', en: 'Overdue' },
  { key: 'all', bn: 'সকল', en: 'All' },
];

export const WorkflowInstanceListPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const [searchParams, setSearchParams] = useSearchParams();

  const [queue, setQueue] = useState(searchParams.get('queue') || 'all');
  const [moduleCode, setModuleCode] = useState('');
  const [currentState, setCurrentState] = useState('');
  const [priority, setPriority] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  const { data: lookupsRes } = useQuery({
    queryKey: ['workflow', 'lookups'],
    queryFn: () => workflowApi.getLookups(),
  });

  const { data: listRes, isLoading, error } = useQuery({
    queryKey: ['workflow', 'instances', { queue, moduleCode, currentState, priority, search, page }],
    queryFn: () =>
      workflowApi.getInstances({
        queue,
        module_code: moduleCode || undefined,
        current_state: currentState || undefined,
        priority: priority || undefined,
        search: search || undefined,
        page,
        per_page: 15,
      }),
  });

  const handleQueueChange = (nextQueue) => {
    setQueue(nextQueue);
    setPage(1);
    setSearchParams(nextQueue === 'all' ? {} : { queue: nextQueue });
  };

  const instances = listRes?.data || [];
  const meta = listRes?.meta || {};
  const lookups = lookupsRes?.data || {};

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-list-check text-primary me-2"></i>
            {t('কর্মপ্রবাহ ও অনুমোদন সারি', 'Workflow & Approval Queues')}
          </h4>
          <p className="text-muted small mb-0">
            {t('অপেক্ষমাণ অনুমোদন, দাখিলকৃত আবেদন ও এসএলএ অবস্থা পর্যালোচনা', 'Track pending approvals, submitted requests, and SLA status')}
          </p>
        </div>
        {canSubmitWorkflow(user) && (
          <Link to="/workflow/submit" className="btn btn-primary btn-sm">
            <i className="bi bi-plus-circle me-1"></i>
            {t('নতুন আবেদন দাখিল', 'Submit Workflow')}
          </Link>
        )}
      </div>

      {/* Queue Tabs */}
      <ul className="nav nav-pills mb-3 gap-1">
        {QUEUE_TABS.map((tab) => (
          <li className="nav-item" key={tab.key}>
            <button
              type="button"
              className={`nav-link btn-sm ${queue === tab.key ? 'active' : ''}`}
              onClick={() => handleQueueChange(tab.key)}
            >
              {t(tab.bn, tab.en)}
            </button>
          </li>
        ))}
      </ul>

      {/* Filter Bar */}
      <div className="idms-card p-3 mb-3">
        <div className="row g-2">
          <div className="col-12 col-md-4">
            <input
              type="text"
              className="form-control form-control-sm"
              placeholder={t('নম্বর, রেফারেন্স বা শিরোনাম দিয়ে খুঁজুন...', 'Search by instance no, reference, or title...')}
              value={search}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
            />
          </div>
          <div className="col-6 col-md-2">
            <select
              className="form-select form-select-sm"
              value={moduleCode}
              onChange={(e) => {
                setModuleCode(e.target.value);
                setPage(1);
              }}
            >
              <option value="">{t('সকল মডিউল', 'All Modules')}</option>
              {(lookups.modules || []).map((m) => (
                <option key={m} value={m}>{m}</option>
              ))}
            </select>
          </div>
          <div className="col-6 col-md-3">
            <select
              className="form-select form-select-sm"
              value={currentState}
              onChange={(e) => {
                setCurrentState(e.target.value);
                setPage(1);
              }}
            >
              <option value="">{t('সকল অবস্থা', 'All States')}</option>
              {(lookups.states || []).map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </select>
          </div>
          <div className="col-6 col-md-2">
            <select
              className="form-select form-select-sm"
              value={priority}
              onChange={(e) => {
                setPriority(e.target.value);
                setPage(1);
              }}
            >
              <option value="">{t('সকল অগ্রাধিকার', 'All Priorities')}</option>
              {(lookups.priorities || []).map((p) => (
                <option key={p} value={p}>{p}</option>
              ))}
            </select>
          </div>
          <div className="col-6 col-md-1">
            <button
              type="button"
              className="btn btn-outline-secondary btn-sm w-100"
              onClick={() => {
                setModuleCode('');
                setCurrentState('');
                setPriority('');
                setSearch('');
                setPage(1);
              }}
            >
              {t('রিসেট', 'Reset')}
            </button>
          </div>
        </div>
      </div>

      {isLoading ? (
        <Loader message="কর্মপ্রবাহ তালিকা লোড হচ্ছে..." messageEn="Loading workflow instances..." />
      ) : error ? (
        <AlertBanner variant="danger" message={formatWorkflowApiError(error, t('তালিকা লোড ব্যর্থ হয়েছে।', 'Failed to load instances.'))} />
      ) : instances.length === 0 ? (
        <EmptyState message={t('নির্বাচিত ফিল্টারে কোনো কর্মপ্রবাহ পাওয়া যায়নি।', 'No workflow instances match the selected criteria.')} />
      ) : (
        <div className="idms-card">
          <div className="table-responsive">
            <table className="table table-hover align-middle mb-0">
              <thead className="table-light">
                <tr>
                  <th>{t('নম্বর ও তারিখ', 'Instance & Date')}</th>
                  <th>{t('মডিউল ও শিরোনাম', 'Module & Title')}</th>
                  <th>{t('প্রশাসনিক এলাকা', 'Jurisdiction')}</th>
                  <th>{t('বর্তমান ধাপ ও অবস্থা', 'Current Stage & State')}</th>
                  <th>{t('দায়িত্বপ্রাপ্ত', 'Assigned')}</th>
                  <th>{t('অগ্রাধিকার', 'Priority')}</th>
                  <th className="text-end">{t('কার্যক্রম', 'Action')}</th>
                </tr>
              </thead>
              <tbody>
                {instances.map((item) => (
                  <tr key={item.id}>
                    <td>
                      <div className="fw-semibold small">{item.instance_no}</div>
                      <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                        {item.submitted_at ? new Date(item.submitted_at).toLocaleDateString() : '-'}
                      </div>
                    </td>
                    <td>
                      <div className="fw-semibold small">{t(item.title_bn || item.title_en, item.title_en)}</div>
                      <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                        <span className="badge bg-light text-dark border me-1">{item.module_code}</span>
                        {item.entity_reference}
                      </div>
                    </td>
                    <td className="small">
                      {t(item.administrative_unit?.name_bn || item.administrative_unit?.name_en, item.administrative_unit?.name_en)}
                      <div className="text-muted" style={{ fontSize: '0.72rem' }}>{item.administrative_unit?.tier_level}</div>
                    </td>
                    <td>
                      <div className="mb-1">
                        <WorkflowStateBadge state={item.current_state} />
                        <OverdueBadge isOverdue={item.is_overdue} />
                      </div>
                      {item.current_stage && (
                        <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                          {t(item.current_stage.name_bn, item.current_stage.name_en)}
                        </div>
                      )}
                    </td>
                    <td className="small">
                      {item.assigned_user?.name_en || item.assigned_role_code || '-'}
                      {item.due_at && !item.is_terminal && (
                        <div className="text-muted" style={{ fontSize: '0.72rem' }}>
                          SLA: {new Date(item.due_at).toLocaleString()}
                        </div>
                      )}
                    </td>
                    <td>
                      <WorkflowPriorityBadge priority={item.priority} />
                    </td>
                    <td className="text-end">
                      <Link to={`/workflow/instances/${item.id}`} className="btn btn-outline-primary btn-sm">
                        {t('বিস্তারিত', 'Details')}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {meta.last_page > 1 && (
            <div className="d-flex justify-content-between align-items-center p-3 border-top">
              <div className="small text-muted">
                {t('মোট', 'Total')}: {meta.total} | {t('পৃষ্ঠা', 'Page')} {meta.current_page} / {meta.last_page}
              </div>
              <div className="btn-group btn-group-sm">
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  {t('পূর্ববর্তী', 'Previous')}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={meta.current_page >= meta.last_page}
                  onClick={() => setPage((p) => p + 1)}
                >
                  {t('পরবর্তী', 'Next')}
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default WorkflowInstanceListPage;
