// src/api/services/institutionService.ts
import { BaseService } from '../baseService';

export interface Institution {
  institution_id: string;
  name: string;
  code?: string;
  type?: string;
}

class InstitutionService extends BaseService {
  async getInstitutions(): Promise<Institution[]> {
    return this.get<Institution[]>('/institutions');
  }

  async getInstitutionById(id: string): Promise<Institution> {
    return this.get<Institution>(`/institutions/${id}`);
  }
}

export const institutionService = new InstitutionService();