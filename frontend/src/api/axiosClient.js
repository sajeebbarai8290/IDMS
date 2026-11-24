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

let isRefreshing = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  failedQueue = [];
};

// Response Interceptor: Standardized Error Handling & Automatic Silent Refresh
axiosClient.interceptors.response.use(
  (response) => response.data,
  async (error) => {
    const originalRequest = error.config;
    const status = error.response ? error.response.status : null;

    // Handle 401 Unauthorized via transparent OIDC Silent Token Refresh
    if (status === 401 && originalRequest && !originalRequest._retry) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`;
            return axiosClient(originalRequest);
          })
          .catch((err) => Promise.reject(err));
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const newToken = await keycloakService.refreshToken();
        if (newToken) {
          processQueue(null, newToken);
          originalRequest.headers.Authorization = `Bearer ${newToken}`;
          return axiosClient(originalRequest);
        } else {
          processQueue(new Error('Session refresh failed'), null);
          keycloakService.clearSession();
          if (window.location.pathname !== '/login') {
            window.location.href = '/login';
          }
        }
      } catch (refreshErr) {
        processQueue(refreshErr, null);
        keycloakService.clearSession();
        if (window.location.pathname !== '/login') {
          window.location.href = '/login';
        }
      } finally {
        isRefreshing = false;
      }
    }

    const apiError = error.response?.data?.error || {
      code: status === 403 ? 'FORBIDDEN' : status === 401 ? 'UNAUTHORIZED' : 'NETWORK_ERROR',
      message: error.response?.data?.message || error.message || 'Network connection failed.',
    };

    return Promise.reject(apiError);
  }
);

export default axiosClient;
