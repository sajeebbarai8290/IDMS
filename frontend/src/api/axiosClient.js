import axios from 'axios';
import { keycloakService } from './keycloakService';

/**
 * Centralized Axios HTTP Client
 * Handles token attachment, response normalization, and error interceptors.
 */
const axiosClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request Interceptor: Attach Keycloak Bearer Token
axiosClient.interceptors.request.use(
  (config) => {
    const token = keycloakService.getToken();
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response Interceptor: Standardized Error Handling
axiosClient.interceptors.response.use(
  (response) => response.data,
  (error) => {
    const status = error.response ? error.response.status : null;
    const apiError = error.response?.data?.error || {
      code: 'NETWORK_ERROR',
      message: error.message || 'Network connection failed. Please check your internet or server status.',
    };

    if (status === 401) {
      // Token expired or unauthenticated
      keycloakService.logout();
    }

    return Promise.reject(apiError);
  }
);

export default axiosClient;
