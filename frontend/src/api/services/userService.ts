// src/api/services/userService.ts
import { BaseService } from '../baseService';

import type { User } from './authService';

export type { User };

export interface UpdateUserRequest {
  name?: string;
  email?: string;
  avatar?: string;
}

class UserService extends BaseService {
  async getUsers(params?: { page?: number; limit?: number; search?: string }): Promise<{
    data: User[];
    total: number;
    current_page: number;
    last_page: number;
  }> {
    return this.get('/users', { params });
  }

  async getUserById(id: string): Promise<User> {
    return this.get(`/users/${id}`);
  }

  async updateUser(id: string, data: UpdateUserRequest): Promise<User> {
    return this.patch(`/users/${id}`, data);
  }

  async deleteUser(id: string): Promise<void> {
    return this.delete(`/users/${id}`);
  }

  async uploadAvatar(file: File): Promise<{ url: string }> {
    const formData = new FormData();
    formData.append('avatar', file);
    
    return this.post('/users/avatar', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
  }
}

export const userService = new UserService();