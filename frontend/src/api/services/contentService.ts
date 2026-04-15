// src/api/services/contentService.ts
import { BaseService } from '../baseService';

export interface Content {
  content_id: string;
  title: string;
  content_type: 'video' | 'article' | 'infographic' | 'quiz';
  file_url: string | null;
  body_text: string | null;
  uploaded_by: string;
  uploader?: {
    user_id: string;
    full_name: string;
  };
  published_at: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  quiz?: any;
  progress?: {
    progress_id: string;
    is_completed: boolean;
    score: number | null;
    completed_at: string | null;
  };
}

export interface CreateContentRequest {
  title: string;
  content_type: 'video' | 'article' | 'infographic' | 'quiz';
  file_url?: string;
  body_text?: string;
  published_at?: string;
  is_active?: boolean;
}

export interface UpdateContentRequest {
  title?: string;
  content_type?: 'video' | 'article' | 'infographic' | 'quiz';
  file_url?: string | null;
  body_text?: string | null;
  published_at?: string;
  is_active?: boolean;
}

class ContentService extends BaseService {
  async getContent(params?: {
    page?: number;
    per_page?: number;
    content_type?: string;
    search?: string;
  }): Promise<{
    data: Content[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  }> {
    return this.get('/content', { params });
  }

  async getContentById(id: string): Promise<{ content: Content; progress: any }> {
    return this.get(`/content/${id}`);
  }

  async createContent(data: CreateContentRequest): Promise<Content> {
    return this.post('/content', data);
  }

  async updateContent(id: string, data: UpdateContentRequest): Promise<Content> {
    return this.put(`/content/${id}`, data);
  }

  async deleteContent(id: string): Promise<void> {
    return this.delete(`/content/${id}`);
  }

  async toggleContent(id: string): Promise<Content> {
    return this.patch(`/content/${id}/toggle`);
  }
}

export const contentService = new ContentService();