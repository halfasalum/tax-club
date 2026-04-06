// src/api/services/authService.ts
import { BaseService } from "../api/baseService";

// Request/Response types
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

export interface LoginResponse {
  user: User;
  token: string;
  refresh_token?: string;
  permissions?: string[];
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
  institution?: {
    institution_id: string;
    name: string;
  } | null;
}

export interface RefreshTokenResponse {
  token: string;
}

class AuthService extends BaseService {
  async register(data: RegisterRequest): Promise<LoginResponse> {
    const response = await this.post<LoginResponse>('/auth/register', data);
    
    if (response.token) {
      localStorage.setItem('auth_token', response.token);
      if (response.refresh_token) {
        localStorage.setItem('refresh_token', response.refresh_token);
      }
      localStorage.setItem('user', JSON.stringify(response.user));
    }
    
    return response;
  }

  async login(data: LoginRequest): Promise<LoginResponse> {
    const response = await this.post<LoginResponse>('/auth/login', data);
    
    if (response.token) {
      localStorage.setItem('auth_token', response.token);
      if (response.refresh_token) {
        localStorage.setItem('refresh_token', response.refresh_token);
      }
      localStorage.setItem('user', JSON.stringify(response.user));
      if (response.permissions) {
        localStorage.setItem('permissions', JSON.stringify(response.permissions));
      }
    }
    
    return response;
  }

  async logout(): Promise<void> {
    try {
      await this.post('/auth/logout');
    } finally {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('refresh_token');
      localStorage.removeItem('user');
      localStorage.removeItem('permissions');
    }
  }

  async getCurrentUser(): Promise<{ user: User; roles: any[]; permissions: string[] }> {
    const response = await this.get<{ user: User; roles: any[]; permissions: string[] }>('/auth/me');
    
    if (response.user) {
      localStorage.setItem('user', JSON.stringify(response.user));
    }
    if (response.permissions) {
      localStorage.setItem('permissions', JSON.stringify(response.permissions));
    }
    
    return response;
  }

  async changePassword(data: { current_password: string; password: string; password_confirmation: string }): Promise<{ message: string }> {
    return this.post('/auth/change-password', data);
  }

  async verifyAccount(): Promise<{ message: string }> {
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
}

export const authService = new AuthService();