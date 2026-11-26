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

      {/* Root Redirection */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />

      {/* Error Routes */}
      <Route path="/unauthorized" element={<UnauthorizedPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
};

export default AppRoutes;
