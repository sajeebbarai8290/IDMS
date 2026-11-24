import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './ProtectedRoute';
import MainLayout from '../components/layout/MainLayout';
import LoginPage from '../modules/auth/LoginPage';
import DashboardPage from '../modules/dashboard/DashboardPage';
import HealthPage from '../modules/system/HealthPage';
import UnauthorizedPage from '../modules/errors/UnauthorizedPage';
import NotFoundPage from '../modules/errors/NotFoundPage';

export const AppRoutes = () => {
  return (
    <Routes>
      {/* Public Authentication Route */}
      <Route path="/login" element={<LoginPage />} />

      {/* Protected Application Routes */}
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

      {/* Root Redirection */}
      <Route path="/" element={<Navigate to="/dashboard" replace />} />

      {/* Error Routes */}
      <Route path="/unauthorized" element={<UnauthorizedPage />} />
      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  );
};

export default AppRoutes;
