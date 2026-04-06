// src/api/services/authService.ts
import { BaseService } from '../baseService';

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
  user_type: string;
  membership_id: string;
  is_verified: boolean;
  created_at: string;
  last_login: string | null;
  institution: {
    institution_id: string;
    name: string;
  } | null;
}

class AuthService extends BaseService {
  async login(data: { email: string; password: string }): Promise<LoginResponse> {
    const response = await this.post<LoginResponse>('/auth/login', data);
    
    if (response.success && response.data) {
      const { token, user, permissions } = response.data;
      
      // Store everything locally
      localStorage.setItem('auth_token', token);
      localStorage.setItem('user', JSON.stringify(user));
      localStorage.setItem('permissions', JSON.stringify(permissions));
      localStorage.setItem('last_auth_check', Date.now().toString());
    }
    
    return response;
  }

  async logout(): Promise<void> {
    try {
      await this.post('/auth/logout');
    } finally {
      // Clear all local storage
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      localStorage.removeItem('permissions');
      localStorage.removeItem('last_auth_check');
    }
  }

  // Only call this when needed (e.g., after profile update, or periodically)
  async refreshUserData(): Promise<void> {
    try {
      const response = await this.get<{ data: { user: User; permissions: string[] } }>('/auth/me');
      
      if (response.data) {
        localStorage.setItem('user', JSON.stringify(response.data.user));
        localStorage.setItem('permissions', JSON.stringify(response.data.permissions));
        localStorage.setItem('last_auth_check', Date.now().toString());
      }
    } catch (error) {
      console.error('Failed to refresh user data:', error);
    }
  }

  isAuthenticated(): boolean {
    const token = localStorage.getItem('auth_token');
    return !!token;
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

  // Optional: Check if we need to refresh (e.g., every 30 minutes)
  shouldRefreshUserData(): boolean {
    const lastCheck = localStorage.getItem('last_auth_check');
    if (!lastCheck) return true;
    
    const THIRTY_MINUTES = 30 * 60 * 1000;
    return Date.now() - parseInt(lastCheck) > THIRTY_MINUTES;
  }
}

export const authService = new AuthService();