// src/api/baseService.ts
import axiosInstance from './axiosInstance';
import {type AxiosRequestConfig } from 'axios';

export class BaseService {
  protected async get<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response = await axiosInstance.get(url, config);
    // Return the entire response data (which already has success, message, data)
    return response.data;
  }

  protected async post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await axiosInstance.post(url, data, config);
    return response.data;
  }

  protected async put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await axiosInstance.put(url, data, config);
    return response.data;
  }

  protected async patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await axiosInstance.patch(url, data, config);
    return response.data;
  }

  protected async delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response = await axiosInstance.delete(url, config);
    return response.data;
  }
}