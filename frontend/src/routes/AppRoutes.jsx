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

      {/* Root Redirection */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />

      {/* Error Routes */}
      <Route path="/unauthorized" element={<UnauthorizedPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
};

export default AppRoutes;
