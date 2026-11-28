import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useLanguage } from '../../contexts/LanguageContext';
import { canInitiateNid, canViewNid } from '../../modules/nid/utils/nidPermissions';
import { canCreateDisaster } from '../../modules/disaster/utils/disasterPermissions';
import {
  canCreateAllocation,
  canCreateRequisition,
  canRecordDistribution,
} from '../../modules/relief/utils/reliefPermissions';
import { canCreateOperation } from '../../modules/response/utils/responsePermissions';
import { canCreateProject } from '../../modules/project/utils/projectPermissions';
import { canWriteResourceAssets } from '../../modules/resource/utils/resourcePermissions';
import { canManageFacilities } from '../../modules/warehouse/utils/warehousePermissions';
import { canCreateDamage } from '../../modules/damage/utils/damagePermissions';
import { canWriteVolunteers } from '../../modules/volunteer/utils/volunteerPermissions';
import {
  canManageDelegations,
  canManageWorkflowConfig,
  canSubmitWorkflow,
  canViewWorkflow,
} from '../../modules/workflow/utils/workflowPermissions';
import {
  canCreateUsers,
  canManageRoleConfig,
  canViewRoles,
  canViewUsers,
} from '../../modules/user-management/utils/userPermissions';
import {
  canManageNotificationTemplates,
  canSendNotificationBroadcast,
  canViewNotificationLogs,
} from '../../modules/notification/utils/notificationPermissions';

export const Sidebar = ({ isOpen }) => {
  const { user } = useAuth();
  const { t } = useLanguage();
  const role = user?.roles?.[0] || 'GUEST';
  const showNidView = canViewNid(user);
  const showNidInitiate = canInitiateNid(user);
  const showDisasterCreate = canCreateDisaster(user);
  const showReliefReqCreate = canCreateRequisition(user);
  const showReliefAllocCreate = canCreateAllocation(user);
  const showReliefDistribute = canRecordDistribution(user);
  const showResponseOpCreate = canCreateOperation(user);
  const showProjectCreate = canCreateProject(user);
  const showResourceCreate = canWriteResourceAssets(user);
  const showWarehouseManage = canManageFacilities(user);
  const showDamageCreate = canCreateDamage(user);
  const showVolunteerCreate = canWriteVolunteers(user);
  const showWorkflowView = canViewWorkflow(user);
  const showWorkflowSubmit = canSubmitWorkflow(user);
  const showWorkflowConfig = canManageWorkflowConfig(user);
  const showWorkflowDelegations = canManageDelegations(user);
  const showUsersView = canViewUsers(user);
  const showUsersCreate = canCreateUsers(user);
  const showRolesView = canViewRoles(user);
  const showRolesManage = canManageRoleConfig(user);
  const showNotifLogs = canViewNotificationLogs(user);
  const showNotifSend = canSendNotificationBroadcast(user);
  const showNotifTemplates = canManageNotificationTemplates(user);

  return (
    <aside className={`idms-sidebar ${isOpen ? 'show' : ''}`}>
      <div className="py-2 idms-sidebar-nav">
        <div className="idms-sidebar-section-title">প্রধান মেনু (Main)</div>
        <NavLink to="/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-speedometer2 me-2"></i>
          <span>ড্যাশবোর্ড (Dashboard)</span>
        </NavLink>
        <NavLink to="/system/health" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-heart-pulse-fill me-2 text-danger"></i>
          <span>সিস্টেম অবস্থা (Health)</span>
        </NavLink>

        <div className="idms-sidebar-section-title">উপকারভোগী ব্যবস্থাপনা (M01)</div>
        <NavLink to="/beneficiary/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-pie-chart-fill me-2 text-primary"></i>
          <span>উপকারভোগী ড্যাশবোর্ড</span>
        </NavLink>
        <NavLink to="/beneficiary/households" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-people-fill me-2 text-info"></i>
          <span>মাস্টার রেজিস্ট্রি (Master List)</span>
        </NavLink>
        <NavLink to="/beneficiary/register" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-person-plus-fill me-2 text-success"></i>
          <span>নতুন পরিবার নিবন্ধন</span>
        </NavLink>
        <NavLink to="/beneficiary/duplicates" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-person-exclamation me-2 text-danger"></i>
          <span>ডুপ্লিকেট পর্যালোচনা</span>
        </NavLink>
        <NavLink to="/beneficiary/import-export" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-file-earmark-excel-fill me-2 text-success"></i>
          <span>ইমপোর্ট ও এক্সপোর্ট</span>
        </NavLink>

        {showNidView && (
          <>
            <div className="idms-sidebar-section-title">
              {t('জাতীয় পরিচয়পত্র যাচাই (M02)', 'NID Verification (M02)')}
            </div>
            <NavLink to="/nid/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
              <i className="bi bi-person-vcard-fill me-2 text-primary"></i>
              <span>{t('এনআইডি ড্যাশবোর্ড', 'NID Dashboard')}</span>
            </NavLink>
            {showNidInitiate && (
              <NavLink to="/nid/verify" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                <i className="bi bi-patch-check-fill me-2 text-success"></i>
                <span>{t('এনআইডি যাচাই করুন', 'Verify NID')}</span>
              </NavLink>
            )}
            <NavLink to="/nid/verifications" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
              <i className="bi bi-clock-history me-2 text-info"></i>
              <span>{t('যাচাই ইতিহাস', 'Verification History')}</span>
            </NavLink>
          </>
        )}

        <div className="idms-sidebar-section-title">
          {t('দুর্যোগ তথ্য ব্যবস্থাপনা (M03)', 'Disaster Info (M03)')}
        </div>
        <NavLink to="/disaster/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-tsunami me-2 text-danger"></i>
          <span>{t('দুর্যোগ ড্যাশবোর্ড', 'Disaster Dashboard')}</span>
        </NavLink>
        <NavLink to="/disaster/events" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-exclamation-diamond me-2 text-warning"></i>
          <span>{t('দুর্যোগ তালিকা', 'Disaster Registry')}</span>
        </NavLink>
        {showDisasterCreate && (
          <NavLink to="/disaster/events/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-plus-circle-fill me-2 text-danger"></i>
            <span>{t('নতুন দুর্যোগ নিবন্ধন', 'Register Disaster')}</span>
          </NavLink>
        )}
        <NavLink to="/disaster/map" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-map-fill me-2 text-primary"></i>
          <span>{t('জিআইএস মানচিত্র', 'GIS Disaster Map')}</span>
        </NavLink>

        <div className="idms-sidebar-section-title">
          {t('ত্রাণ বিতরণ ব্যবস্থাপনা (M04)', 'Relief Distribution (M04)')}
        </div>
        <NavLink to="/relief/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-box-seam-fill me-2 text-success"></i>
          <span>{t('ত্রাণ ড্যাশবোর্ড', 'Relief Dashboard')}</span>
        </NavLink>
        <NavLink to="/relief/requisitions" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-file-earmark-text me-2 text-primary"></i>
          <span>{t('ত্রাণ চাহিদাপত্র', 'Requisitions')}</span>
        </NavLink>
        {showReliefReqCreate && (
          <NavLink to="/relief/requisitions/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-plus-circle me-2 text-success"></i>
            <span>{t('নতুন চাহিদাপত্র', 'New Requisition')}</span>
          </NavLink>
        )}
        <NavLink to="/relief/allocations" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-truck me-2 text-info"></i>
          <span>{t('ত্রাণ বরাদ্দ আদেশ', 'Allocations')}</span>
        </NavLink>
        {showReliefAllocCreate && (
          <NavLink to="/relief/allocations/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-clipboard-plus me-2 text-primary"></i>
            <span>{t('নতুন বরাদ্দ আদেশ', 'New Allocation')}</span>
          </NavLink>
        )}
        <NavLink to="/relief/distributions" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-hand-index-thumb-fill me-2 text-warning"></i>
          <span>{showReliefDistribute ? t('ত্রাণ বিতরণ ও ভাউচার', 'Handover & Vouchers') : t('বিতরণ ভাউচার লেজার', 'Distribution Ledger')}</span>
        </NavLink>

        <div className="idms-sidebar-section-title">
          {t('দুর্যোগ সাড়াদান ব্যবস্থাপনা (M05)', 'Disaster Response (M05)')}
        </div>
        <NavLink to="/response/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-broadcast-pin me-2 text-danger"></i>
          <span>{t('সাড়াদান ড্যাশবোর্ড', 'Response Dashboard')}</span>
        </NavLink>
        <NavLink to="/response/operations" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-shield-exclamation me-2 text-primary"></i>
          <span>{t('সাড়াদান অভিযানসমূহ', 'Response Operations')}</span>
        </NavLink>
        {showResponseOpCreate && (
          <NavLink to="/response/operations/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-plus-circle me-2 text-danger"></i>
            <span>{t('নতুন অভিযান তৈরি', 'New Operation')}</span>
          </NavLink>
        )}
        <NavLink to="/response/map" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-map me-2 text-success"></i>
          <span>{t('সাড়াদান জিআইএস মানচিত্র', 'Response GIS Map')}</span>
        </NavLink>

        <div className="idms-sidebar-section-title">
          {t('প্রকল্প ব্যবস্থাপনা (M06)', 'Project Management (M06)')}
        </div>
        <NavLink to="/project/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-kanban-fill me-2 text-primary"></i>
          <span>{t('প্রকল্প ড্যাশবোর্ড', 'Project Dashboard')}</span>
        </NavLink>
        <NavLink to="/project/schemes" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-list-check me-2 text-info"></i>
          <span>{t('প্রকল্প তালিকা', 'Project Registry')}</span>
        </NavLink>
        {showProjectCreate && (
          <NavLink to="/project/schemes/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-plus-circle me-2 text-primary"></i>
            <span>{t('নতুন প্রকল্প নিবন্ধন', 'Register Project')}</span>
          </NavLink>
        )}
        <NavLink to="/project/reports" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-file-earmark-bar-graph me-2 text-warning"></i>
          <span>{t('প্রকল্প প্রতিবেদন', 'Project Reports')}</span>
        </NavLink>
        <NavLink to="/project/map" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-geo-alt-fill me-2 text-success"></i>
          <span>{t('প্রকল্প জিআইএস মানচিত্র', 'Project GIS Map')}</span>
        </NavLink>

        <div className="idms-sidebar-section-title">
          {t('সম্পদ ব্যবস্থাপনা (M07)', 'Resource Management (M07)')}
        </div>
        <NavLink to="/resource/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-truck-front-fill me-2 text-primary"></i>
          <span>{t('সম্পদ ড্যাশবোর্ড', 'Resource Dashboard')}</span>
        </NavLink>
        <NavLink to="/resource/assets" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-boxes me-2 text-info"></i>
          <span>{t('সম্পদ রেজিস্টার', 'Resource Register')}</span>
        </NavLink>
        {showResourceCreate && (
          <NavLink to="/resource/assets/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-plus-circle me-2 text-primary"></i>
            <span>{t('নতুন সম্পদ নিবন্ধন', 'Register Resource')}</span>
          </NavLink>
        )}
        <NavLink to="/resource/allocations" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-send-check me-2 text-success"></i>
          <span>{t('সম্পদ বরাদ্দ ও ব্যবহার', 'Allocations & Returns')}</span>
        </NavLink>
        <NavLink to="/resource/maintenance" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-wrench-adjustable-circle me-2 text-warning"></i>
          <span>{t('রক্ষণাবেক্ষণ ও পরিদর্শন', 'Maintenance & Alerts')}</span>
        </NavLink>
        <NavLink to="/resource/human-deployments" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-people-fill me-2 text-secondary"></i>
          <span>{t('মানবসম্পদ মোতায়েন', 'Human Resources')}</span>
        </NavLink>
        <NavLink to="/resource/map" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-geo-alt-fill me-2 text-danger"></i>
          <span>{t('সম্পদ জিআইএস মানচিত্র', 'Resource GIS Map')}</span>
        </NavLink>

        <div className="idms-sidebar-section-title">
          {t('গুদাম ও মজুদ ব্যবস্থাপনা (M08)', 'Warehouse & Inventory (M08)')}
        </div>
        <NavLink to="/warehouse/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-building-fill-check me-2 text-primary"></i>
          <span>{t('গুদাম ড্যাশবোর্ড ও সতর্কতা', 'Warehouse Dashboard')}</span>
        </NavLink>
        <NavLink to="/warehouse/facilities" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-houses-fill me-2 text-info"></i>
          <span>{t('গুদাম ও সংরক্ষণাগার', 'Warehouses')}</span>
        </NavLink>
        {showWarehouseManage && (
          <NavLink to="/warehouse/facilities/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-plus-circle me-2 text-primary"></i>
            <span>{t('নতুন গুদাম নিবন্ধন', 'Register Warehouse')}</span>
          </NavLink>
        )}
        <NavLink to="/warehouse/items" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-tags-fill me-2 text-secondary"></i>
          <span>{t('পণ্য ক্যাটালগ (Items)', 'Inventory Items')}</span>
        </NavLink>
        <NavLink to="/warehouse/inventory" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-boxes me-2 text-success"></i>
          <span>{t('মজুদ ও অপরিবর্তনীয় লেজার', 'Stock & Ledger')}</span>
        </NavLink>
        <NavLink to="/warehouse/receipts" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-box-arrow-in-down me-2 text-success"></i>
          <span>{t('ক্রয় ও মালামাল গ্রহণ (GRN)', 'Receiving (GRN)')}</span>
        </NavLink>
        <NavLink to="/warehouse/transfers" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-arrow-left-right me-2 text-primary"></i>
          <span>{t('আন্তঃগুদাম স্থানান্তর', 'Warehouse Transfers')}</span>
        </NavLink>
        <NavLink to="/warehouse/relief-issue" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-truck me-2 text-warning"></i>
          <span>{t('ত্রাণ বরাদ্দ স্টক ইস্যু', 'Relief Stock Issue')}</span>
        </NavLink>
        <NavLink to="/warehouse/reports" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-file-earmark-spreadsheet-fill me-2 text-danger"></i>
          <span>{t('গুদাম প্রতিবেদনসমূহ', 'Warehouse Reports')}</span>
        </NavLink>

        <div className="idms-sidebar-section-title">
          {t('ক্ষয়ক্ষতি নিরূপণ - ডি-ফর্ম (M09)', 'Damage Assessment (M09)')}
        </div>
        <NavLink to="/damage/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-clipboard2-data-fill me-2 text-danger"></i>
          <span>{t('ক্ষয়ক্ষতি ড্যাশবোর্ড', 'Damage Dashboard')}</span>
        </NavLink>
        <NavLink to="/damage/assessments" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-list-ul me-2 text-primary"></i>
          <span>{t('ডি-ফর্ম তালিকা', 'D-Form Registry')}</span>
        </NavLink>
        {showDamageCreate && (
          <NavLink to="/damage/assessments/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-plus-circle-fill me-2 text-danger"></i>
            <span>{t('নতুন ডি-ফর্ম নিবন্ধন', 'New D-Form')}</span>
          </NavLink>
        )}
        <NavLink to="/damage/map" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-geo-alt-fill me-2 text-warning"></i>
          <span>{t('ক্ষয়ক্ষতি জিআইএস ও হিটম্যাপ', 'Damage GIS & Heatmap')}</span>
        </NavLink>
        <NavLink to="/damage/reports" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-file-earmark-bar-graph-fill me-2 text-success"></i>
          <span>{t('ক্ষয়ক্ষতি প্রতিবেদন', 'Damage Reports')}</span>
        </NavLink>

        <div className="idms-sidebar-section-title">
          {t('স্বেচ্ছাসেবক ব্যবস্থাপনা (M10)', 'Volunteer Management (M10)')}
        </div>
        <NavLink to="/volunteer/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-people-fill me-2 text-primary"></i>
          <span>{t('স্বেচ্ছাসেবক ড্যাশবোর্ড', 'Volunteer Dashboard')}</span>
        </NavLink>
        <NavLink to="/volunteer/volunteers" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-person-lines-fill me-2 text-info"></i>
          <span>{t('স্বেচ্ছাসেবক রোস্টার', 'Volunteer Roster')}</span>
        </NavLink>
        {showVolunteerCreate && (
          <NavLink to="/volunteer/volunteers/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-person-plus-fill me-2 text-success"></i>
            <span>{t('নতুন স্বেচ্ছাসেবক নিবন্ধন', 'Register Volunteer')}</span>
          </NavLink>
        )}
        <NavLink to="/volunteer/organizations" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-building-check me-2 text-secondary"></i>
          <span>{t('স্বেচ্ছাসেবক সংগঠন', 'Organizations')}</span>
        </NavLink>
        <NavLink to="/volunteer/deployments" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-send-check-fill me-2 text-danger"></i>
          <span>{t('মোতায়েন ও উপস্থিতি', 'Deployments & Attendance')}</span>
        </NavLink>
        <NavLink to="/volunteer/training" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-mortarboard-fill me-2 text-warning"></i>
          <span>{t('প্রশিক্ষণ ও সনদ', 'Training & Certificates')}</span>
        </NavLink>
        <NavLink to="/volunteer/reports" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-file-earmark-spreadsheet me-2 text-success"></i>
          <span>{t('স্বেচ্ছাসেবক প্রতিবেদন', 'Volunteer Reports')}</span>
        </NavLink>

        {showWorkflowView && (
          <>
            <div className="idms-sidebar-section-title">
              {t('কর্মপ্রবাহ ব্যবস্থাপনা (M11)', 'Workflow Management (M11)')}
            </div>
            <NavLink to="/workflow/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
              <i className="bi bi-diagram-3-fill me-2 text-primary"></i>
              <span>{t('কর্মপ্রবাহ ড্যাশবোর্ড', 'Workflow Dashboard')}</span>
            </NavLink>
            <NavLink to="/workflow/instances" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
              <i className="bi bi-list-check me-2 text-info"></i>
              <span>{t('অনুমোদন ও কর্মপ্রবাহ সারি', 'Approval Queues')}</span>
            </NavLink>
            {showWorkflowSubmit && (
              <NavLink to="/workflow/submit" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                <i className="bi bi-send-plus-fill me-2 text-success"></i>
                <span>{t('নতুন অনুমোদন আবেদন', 'Submit Request')}</span>
              </NavLink>
            )}
            {showWorkflowDelegations && (
              <NavLink to="/workflow/delegations" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                <i className="bi bi-person-check-fill me-2 text-warning"></i>
                <span>{t('প্রতিনিধিত্ব নিয়মাবলী', 'Delegations')}</span>
              </NavLink>
            )}
            {showWorkflowConfig && (
              <NavLink to="/workflow/configurations" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                <i className="bi bi-sliders me-2 text-dark"></i>
                <span>{t('কর্মপ্রবাহ কনফিগারেশন', 'Workflow Configs')}</span>
              </NavLink>
            )}
          </>
        )}

        {(showUsersView || showRolesView) && (
          <>
            <div className="idms-sidebar-section-title">
              {t('ব্যবহারকারী ও আরবিএসি (M12)', 'User & Access (M12)')}
            </div>
            {showUsersView && (
              <>
                <NavLink to="/user-management/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                  <i className="bi bi-person-gear me-2 text-primary"></i>
                  <span>{t('ব্যবহারকারী ড্যাশবোর্ড', 'User Dashboard')}</span>
                </NavLink>
                <NavLink to="/user-management/users" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                  <i className="bi bi-people-fill me-2 text-info"></i>
                  <span>{t('ব্যবহারকারী তালিকা', 'User Directory')}</span>
                </NavLink>
              </>
            )}
            {showUsersCreate && (
              <NavLink to="/user-management/users/new" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                <i className="bi bi-person-plus-fill me-2 text-success"></i>
                <span>{t('নতুন ব্যবহারকারী', 'Provision User')}</span>
              </NavLink>
            )}
            {showRolesView && (
              <NavLink to="/user-management/roles" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                <i className="bi bi-shield-lock-fill me-2 text-warning"></i>
                <span>{showRolesManage ? t('ভূমিকা ও অনুমতি', 'Roles & Permissions') : t('ভূমিকা তালিকা', 'View Roles')}</span>
              </NavLink>
            )}
            {showUsersView && (
              <NavLink to="/user-management/organizations" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
                <i className="bi bi-building-fill me-2 text-secondary"></i>
                <span>{t('সংগঠন ব্যবস্থাপনা', 'Organizations')}</span>
              </NavLink>
            )}
          </>
        )}

        <div className="idms-sidebar-section-title">
          {t('বিজ্ঞপ্তি ও সতর্কতা (M13)', 'Notifications & Alerts (M13)')}
        </div>
        <NavLink to="/notifications/inbox" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-inbox-fill me-2 text-primary"></i>
          <span>{t('আমার ইনবক্স', 'My Inbox')}</span>
        </NavLink>
        {showNotifLogs && (
          <>
            <NavLink to="/notifications/dashboard" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
              <i className="bi bi-bell-fill me-2 text-warning"></i>
              <span>{t('বিজ্ঞপ্তি ড্যাশবোর্ড', 'Notification Dashboard')}</span>
            </NavLink>
            <NavLink to="/notifications/alerts" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
              <i className="bi bi-exclamation-triangle-fill me-2 text-danger"></i>
              <span>{t('সতর্কতা ও নিয়ম', 'Alerts & Rules')}</span>
            </NavLink>
          </>
        )}
        {showNotifSend && (
          <NavLink to="/notifications/send" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-megaphone-fill me-2 text-danger"></i>
            <span>{t('বিজ্ঞপ্তি প্রেরণ', 'Send Notification')}</span>
          </NavLink>
        )}
        {showNotifTemplates && (
          <NavLink to="/notifications/templates" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-file-earmark-richtext me-2 text-info"></i>
            <span>{t('বিজ্ঞপ্তি টেমপ্লেট', 'Templates')}</span>
          </NavLink>
        )}
        {showNotifLogs && (
          <NavLink to="/notifications/logs" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
            <i className="bi bi-journal-text me-2 text-secondary"></i>
            <span>{t('ডেলিভারি লগ', 'Delivery Logs')}</span>
          </NavLink>
        )}
        <NavLink to="/notifications/preferences" className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}>
          <i className="bi bi-sliders me-2 text-success"></i>
          <span>{t('পছন্দ ও ডিভাইস', 'Preferences & Devices')}</span>
        </NavLink>

        <div className="idms-sidebar-section-title">অন্যান্য মডিউল (Planned)</div>
        <div className="nav-link text-muted opacity-75 d-flex justify-content-between align-items-center">
          <span><i className="bi bi-shield-lock me-2"></i>নিরাপত্তা ও অডিট ট্রেইল</span>
          <span className="badge bg-light text-secondary border">M16</span>
        </div>

        <div className="mt-4 px-3">
          <div className="card bg-light border-0 p-2 text-center rounded">
            <small className="text-muted d-block mb-1">বর্তমান ভূমিকা (Role)</small>
            <span className="badge bg-secondary text-wrap">{role}</span>
          </div>
        </div>
      </div>
    </aside>
  );
};

export default Sidebar;
