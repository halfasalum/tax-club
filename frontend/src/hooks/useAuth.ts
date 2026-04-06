// src/hooks/useAuth.ts
import { useState, useEffect, useCallback } from 'react';
import { authService } from '../api/services/authService';
import { institutionService, type Institution } from '../api/services/institutionService';
import { useNavigate } from 'react-router-dom';
import { notifications } from '@mantine/notifications';
import { type User, type RegisterRequest } from '../api/services/authService';

export const useAuth = () => {
  const navigate = useNavigate();
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [authenticated, setAuthenticated] = useState(false);
  const [permissions, setPermissions] = useState<string[]>([]);
  const [institutions, setInstitutions] = useState<Institution[]>([]);

  useEffect(() => {
    checkAuth();
    loadInstitutions();
  }, []);

  const loadInstitutions = async () => {
    try {
      const data = await institutionService.getInstitutions();
      setInstitutions(data);
    } catch (error) {
      console.error('Failed to load institutions:', error);
    }
  };

  const checkAuth = useCallback(async () => {
    setLoading(true);
    try {
      if (authService.isAuthenticated()) {
        const response = await authService.getCurrentUser();
        
        console.log('Check auth response:', response); // Debug log
        
        if (response.success && response.data) {
          setUser(response.data.user);
          setPermissions(response.data.permissions);
          setAuthenticated(true);
        } else {
          // Invalid response, clear auth
          localStorage.removeItem('auth_token');
          localStorage.removeItem('user');
          localStorage.removeItem('permissions');
          setAuthenticated(false);
        }
      } else {
        setUser(null);
        setAuthenticated(false);
        setPermissions([]);
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      setUser(null);
      setAuthenticated(false);
      setPermissions([]);
    } finally {
      setLoading(false);
    }
  }, []);

  const register = useCallback(
    async (data: {
      full_name: string;
      email: string;
      phone?: string;
      password: string;
      password_confirmation: string;
      user_type: string;
      institution_id?: string;
    }) => {
      try {
        const response = await authService.register(data);
        
        console.log('Register response:', response); // Debug log
        
        if (response.success && response.data) {
          setUser(response.data.user);
          setAuthenticated(true);
          if (response.data.permissions) {
            setPermissions(response.data.permissions);
          }
          
          notifications.show({
            title: 'Registration successful!',
            message: response.message || 'Welcome to TRA Tax Club!',
            color: 'green',
          });
          
          navigate('/dashboard');
          return { success: true };
        } else {
          return { success: false, error: response.message || 'Registration failed' };
        }
      } catch (error: any) {
        console.error('Register error:', error);
        const errorMessage = error.response?.data?.message || 'Registration failed';
        return { success: false, error: errorMessage };
      }
    },
    [navigate]
  );

  const login = useCallback(
    async (email: string, password: string) => {
      try {
        const response = await authService.login({ email, password });
        
        console.log('Login response in hook:', response); // Debug log
        
        if (response.success && response.data) {
          const { user, token, permissions } = response.data;
          
          console.log('Setting user state:', user); // Debug log
          
          setUser(user);
          setPermissions(permissions);
          setAuthenticated(true);
          
          notifications.show({
            title: 'Welcome back!',
            message: `Hello, ${user.full_name}`,
            color: 'green',
          });
          
          navigate('/dashboard');
          return { success: true };
        } else {
          return { success: false, error: response.message || 'Login failed' };
        }
      } catch (error: any) {
        console.error('Login error:', error);
        const errorMessage = error.response?.data?.message || 'Invalid email or password';
        return { success: false, error: errorMessage };
      }
    },
    [navigate]
  );

  const logout = useCallback(async () => {
    try {
      const response = await authService.logout();
      if (response.success) {
        notifications.show({
          title: 'Logged out',
          message: response.message || 'You have been successfully logged out',
          color: 'blue',
        });
      }
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      setUser(null);
      setAuthenticated(false);
      setPermissions([]);
      navigate('/auth/signin');
    }
  }, [navigate]);

  const changePassword = useCallback(
    async (current_password: string, password: string, password_confirmation: string) => {
      try {
        const response = await authService.changePassword({ 
          current_password, 
          password, 
          password_confirmation 
        });
        
        if (response.success) {
          notifications.show({
            title: 'Password changed',
            message: response.message || 'Please login again with your new password',
            color: 'green',
          });
          
          await logout();
          return { success: true };
        } else {
          return { success: false, error: response.message };
        }
      } catch (error: any) {
        const errorMessage = error.response?.data?.message || 'Failed to change password';
        return { success: false, error: errorMessage };
      }
    },
    [logout]
  );

  const hasPermission = useCallback(
    (permission: string): boolean => {
      return permissions.includes(permission);
    },
    [permissions]
  );

  const hasAnyPermission = useCallback(
    (permissionList: string[]): boolean => {
      return permissionList.some(permission => permissions.includes(permission));
    },
    [permissions]
  );

  const hasAllPermissions = useCallback(
    (permissionList: string[]): boolean => {
      return permissionList.every(permission => permissions.includes(permission));
    },
    [permissions]
  );

  return {
    user,
    loading,
    authenticated,
    permissions,
    institutions,
    register,
    login,
    logout,
    changePassword,
    checkAuth,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
  };
};