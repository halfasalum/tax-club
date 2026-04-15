// src/api/axiosInstance.ts
import axios, { type AxiosInstance, type AxiosRequestConfig, type AxiosResponse, type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { notifications } from '@mantine/notifications';

// Types for your backend responses
export interface ApiSuccessResponse<T = any> {
  success: true;
  message: string;
  data: T;
}

export interface ApiErrorResponse {
  success: false;
  message: string;
  errors?: any;
}

export type ApiResponse<T = any> = ApiSuccessResponse<T> | ApiErrorResponse;

// Store pending requests for token refresh
let isRefreshing = false;
let failedQueue: Array<{
  resolve: (value?: any) => void;
  reject: (reason?: any) => void;
  config: InternalAxiosRequestConfig;
}> = [];

const processQueue = (error: any = null, token: string | null = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error);
    } else if (token && prom.config.headers) {
      prom.config.headers.Authorization = `Bearer ${token}`;
      prom.resolve(axiosInstance(prom.config));
    }
  });
  failedQueue = [];
};

// Create axios instance
const axiosInstance: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'https://taxclub.flux.co.tz/api/v1',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});


// Add request interceptor for debugging
axiosInstance.interceptors.request.use(
  (config) => {
    console.log('Making request to:', config.url);
    console.log('With headers:', config.headers);
    return config;
  },
  (error) => {
    console.error('Request error:', error);
    return Promise.reject(error);
  }
);

// Add response interceptor for debugging
axiosInstance.interceptors.response.use(
  (response) => {
    console.log('Response from:', response.config.url);
    console.log('Response data:', response.data);
    return response;
  },
  (error) => {
    console.error('Response error:', error.response?.data);
    return Promise.reject(error);
  }
);

// Request interceptor - Add token to every request
axiosInstance.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem('auth_token');
    
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    return config;
  },
  (error: AxiosError) => {
    console.error('Request interceptor error:', error);
    return Promise.reject(error);
  }
);

// Response interceptor - Handle responses and token refresh
axiosInstance.interceptors.response.use(
  (response: AxiosResponse) => {
    // Return the response as-is - our services will handle the structure
    return response;
  },
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };
    
    // If error is not 401 or request already retried, reject
    if (error.response?.status !== 401 || originalRequest._retry) {
      // Don't show notification for auth endpoints to avoid spam
      const isAuthEndpoint = originalRequest.url?.includes('/auth/');
      if (!isAuthEndpoint && error.response?.status !== 401) {
        const errorData = error.response?.data as ApiErrorResponse;
        notifications.show({
          title: 'Error',
          message: errorData?.message || 'An error occurred',
          color: 'red',
          autoClose: 5000,
        });
      }
      return Promise.reject(error);
    }

    // Mark request as retried
    originalRequest._retry = true;

    // If already refreshing, add to queue
    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        failedQueue.push({ resolve, reject, config: originalRequest });
      });
    }

    isRefreshing = true;

    try {
      // Attempt to refresh token - adjust endpoint as needed
      const refreshToken = localStorage.getItem('refresh_token');
      
      if (!refreshToken) {
        throw new Error('No refresh token available');
      }

      // Call refresh endpoint (implement if your backend has this)
      const response = await axios.post(
        `${import.meta.env.VITE_API_URL}/auth/refresh`,
        { refresh_token: refreshToken }
      );

      const { token } = response.data.data;
      
      // Store new token
      localStorage.setItem('auth_token', token);
      
      // Update authorization header
      if (originalRequest.headers) {
        originalRequest.headers.Authorization = `Bearer ${token}`;
      }
      
      // Process queued requests
      processQueue(null, token);
      
      // Retry original request
      return axiosInstance(originalRequest);
      
    } catch (refreshError) {
      // Refresh failed - clear tokens and redirect to login
      console.error('Token refresh failed:', refreshError);
      
      localStorage.removeItem('auth_token');
      localStorage.removeItem('refresh_token');
      localStorage.removeItem('user');
      localStorage.removeItem('permissions');
      
      // Process queue with error
      processQueue(refreshError, null);
      
      // Redirect to login if not already there
      if (!window.location.pathname.includes('/auth/')) {
        notifications.show({
          title: 'Session Expired',
          message: 'Please login again to continue',
          color: 'red',
        });
        window.location.href = '/auth/signin';
      }
      
      return Promise.reject(refreshError);
    } finally {
      isRefreshing = false;
    }
  }
);

export default axiosInstance;