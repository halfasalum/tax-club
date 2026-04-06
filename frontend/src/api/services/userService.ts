// src/api/services/userService.ts
import { BaseService } from '../baseService';

export interface User {
  id: string;
  email: string;
  name: string;
  role: 'user' | 'admin';
  avatar?: string;
  createdAt: string;
  updatedAt: string;
}

export interface UpdateUserRequest {
  name?: string;
  email?: string;
  avatar?: string;
}

class UserService extends BaseService {
  async getUsers(params?: { page?: number; limit?: number }): Promise<{
    users: User[];
    total: number;
    page: number;
    totalPages: number;
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