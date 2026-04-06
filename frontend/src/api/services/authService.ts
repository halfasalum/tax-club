// src/api/services/authService.ts
import { BaseService } from '../baseService';

// Request/Response types matching your backend exactly
export interface RegisterRequest {
  full_name: string;
  email: string;
  phone?: string;
  password: string;
  password_confirmation: string;
  user_type: 'secondary' | 'college' | 'university' | 'alumni';
  institution_id?: string;
}

export interface LoginRequest {
  email: string;
  password: string;
}

// This matches your backend response structure
export interface LoginResponse {
  success: boolean;
  message: string;
  data: {
    user: User;
    token: string;
    permissions: string[];
  };
}

export interface User {
  user_id: string;
  full_name: string;
  email: string;
  phone: string | null;
  user_type: 'secondary' | 'college' | 'university' | 'alumni';
  membership_id: string;
  is_verified: boolean;
  created_at: string;
  last_login: string | null;
  institution: {
    institution_id: string;
    name: string;
  } | null;
}

export interface MeResponse {
  success: boolean;
  message?: string;
  data: {
    user: User;
    roles: Array<{ name: string; slug: string }>;
    permissions: string[];
  };
}

export interface ChangePasswordRequest {
  current_password: string;
  password: string;
  password_confirmation: string;
}

class AuthService extends BaseService {
  async register(data: RegisterRequest): Promise<LoginResponse> {
    const response = await this.post<LoginResponse>('/auth/register', data);
    
    // Store data if registration successful
    if (response.success && response.data) {
      const { token, user, permissions } = response.data;
      
      if (token) {
        localStorage.setItem('auth_token', token);
        localStorage.setItem('user', JSON.stringify(user));
        if (permissions && permissions.length > 0) {
          localStorage.setItem('permissions', JSON.stringify(permissions));
        }
      }
    }
    
    return response;
  }

  async login(data: LoginRequest): Promise<LoginResponse> {
    const response = await this.post<LoginResponse>('/auth/login', data);
    
    console.log('Login API Response:', response); // Debug log
    
    // Store data if login successful
    if (response.success && response.data) {
      const { token, user, permissions } = response.data;
      
      console.log('Storing token:', token); // Debug log
      console.log('Storing user:', user); // Debug log
      
      if (token) {
        localStorage.setItem('auth_token', token);
        localStorage.setItem('user', JSON.stringify(user));
        if (permissions && permissions.length > 0) {
          localStorage.setItem('permissions', JSON.stringify(permissions));
        }
      }
    }
    
    return response;
  }

  async logout(): Promise<{ success: boolean; message: string }> {
    try {
      const response = await this.post<{ success: boolean; message: string }>('/auth/logout');
      return response;
    } finally {
      // Always clear local storage even if API call fails
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      localStorage.removeItem('permissions');
    }
  }

  async getCurrentUser(): Promise<MeResponse> {
    const response = await this.get<MeResponse>('/auth/me');
    
    // Update stored user data if we have fresh data
    if (response.success && response.data) {
      localStorage.setItem('user', JSON.stringify(response.data.user));
      if (response.data.permissions) {
        localStorage.setItem('permissions', JSON.stringify(response.data.permissions));
      }
    }
    
    return response;
  }

  async changePassword(data: ChangePasswordRequest): Promise<{ success: boolean; message: string }> {
    return this.post('/auth/change-password', data);
  }

  async verifyAccount(): Promise<{ success: boolean; message: string }> {
    return this.post('/auth/verify');
  }

  isAuthenticated(): boolean {
    const token = localStorage.getItem('auth_token');
    return !!token;
  }

  getToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  getUser(): User | null {
    const userStr = localStorage.getItem('user');
    if (!userStr) return null;
    try {
      return JSON.parse(userStr);
    } catch {
      return null;
    }
  }

  getPermissions(): string[] {
    const permissionsStr = localStorage.getItem('permissions');
    if (!permissionsStr) return [];
    try {
      return JSON.parse(permissionsStr);
    } catch {
      return [];
    }
  }

  hasPermission(permission: string): boolean {
    const permissions = this.getPermissions();
    return permissions.includes(permission);
  }
}

export const authService = new AuthService();