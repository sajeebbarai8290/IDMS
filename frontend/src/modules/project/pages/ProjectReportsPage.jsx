import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { projectApi } from '../../../api/projectApi';
import { useAuth } from '../../../hooks/useAuth';
import { useLanguage } from '../../../contexts/LanguageContext';
import Loader from '../../../components/common/Loader';
import AlertBanner from '../../../components/common/AlertBanner';
import { ProjectStatusBadge, MilestoneApprovalBadge } from '../components/ProjectStatusBadges';
import {
  canExportProjects,
  formatBdt,
  formatProjectApiError,
  PROJECT_STATUS_META,
  triggerBlobDownload,
} from '../utils/projectPermissions';

const REPORT_TABS = [
  { key: 'summary', bn: 'প্রকল্প সারসংক্ষেপ', en: 'Project Summary' },
  { key: 'financial', bn: 'আর্থিক অগ্রগতি', en: 'Financial Progress' },
  { key: 'physical', bn: 'ভৌত অগ্রগতি ও বিলম্ব', en: 'Physical & Delay' },
  { key: 'milestones', bn: 'মাইলস্টোন প্রতিবেদন', en: 'Milestone Report' },
  { key: 'monitoring', bn: 'পরিদর্শন ও পর্যবেক্ষণ', en: 'Monitoring Visits' },
];

export const ProjectReportsPage = () => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const [reportType, setReportType] = useState('summary');
  const [status, setStatus] = useState('');

  const { data: repRes, isLoading, error } = useQuery({
    queryKey: ['project', 'reports', reportType, status],
    queryFn: () => projectApi.getReports({ type: reportType, ...(status ? { status } : {}) }),
  });

  const handleExportCsv = async () => {
    const res = await projectApi.exportCsv(status ? { status } : {});
    triggerBlobDownload(res.data, `idms-project-${reportType}-report.csv`);
  };

  const rows = repRes?.data?.rows || [];

  return (
    <div className="container-fluid py-3">
      <div className="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h4 className="fw-bold mb-1">
            <i className="bi bi-file-earmark-bar-graph text-primary me-2"></i>
            {t('প্রকল্প ব্যবস্থাপনা প্রতিবেদনসমূহ', 'Project Management Reports')}
          </h4>
          <p className="text-muted small mb-0">
            {t('প্রকল্প সারসংক্ষেপ, আর্থিক, ভৌত, মাইলস্টোন ও মাঠ পরিদর্শন প্রতিবেদন', 'Summary, Financial, Physical, Milestone, and Monitoring reports')}
          </p>
        </div>

        <div className="d-flex gap-2">
          <select className="form-select form-select-sm" style={{ width: '160px' }} value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">{t('সকল অবস্থা', 'All Statuses')}</option>
            {['PROPOSED', 'UNDER_REVIEW', 'APPROVED', 'ONGOING', 'SUSPENDED', 'COMPLETED', 'CLOSED'].map((s) => (
              <option key={s} value={s}>{t(PROJECT_STATUS_META[s]?.bn || s, PROJECT_STATUS_META[s]?.en || s)}</option>
            ))}
          </select>
          {canExportProjects(user) && (
            <button type="button" className="btn btn-sm btn-outline-success" onClick={handleExportCsv}>
              <i className="bi bi-download me-1"></i>
              {t('CSV ডাউনলোড', 'Download CSV')}
            </button>
          )}
        </div>
      </div>

      <ul className="nav nav-tabs mb-3">
        {REPORT_TABS.map((tab) => (
          <li className="nav-item" key={tab.key}>
            <button
              type="button"
              className={`nav-link ${reportType === tab.key ? 'active fw-semibold' : ''}`}
              onClick={() => setReportType(tab.key)}
            >
              {t(tab.bn, tab.en)}
            </button>
          </li>
        ))}
      </ul>

      {error && <AlertBanner variant="danger" message={formatProjectApiError(error, t('প্রতিবেদন লোড ব্যর্থ', 'Failed to load report'))} />}

      {isLoading ? (
        <Loader />
      ) : (
        <div className="card border-0 shadow-sm">
          <div className="table-responsive">
            <table className="table table-sm table-hover align-middle mb-0">
              <thead className="table-light">
                {reportType === 'summary' && (
                  <tr>
                    <th>{t('কোড', 'Code')}</th>
                    <th>{t('শিরোনাম', 'Title')}</th>
                    <th>{t('ধরন', 'Category')}</th>
                    <th>{t('এলাকা', 'Jurisdiction')}</th>
                    <th>{t('অবস্থা', 'Status')}</th>
                    <th className="text-end">{t('বাজেট', 'Budget')}</th>
                    <th className="text-end">{t('ব্যয়', 'Spent')}</th>
                    <th className="text-center">{t('ভৌত %', 'Phy %')}</th>
                    <th className="text-center">{t('আর্থিক %', 'Fin %')}</th>
                  </tr>
                )}
                {reportType === 'financial' && (
                  <tr>
                    <th>{t('কোড', 'Code')}</th>
                    <th>{t('শিরোনাম', 'Title')}</th>
                    <th>{t('তহবিল উৎস', 'Funding Source')}</th>
                    <th className="text-end">{t('কার্যকর বাজেট', 'Effective Budget')}</th>
                    <th className="text-end">{t('ছাড়কৃত অর্থ', 'Released')}</th>
                    <th className="text-end">{t('ব্যয়', 'Expenditure')}</th>
                    <th className="text-end">{t('অবশিষ্ট', 'Balance')}</th>
                    <th className="text-center">{t('আর্থিক %', 'Fin %')}</th>
                  </tr>
                )}
                {reportType === 'physical' && (
                  <tr>
                    <th>{t('কোড', 'Code')}</th>
                    <th>{t('শিরোনাম', 'Title')}</th>
                    <th>{t('এলাকা', 'Jurisdiction')}</th>
                    <th>{t('সময়কাল', 'Timeline')}</th>
                    <th className="text-center">{t('ভৌত %', 'Phy %')}</th>
                    <th>{t('বিলম্ব ও কারণ', 'Delay & Reason')}</th>
                  </tr>
                )}
                {reportType === 'milestones' && (
                  <tr>
                    <th>{t('প্রকল্প', 'Project')}</th>
                    <th>{t('WBS', 'WBS')}</th>
                    <th>{t('মাইলস্টোন', 'Milestone')}</th>
                    <th>{t('ধরন', 'Type')}</th>
                    <th className="text-center">{t('ওজন %', 'Weight %')}</th>
                    <th className="text-center">{t('সম্পন্ন %', 'Done %')}</th>
                    <th>{t('অনুমোদন', 'Approval')}</th>
                  </tr>
                )}
                {reportType === 'monitoring' && (
                  <tr>
                    <th>{t('রিপোর্ট নং', 'Report No')}</th>
                    <th>{t('প্রকল্প', 'Project')}</th>
                    <th>{t('তারিখ', 'Visit Date')}</th>
                    <th>{t('পরিদর্শক', 'Inspector')}</th>
                    <th className="text-center">{t('পর্যবেক্ষিত ভৌত %', 'Observed Phy %')}</th>
                    <th>{t('বিলম্ব', 'Delay')}</th>
                    <th>{t('পর্যবেক্ষণ', 'Findings')}</th>
                  </tr>
                )}
              </thead>
              <tbody>
                {rows.length === 0 && (
                  <tr>
                    <td colSpan="9" className="text-center text-muted py-4">{t('কোনো প্রতিবেদন তথ্য নেই', 'No report records')}</td>
                  </tr>
                )}

                {reportType === 'summary' &&
                  rows.map((r) => (
                    <tr key={r.id}>
                      <td><Link to={`/project/schemes/${r.id}`}>{r.project_code}</Link></td>
                      <td>{t(r.title_bn, r.title_en)}</td>
                      <td>{r.category_en}</td>
                      <td>{r.administrative_unit}</td>
                      <td><ProjectStatusBadge status={r.status} /></td>
                      <td className="text-end">{formatBdt(r.approved_budget_bdt)}</td>
                      <td className="text-end">{formatBdt(r.expenditure_bdt)}</td>
                      <td className="text-center">{r.physical_progress_pct}%</td>
                      <td className="text-center">{r.financial_progress_pct}%</td>
                    </tr>
                  ))}

                {reportType === 'financial' &&
                  rows.map((r) => (
                    <tr key={r.id}>
                      <td><Link to={`/project/schemes/${r.id}`}>{r.project_code}</Link></td>
                      <td>{t(r.title_bn, r.title_en)}</td>
                      <td>{r.funding_source}</td>
                      <td className="text-end">{formatBdt(r.effective_budget_bdt)}</td>
                      <td className="text-end">{formatBdt(r.released_funds_bdt)}</td>
                      <td className="text-end">{formatBdt(r.expenditure_bdt)}</td>
                      <td className="text-end">{formatBdt(r.remaining_balance_bdt)}</td>
                      <td className="text-center fw-semibold">{r.financial_progress_pct}%</td>
                    </tr>
                  ))}

                {reportType === 'physical' &&
                  rows.map((r) => (
                    <tr key={r.id}>
                      <td><Link to={`/project/schemes/${r.id}`}>{r.project_code}</Link></td>
                      <td>{t(r.title_bn, r.title_en)}</td>
                      <td>{r.administrative_unit}</td>
                      <td className="small">{r.start_date} &rarr; {r.expected_end_date}</td>
                      <td className="text-center fw-semibold">{r.physical_progress_pct}%</td>
                      <td>
                        {r.is_delayed ? (
                          <span className="text-danger small">{r.delay_days}d — {r.delay_reason || t('কারণ উল্লেখিত নয়', 'No reason noted')}</span>
                        ) : (
                          <span className="text-success small">{t('সময়মতো', 'On track')}</span>
                        )}
                      </td>
                    </tr>
                  ))}

                {reportType === 'milestones' &&
                  rows.map((r, i) => (
                    <tr key={`${r.project_id}-${r.wbs_code}-${i}`}>
                      <td><Link to={`/project/schemes/${r.project_id}`}>{r.project_code}</Link></td>
                      <td className="fw-semibold">{r.wbs_code}</td>
                      <td>{r.milestone_title}</td>
                      <td><span className="badge bg-light text-dark border">{r.item_type}</span></td>
                      <td className="text-center">{r.weight_percentage}%</td>
                      <td className="text-center">{r.completion_percentage}%</td>
                      <td><MilestoneApprovalBadge status={r.approval_status} /></td>
                    </tr>
                  ))}

                {reportType === 'monitoring' &&
                  rows.map((r) => (
                    <tr key={r.report_no}>
                      <td className="fw-semibold">{r.report_no}</td>
                      <td><Link to={`/project/schemes/${r.project_id}`}>{r.project_code}</Link></td>
                      <td>{r.visit_date}</td>
                      <td>{r.inspector_name}</td>
                      <td className="text-center">{r.observed_physical_pct}%</td>
                      <td>{r.delay_observed ? <span className="badge bg-danger">{r.delay_days}d</span> : <span className="badge bg-success">OK</span>}</td>
                      <td className="small">{r.findings}</td>
                    </tr>
                  ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProjectReportsPage;
