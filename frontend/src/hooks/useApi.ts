// src/hooks/useApi.ts
import { useState, useCallback } from 'react';
import { notifications } from '@mantine/notifications';

interface UseApiState<T> {
  data: T | null;
  loading: boolean;
  error: string | null;
}

interface UseApiOptions {
  showSuccessNotification?: boolean;
  successMessage?: string;
  showErrorNotification?: boolean;
  onSuccess?: (data: any) => void;
  onError?: (error: string) => void;
}

export function useApi<T = any, P = any>(
  apiFunction: (params?: P) => Promise<T>,
  options: UseApiOptions = {}
) {
  const [state, setState] = useState<UseApiState<T>>({
    data: null,
    loading: false,
    error: null,
  });

  const execute = useCallback(
    async (params?: P): Promise<T | null> => {
      setState(prev => ({ ...prev, loading: true, error: null }));
      
      try {
        const data = await apiFunction(params);
        
        setState({
          data,
          loading: false,
          error: null,
        });
        
        if (options.showSuccessNotification && options.successMessage) {
          notifications.show({
            title: 'Success',
            message: options.successMessage,
            color: 'green',
          });
        }
        
        options.onSuccess?.(data);
        return data;
      } catch (error: any) {
        const errorMessage = error.response?.data?.message || error.message || 'An error occurred';
        
        setState({
          data: null,
          loading: false,
          error: errorMessage,
        });
        
        if (options.showErrorNotification !== false) {
          notifications.show({
            title: 'Error',
            message: errorMessage,
            color: 'red',
          });
        }
        
        options.onError?.(errorMessage);
        return null;
      }
    },
    [apiFunction, options]
  );

  const reset = useCallback(() => {
    setState({
      data: null,
      loading: false,
      error: null,
    });
  }, []);

  return {
    ...state,
    execute,
    reset,
  };
}