import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './ProtectedRoute';
import MainLayout from '../components/layout/MainLayout';
import LoginPage from '../modules/auth/LoginPage';
import CallbackPage from '../modules/auth/CallbackPage';
import DashboardPage from '../modules/dashboard/DashboardPage';
import HealthPage from '../modules/system/HealthPage';
import UnauthorizedPage from '../modules/errors/UnauthorizedPage';
import NotFoundPage from '../modules/errors/NotFoundPage';

// Module 01: Beneficiary Management Pages
import BeneficiaryDashboardPage from '../modules/beneficiary/pages/BeneficiaryDashboardPage';
import BeneficiaryListPage from '../modules/beneficiary/pages/BeneficiaryListPage';
import BeneficiaryRegistrationPage from '../modules/beneficiary/pages/BeneficiaryRegistrationPage';
import BeneficiaryDetailPage from '../modules/beneficiary/pages/BeneficiaryDetailPage';
import BeneficiaryEditPage from '../modules/beneficiary/pages/BeneficiaryEditPage';
import DuplicateReviewPage from '../modules/beneficiary/pages/DuplicateReviewPage';
import ImportExportPage from '../modules/beneficiary/pages/ImportExportPage';

// Module 02: National ID Verification Pages
import NidDashboardPage from '../modules/nid/pages/NidDashboardPage';
import NidVerifyPage from '../modules/nid/pages/NidVerifyPage';
import NidVerificationListPage from '../modules/nid/pages/NidVerificationListPage';
import NidVerificationDetailPage from '../modules/nid/pages/NidVerificationDetailPage';

// Module 03: Disaster Information Management Pages
import DisasterDashboardPage from '../modules/disaster/pages/DisasterDashboardPage';
import DisasterListPage from '../modules/disaster/pages/DisasterListPage';
import DisasterFormPage from '../modules/disaster/pages/DisasterFormPage';
import DisasterDetailPage from '../modules/disaster/pages/DisasterDetailPage';
import DisasterMapPage from '../modules/disaster/pages/DisasterMapPage';

// Module 04: Relief Distribution Management Pages
import ReliefDashboardPage from '../modules/relief/pages/ReliefDashboardPage';
import ReliefRequisitionListPage from '../modules/relief/pages/ReliefRequisitionListPage';
import ReliefRequisitionFormPage from '../modules/relief/pages/ReliefRequisitionFormPage';
import ReliefRequisitionDetailPage from '../modules/relief/pages/ReliefRequisitionDetailPage';
import ReliefAllocationListPage from '../modules/relief/pages/ReliefAllocationListPage';
import ReliefAllocationFormPage from '../modules/relief/pages/ReliefAllocationFormPage';
import ReliefAllocationDetailPage from '../modules/relief/pages/ReliefAllocationDetailPage';
import ReliefDistributionPage from '../modules/relief/pages/ReliefDistributionPage';

// Module 05: Disaster Response Management Pages
import ResponseDashboardPage from '../modules/response/pages/ResponseDashboardPage';
import ResponseOperationListPage from '../modules/response/pages/ResponseOperationListPage';
import ResponseOperationFormPage from '../modules/response/pages/ResponseOperationFormPage';
import ResponseOperationDetailPage from '../modules/response/pages/ResponseOperationDetailPage';
import ResponseMapPage from '../modules/response/pages/ResponseMapPage';

// Module 06: Project Management Pages
import ProjectDashboardPage from '../modules/project/pages/ProjectDashboardPage';
import ProjectListPage from '../modules/project/pages/ProjectListPage';
import ProjectFormPage from '../modules/project/pages/ProjectFormPage';
import ProjectDetailPage from '../modules/project/pages/ProjectDetailPage';
import ProjectReportsPage from '../modules/project/pages/ProjectReportsPage';
import ProjectMapPage from '../modules/project/pages/ProjectMapPage';

// Module 07: Resource Management Pages
import ResourceDashboardPage from '../modules/resource/pages/ResourceDashboardPage';
import ResourceListPage from '../modules/resource/pages/ResourceListPage';
import ResourceFormPage from '../modules/resource/pages/ResourceFormPage';
import ResourceDetailPage from '../modules/resource/pages/ResourceDetailPage';
import ResourceAllocationPage from '../modules/resource/pages/ResourceAllocationPage';
import ResourceMaintenancePage from '../modules/resource/pages/ResourceMaintenancePage';

// Module 08: Warehouse & Inventory Management Pages
import { WarehouseDashboardPage } from '../modules/warehouse/pages/WarehouseDashboardPage';
import { WarehouseFacilityListPage } from '../modules/warehouse/pages/WarehouseFacilityListPage';
import { WarehouseFacilityFormPage } from '../modules/warehouse/pages/WarehouseFacilityFormPage';
import { WarehouseItemListPage } from '../modules/warehouse/pages/WarehouseItemListPage';
import { WarehouseInventoryPage } from '../modules/warehouse/pages/WarehouseInventoryPage';
import { StockReceiptPage } from '../modules/warehouse/pages/StockReceiptPage';
import { WarehouseTransferPage } from '../modules/warehouse/pages/WarehouseTransferPage';
import { WarehouseReliefIssuePage } from '../modules/warehouse/pages/WarehouseReliefIssuePage';
import { WarehouseReportsPage } from '../modules/warehouse/pages/WarehouseReportsPage';
import HumanResourceDeploymentPage from '../modules/resource/pages/HumanResourceDeploymentPage';
import ResourceMapPage from '../modules/resource/pages/ResourceMapPage';

// Module 09: Damage Assessment (D-Form) Pages
import DamageDashboardPage from '../modules/damage/pages/DamageDashboardPage';
import DamageAssessmentListPage from '../modules/damage/pages/DamageAssessmentListPage';
import DamageAssessmentFormPage from '../modules/damage/pages/DamageAssessmentFormPage';
import DamageAssessmentDetailPage from '../modules/damage/pages/DamageAssessmentDetailPage';
import DamageMapPage from '../modules/damage/pages/DamageMapPage';
import DamageReportsPage from '../modules/damage/pages/DamageReportsPage';

// Module 10: Volunteer Management Pages
import VolunteerDashboardPage from '../modules/volunteer/pages/VolunteerDashboardPage';
import VolunteerListPage from '../modules/volunteer/pages/VolunteerListPage';
import VolunteerFormPage from '../modules/volunteer/pages/VolunteerFormPage';
import VolunteerDetailPage from '../modules/volunteer/pages/VolunteerDetailPage';
import VolunteerOrganizationPage from '../modules/volunteer/pages/VolunteerOrganizationPage';
import VolunteerDeploymentPage from '../modules/volunteer/pages/VolunteerDeploymentPage';
import VolunteerTrainingPage from '../modules/volunteer/pages/VolunteerTrainingPage';
import VolunteerReportsPage from '../modules/volunteer/pages/VolunteerReportsPage';

export const AppRoutes = () => {
  return (
    <Routes>
      {/* Public Authentication Routes */}
      <Route path="/login" element={<LoginPage />} />
      <Route path="/auth/callback" element={<CallbackPage />} />

      {/* Protected Core Application Routes */}
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/system/health"
        element={
          <ProtectedRoute>
            <MainLayout>
              <HealthPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 01: Beneficiary Management Routes */}
      <Route
        path="/beneficiary/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <BeneficiaryDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/beneficiary/households"
        element={
          <ProtectedRoute>
            <MainLayout>
              <BeneficiaryListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/beneficiary/register"
        element={
          <ProtectedRoute>
            <MainLayout>
              <BeneficiaryRegistrationPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/beneficiary/households/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <BeneficiaryDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/beneficiary/households/:id/edit"
        element={
          <ProtectedRoute>
            <MainLayout>
              <BeneficiaryEditPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/beneficiary/duplicates"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DuplicateReviewPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      <Route
        path="/beneficiary/import-export"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ImportExportPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 02: National ID Verification Routes */}
      <Route
        path="/nid/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <NidDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/nid/verify"
        element={
          <ProtectedRoute>
            <MainLayout>
              <NidVerifyPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/nid/verifications"
        element={
          <ProtectedRoute>
            <MainLayout>
              <NidVerificationListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/nid/verifications/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <NidVerificationDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 03: Disaster Information Management Routes */}
      <Route
        path="/disaster/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DisasterDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/disaster/events"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DisasterListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/disaster/events/new"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DisasterFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/disaster/events/:id/edit"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DisasterFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/disaster/events/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DisasterDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/disaster/map"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DisasterMapPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 04: Relief Distribution Management Routes */}
      <Route
        path="/relief/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ReliefDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/relief/requisitions"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ReliefRequisitionListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/relief/requisitions/new"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ReliefRequisitionFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/relief/requisitions/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ReliefRequisitionDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/relief/allocations"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ReliefAllocationListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/relief/allocations/new"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ReliefAllocationFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/relief/allocations/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ReliefAllocationDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/relief/distributions"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ReliefDistributionPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 05: Disaster Response Management Routes */}
      <Route
        path="/response/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResponseDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/response/operations"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResponseOperationListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/response/operations/new"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResponseOperationFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/response/operations/:id/edit"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResponseOperationFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/response/operations/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResponseOperationDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/response/map"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResponseMapPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 06: Project Management Routes */}
      <Route
        path="/project/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ProjectDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/project/schemes"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ProjectListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/project/schemes/new"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ProjectFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/project/schemes/:id/edit"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ProjectFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/project/schemes/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ProjectDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/project/reports"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ProjectReportsPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/project/map"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ProjectMapPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 07: Resource Management Routes */}
      <Route
        path="/resource/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResourceDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/resource/assets"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResourceListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/resource/assets/new"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResourceFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/resource/assets/:id/edit"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResourceFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/resource/assets/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResourceDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/resource/allocations"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResourceAllocationPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/resource/maintenance"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResourceMaintenancePage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/resource/human-deployments"
        element={
          <ProtectedRoute>
            <MainLayout>
              <HumanResourceDeploymentPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/resource/map"
        element={
          <ProtectedRoute>
            <MainLayout>
              <ResourceMapPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 08: Warehouse & Inventory Management Routes */}
      <Route
        path="/warehouse/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/facilities"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseFacilityListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/facilities/new"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseFacilityFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/facilities/:id/edit"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseFacilityFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/items"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseItemListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/inventory"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseInventoryPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/receipts"
        element={
          <ProtectedRoute>
            <MainLayout>
              <StockReceiptPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/transfers"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseTransferPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/relief-issue"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseReliefIssuePage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/warehouse/reports"
        element={
          <ProtectedRoute>
            <MainLayout>
              <WarehouseReportsPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 09: Damage Assessment (D-Form) Routes */}
      <Route
        path="/damage/dashboard"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DamageDashboardPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/damage/assessments"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DamageAssessmentListPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/damage/assessments/new"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DamageAssessmentFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/damage/assessments/:id"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DamageAssessmentDetailPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/damage/assessments/:id/edit"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DamageAssessmentFormPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/damage/map"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DamageMapPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/damage/reports"
        element={
          <ProtectedRoute>
            <MainLayout>
              <DamageReportsPage />
            </MainLayout>
          </ProtectedRoute>
        }
      />

      {/* Module 10: Volunteer Management Routes */}
      <Route path="/volunteer/dashboard" element={<ProtectedRoute><MainLayout><VolunteerDashboardPage /></MainLayout></ProtectedRoute>} />
      <Route path="/volunteer/volunteers" element={<ProtectedRoute><MainLayout><VolunteerListPage /></MainLayout></ProtectedRoute>} />
      <Route path="/volunteer/volunteers/new" element={<ProtectedRoute><MainLayout><VolunteerFormPage /></MainLayout></ProtectedRoute>} />
      <Route path="/volunteer/volunteers/:id" element={<ProtectedRoute><MainLayout><VolunteerDetailPage /></MainLayout></ProtectedRoute>} />
      <Route path="/volunteer/volunteers/:id/edit" element={<ProtectedRoute><MainLayout><VolunteerFormPage /></MainLayout></ProtectedRoute>} />
      <Route path="/volunteer/organizations" element={<ProtectedRoute><MainLayout><VolunteerOrganizationPage /></MainLayout></ProtectedRoute>} />
      <Route path="/volunteer/deployments" element={<ProtectedRoute><MainLayout><VolunteerDeploymentPage /></MainLayout></ProtectedRoute>} />
      <Route path="/volunteer/training" element={<ProtectedRoute><MainLayout><VolunteerTrainingPage /></MainLayout></ProtectedRoute>} />
      <Route path="/volunteer/reports" element={<ProtectedRoute><MainLayout><VolunteerReportsPage /></MainLayout></ProtectedRoute>} />

      {/* Root Redirection */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />

      {/* Error Routes */}
      <Route path="/unauthorized" element={<UnauthorizedPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
};

export default AppRoutes;
