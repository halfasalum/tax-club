// src/hooks/useAuth.ts
import { useState, useEffect, useCallback } from 'react';
import { authService } from '../api/services/authService';
import { useNavigate } from 'react-router-dom';
import { notifications } from '@mantine/notifications';
import {type User } from '../api/services/authService';

export const useAuth = () => {
  const navigate = useNavigate();
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [authenticated, setAuthenticated] = useState(false);
  const [permissions, setPermissions] = useState<string[]>([]);

  // Only check local storage on initial load - no API call
  useEffect(() => {
    const token = authService.isAuthenticated();
    const storedUser = authService.getUser();
    const storedPermissions = authService.getPermissions();
    
    if (token && storedUser) {
      setUser(storedUser);
      setPermissions(storedPermissions);
      setAuthenticated(true);
    } else {
      // Clear invalid data
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      localStorage.removeItem('permissions');
      setAuthenticated(false);
    }
    
    setLoading(false);
  }, []);

  const login = useCallback(
    async (email: string, password: string) => {
      try {
        const response = await authService.login({ email, password });
        
        if (response.success && response.data) {
          const { user, permissions } = response.data;
          
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
        const errorMessage = error.response?.data?.message || 'Invalid email or password';
        return { success: false, error: errorMessage };
      }
    },
    [navigate]
  );

  const logout = useCallback(async () => {
    try {
      await authService.logout();
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      setUser(null);
      setAuthenticated(false);
      setPermissions([]);
      navigate('/auth/signin');
      
      notifications.show({
        title: 'Logged out',
        message: 'You have been successfully logged out',
        color: 'blue',
      });
    }
  }, [navigate]);

  // Only call this when user updates their profile or permissions
  const refreshUser = useCallback(async () => {
    try {
      await authService.refreshUserData();
      setUser(authService.getUser());
      setPermissions(authService.getPermissions());
      
      notifications.show({
        title: 'Profile Updated',
        message: 'Your information has been refreshed',
        color: 'green',
      });
    } catch (error) {
      console.error('Failed to refresh user data:', error);
    }
  }, []);

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
    login,
    logout,
    refreshUser,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
  };
};