import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { workflowApi } from '../../../api/workflowApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import EmptyState from '../../../components/common/EmptyState';
import {
  canManageWorkflowConfig,
  canRunEscalations,
  canSubmitWorkflow,
  formatWorkflowApiError,
} from '../utils/workflowPermissions';
import {
  OverdueBadge,
  WorkflowLevelLabel,
  WorkflowPriorityBadge,
  WorkflowStateBadge,
} from '../components/WorkflowStatusBadges';

export const WorkflowDashboardPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const queryClient = useQueryClient();
  const [moduleFilter, setModuleFilter] = useState('');
  const [feedback, setFeedback] = useState(null);

  const { data: dashRes, isLoading, error } = useQuery({
    queryKey: ['workflow', 'dashboard', moduleFilter],
    queryFn: () => workflowApi.getDashboard(moduleFilter ? { module_code: moduleFilter } : {}),
  });

  const { data: pendingRes } = useQuery({
    queryKey: ['workflow', 'instances', 'my_pending_preview'],
    queryFn: () => workflowApi.getInstances({ queue: 'my_pending', per_page: 5 }),
  });

  const { data: overdueRes } = useQuery({
    queryKey: ['workflow', 'instances', 'overdue_preview'],
    queryFn: () => workflowApi.getInstances({ queue: 'overdue', per_page: 5 }),
  });

  const { data: notifRes } = useQuery({
    queryKey: ['workflow', 'notifications'],
    queryFn: () => workflowApi.getNotifications({ per_page: 8 }),
  });

  const markReadMutation = useMutation({
    mutationFn: (id) => workflowApi.markNotificationRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['workflow', 'notifications'] }),
  });

  const markAllReadMutation = useMutation({
    mutationFn: () => workflowApi.markAllNotificationsRead(),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['workflow', 'notifications'] }),
  });

  const runEscalationsMutation = useMutation({
    mutationFn: () => workflowApi.runEscalations(),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['workflow'] });
      const stats = res?.data || {};
      setFeedback({
        variant: 'success',
        message: t(
          `এস্কেলেশন সম্পন্ন: ${stats.escalated ?? 0}টি এস্কেলেট, ${stats.reminded ?? 0}টি রিমাইন্ডার পাঠানো হয়েছে।`,
          `Escalation sweep completed: ${stats.escalated ?? 0} escalated, ${stats.reminded ?? 0} reminders sent.`
        ),
      });
    },
    onError: (err) => {
      setFeedback({
        variant: 'danger',
        message: formatWorkflowApiError(err, t('এস্কেলেশন ব্যর্থ হয়েছে।', 'Escalation sweep failed.')),
      });
    },
  });

  if (isLoading) {
    return <Loader message="কর্মপ্রবাহ ড্যাশবোর্ড লোড হচ্ছে..." messageEn="Loading workflow dashboard..." />;
  }

  if (error) {
    return (
      <div className="container-fluid py-3">
        <AlertBanner variant="danger" message={formatWorkflowApiError(error, t('তথ্য লোড ব্যর্থ হয়েছে।', 'Failed to load dashboard.'))} />
      </div>
    );
  }

  const data = dashRes?.data || {};
  const kpis = data.kpis || {};
  const byModule = data.by_module || [];
  const byLevel = data.by_approval_level || [];
  const pendingItems = pendingRes?.data || [];
  const overdueItems = overdueRes?.data || [];
  const notifications = notifRes?.data || [];
  const unreadCount = notifRes?.unread_count || 0;

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-diagram-3-fill text-primary me-2"></i>
            {t('কর্মপ্রবাহ ও অনুমোদন ব্যবস্থাপনা (M11)', 'Workflow & Approval Engine (M11)')}
          </h4>
          <p className="text-muted small mb-0">
            {t(
              'কেন্দ্রীয় কনফিগারযোগ্য বহু-স্তরীয় অনুমোদন, এস্কেলেশন এবং সিদ্ধান্তের ইতিহাস',
              'Centralized configurable multi-tier approvals, SLA escalations, and decision audit history'
            )}
          </p>
        </div>

        <div className="d-flex flex-wrap gap-2 align-items-center">
          <select
            className="form-select form-select-sm"
            style={{ width: 170 }}
            value={moduleFilter}
            onChange={(e) => setModuleFilter(e.target.value)}
          >
            <option value="">{t('সকল মডিউল', 'All Modules')}</option>
            <option value="RELIEF">RELIEF</option>
            <option value="DAMAGE">DAMAGE</option>
            <option value="PROJECT">PROJECT</option>
            <option value="BENEFICIARY">BENEFICIARY</option>
            <option value="RESOURCE">RESOURCE</option>
            <option value="WAREHOUSE">WAREHOUSE</option>
            <option value="VOLUNTEER">VOLUNTEER</option>
            <option value="GENERAL">GENERAL</option>
          </select>

          {canSubmitWorkflow(user) && (
            <Link to="/workflow/submit" className="btn btn-primary btn-sm">
              <i className="bi bi-plus-circle me-1"></i>
              {t('নতুন অনুমোদন আবেদন', 'New Workflow Request')}
            </Link>
          )}
          <Link to="/workflow/instances" className="btn btn-outline-secondary btn-sm">
            <i className="bi bi-list-task me-1"></i>
            {t('সকল কর্মপ্রবাহ', 'All Requests')}
          </Link>
          {canManageWorkflowConfig(user) && (
            <Link to="/workflow/configurations" className="btn btn-outline-dark btn-sm">
              <i className="bi bi-sliders me-1"></i>
              {t('কনফিগারেশন', 'Configure Workflows')}
            </Link>
          )}
          {canRunEscalations(user) && (
            <button
              type="button"
              className="btn btn-outline-danger btn-sm"
              disabled={runEscalationsMutation.isPending}
              onClick={() => runEscalationsMutation.mutate()}
            >
              <i className="bi bi-alarm me-1"></i>
              {t('এস্কেলেশন চালান', 'Run SLA Escalation')}
            </button>
          )}
        </div>
      </div>

      {feedback && (
        <AlertBanner variant={feedback.variant} message={feedback.message} onClose={() => setFeedback(null)} />
      )}

      {/* KPI Metric Cards */}
      <div className="row g-3 mb-4">
        <div className="col-6 col-md-4 col-xl-2">
          <Link to="/workflow/instances?queue=my_pending" className="text-decoration-none">
            <div className="idms-card p-3 h-100 border-start border-4 border-primary">
              <div className="text-muted small">{t('আমার অপেক্ষমাণ অনুমোদন', 'My Pending Approvals')}</div>
              <div className="fs-3 fw-bold text-primary">{kpis.my_pending_approvals ?? 0}</div>
            </div>
          </Link>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <Link to="/workflow/instances?queue=submitted_by_me" className="text-decoration-none">
            <div className="idms-card p-3 h-100 border-start border-4 border-info">
              <div className="text-muted small">{t('আমার দাখিলকৃত আবেদন', 'Submitted Requests')}</div>
              <div className="fs-3 fw-bold text-info">{kpis.submitted_requests ?? 0}</div>
            </div>
          </Link>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <Link to="/workflow/instances?queue=approved" className="text-decoration-none">
            <div className="idms-card p-3 h-100 border-start border-4 border-success">
              <div className="text-muted small">{t('অনুমোদিত আবেদন', 'Approved Requests')}</div>
              <div className="fs-3 fw-bold text-success">{kpis.approved_requests ?? 0}</div>
            </div>
          </Link>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <Link to="/workflow/instances?queue=rejected" className="text-decoration-none">
            <div className="idms-card p-3 h-100 border-start border-4 border-secondary">
              <div className="text-muted small">{t('প্রত্যাখ্যাত আবেদন', 'Rejected Requests')}</div>
              <div className="fs-3 fw-bold text-secondary">{kpis.rejected_requests ?? 0}</div>
            </div>
          </Link>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <Link to="/workflow/instances?queue=overdue" className="text-decoration-none">
            <div className="idms-card p-3 h-100 border-start border-4 border-danger">
              <div className="text-muted small">{t('মেয়াদোত্তীর্ণ কর্মপ্রবাহ', 'Overdue Workflows')}</div>
              <div className="fs-3 fw-bold text-danger">{kpis.overdue_workflows ?? 0}</div>
            </div>
          </Link>
        </div>
        <div className="col-6 col-md-4 col-xl-2">
          <div className="idms-card p-3 h-100 border-start border-4 border-warning">
            <div className="text-muted small">{t('এসএলএ সম্মতি ও গড় সময়', 'SLA Compliance')}</div>
            <div className="fs-4 fw-bold text-dark">{kpis.sla_compliance_rate_pct ?? 100}%</div>
            <div className="small text-muted">
              {t('গড়', 'Avg')}: {kpis.avg_turnaround_hours ?? 0}h
            </div>
          </div>
        </div>
      </div>

      {/* My Pending & Overdue Queues */}
      <div className="row g-3 mb-4">
        <div className="col-12 col-lg-6">
          <div className="idms-card h-100">
            <div className="idms-card-header d-flex justify-content-between align-items-center">
              <span>
                <i className="bi bi-inbox-fill text-primary me-2"></i>
                {t('আমার অপেক্ষমাণ অনুমোদনসমূহ', 'My Pending Approvals')}
              </span>
              <Link to="/workflow/instances?queue=my_pending" className="btn btn-sm btn-link text-decoration-none">
                {t('সব দেখুন', 'View All')}
              </Link>
            </div>
            <div className="p-3">
              {pendingItems.length === 0 ? (
                <EmptyState message={t('কোনো অপেক্ষমাণ অনুমোদন নেই।', 'No pending approvals in your queue.')} />
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm table-hover align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>{t('নম্বর', 'No.')}</th>
                        <th>{t('শিরোনাম', 'Title')}</th>
                        <th>{t('অবস্থা', 'State')}</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {pendingItems.map((item) => (
                        <tr key={item.id}>
                          <td className="fw-semibold small">{item.instance_no}</td>
                          <td>
                            <div className="small fw-semibold">{t(item.title_bn || item.title_en, item.title_en)}</div>
                            <div className="text-muted" style={{ fontSize: '0.75rem' }}>
                              {item.module_code} • {item.entity_reference}
                            </div>
                          </td>
                          <td>
                            <WorkflowStateBadge state={item.current_state} />
                            <OverdueBadge isOverdue={item.is_overdue} />
                          </td>
                          <td className="text-end">
                            <Link to={`/workflow/instances/${item.id}`} className="btn btn-outline-primary btn-sm">
                              {t('পর্যালোচনা', 'Review')}
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-12 col-lg-6">
          <div className="idms-card h-100">
            <div className="idms-card-header d-flex justify-content-between align-items-center">
              <span>
                <i className="bi bi-exclamation-octagon-fill text-danger me-2"></i>
                {t('মেয়াদোত্তীর্ণ কর্মপ্রবাহ (SLA Overdue)', 'Overdue Workflows')}
              </span>
              <Link to="/workflow/instances?queue=overdue" className="btn btn-sm btn-link text-decoration-none">
                {t('সব দেখুন', 'View All')}
              </Link>
            </div>
            <div className="p-3">
              {overdueItems.length === 0 ? (
                <EmptyState message={t('কোনো মেয়াদোত্তীর্ণ কর্মপ্রবাহ নেই।', 'No overdue workflows right now.')} />
              ) : (
                <div className="table-responsive">
                  <table className="table table-sm table-hover align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>{t('নম্বর', 'No.')}</th>
                        <th>{t('দায়িত্বপ্রাপ্ত', 'Assigned')}</th>
                        <th>{t('অগ্রাধিকার', 'Priority')}</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {overdueItems.map((item) => (
                        <tr key={item.id}>
                          <td>
                            <div className="fw-semibold small">{item.instance_no}</div>
                            <div className="text-muted" style={{ fontSize: '0.75rem' }}>{item.entity_reference}</div>
                          </td>
                          <td className="small">{item.assigned_role_code || item.assigned_user?.name_en || '-'}</td>
                          <td>
                            <WorkflowPriorityBadge priority={item.priority} />
                            <span className="badge bg-light text-dark border ms-1">Esc: {item.escalation_count}</span>
                          </td>
                          <td className="text-end">
                            <Link to={`/workflow/instances/${item.id}`} className="btn btn-outline-danger btn-sm">
                              {t('দেখুন', 'Open')}
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Performance Stats & Notifications */}
      <div className="row g-3">
        <div className="col-12 col-lg-7">
          <div className="idms-card h-100">
            <div className="idms-card-header">
              <i className="bi bi-bar-chart-fill text-success me-2"></i>
              {t('মডিউল ও স্তরভিত্তিক কর্মক্ষমতা পরিসংখ্যান', 'Workflow Performance Statistics by Module & Level')}
            </div>
            <div className="p-3">
              {byModule.length === 0 ? (
                <EmptyState message={t('এখনো কোনো কর্মপ্রবাহ তথ্য নেই।', 'No workflow performance statistics yet.')} />
              ) : (
                <div className="table-responsive mb-3">
                  <table className="table table-sm table-bordered align-middle mb-0">
                    <thead className="table-light">
                      <tr>
                        <th>{t('মডিউল', 'Module')}</th>
                        <th className="text-center">{t('মোট', 'Total')}</th>
                        <th className="text-center">{t('অপেক্ষমাণ', 'Pending')}</th>
                        <th className="text-center">{t('অনুমোদিত', 'Approved')}</th>
                        <th className="text-center">{t('প্রত্যাখ্যাত', 'Rejected')}</th>
                        <th className="text-center">{t('মেয়াদোত্তীর্ণ', 'Overdue')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {byModule.map((m) => (
                        <tr key={m.module_code}>
                          <td className="fw-semibold">{m.module_code}</td>
                          <td className="text-center">{m.total}</td>
                          <td className="text-center text-primary fw-semibold">{m.pending}</td>
                          <td className="text-center text-success fw-semibold">{m.approved}</td>
                          <td className="text-center text-danger">{m.rejected}</td>
                          <td className="text-center text-warning fw-semibold">{m.overdue}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {byLevel.length > 0 && (
                <div>
                  <h6 className="small fw-bold text-muted mb-2">{t('সক্রিয় স্তর অনুযায়ী বণ্টন', 'Active Workload by Approval Level')}</h6>
                  <div className="d-flex flex-wrap gap-2">
                    {byLevel.map((lvl) => (
                      <div key={lvl.approval_level} className="border rounded px-3 py-2 bg-light small">
                        <div className="fw-semibold"><WorkflowLevelLabel level={lvl.approval_level} /></div>
                        <div className="text-muted">
                          {t('সক্রিয়', 'Active')}: <strong>{lvl.count}</strong> | {t('বিলম্বিত', 'Overdue')}: <strong className="text-danger">{lvl.overdue}</strong>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className="col-12 col-lg-5">
          <div className="idms-card h-100">
            <div className="idms-card-header d-flex justify-content-between align-items-center">
              <span>
                <i className="bi bi-bell-fill text-warning me-2"></i>
                {t('বিজ্ঞপ্তি ও রিমাইন্ডার', 'Notifications & Reminders')}
                {unreadCount > 0 && <span className="badge bg-danger ms-2">{unreadCount}</span>}
              </span>
              {unreadCount > 0 && (
                <button
                  type="button"
                  className="btn btn-sm btn-outline-secondary"
                  onClick={() => markAllReadMutation.mutate()}
                >
                  {t('সব পঠিত চিহ্নিত করুন', 'Mark All Read')}
                </button>
              )}
            </div>
            <div className="p-3">
              {notifications.length === 0 ? (
                <EmptyState message={t('কোনো নতুন বিজ্ঞপ্তি নেই।', 'No notifications available.')} />
              ) : (
                <div className="list-group list-group-flush">
                  {notifications.map((n) => (
                    <div key={n.id} className={`list-group-item px-0 ${!n.is_read ? 'bg-light' : ''}`}>
                      <div className="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div className="fw-semibold small">
                            {!n.is_read && <span className="badge bg-primary me-1">{t('নতুন', 'New')}</span>}
                            {t(n.title_bn, n.title_en)}
                          </div>
                          <div className="text-muted small">{t(n.message_bn, n.message_en)}</div>
                        </div>
                        <div className="d-flex gap-1">
                          {n.workflow_instance_id && (
                            <Link to={`/workflow/instances/${n.workflow_instance_id}`} className="btn btn-outline-primary btn-sm">
                              <i className="bi bi-arrow-right"></i>
                            </Link>
                          )}
                          {!n.is_read && (
                            <button
                              type="button"
                              className="btn btn-outline-success btn-sm"
                              onClick={() => markReadMutation.mutate(n.id)}
                              title={t('পঠিত চিহ্নিত করুন', 'Mark as read')}
                            >
                              <i className="bi bi-check2"></i>
                            </button>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default WorkflowDashboardPage;
