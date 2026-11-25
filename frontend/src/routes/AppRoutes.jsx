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

      {/* Root Redirection */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />

      {/* Error Routes */}
      <Route path="/unauthorized" element={<UnauthorizedPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
};

export default AppRoutes;
