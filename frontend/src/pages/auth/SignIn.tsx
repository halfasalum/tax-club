// src/pages/auth/SignIn.tsx
import { useState } from 'react';
import { TextInput, PasswordInput, Button, Stack, Checkbox, Group, Alert } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconMail, IconLock, IconAlertCircle } from '@tabler/icons-react';
import { AuthLayout } from '../../layouts/AuthLayout';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

export const SignIn = () => {
  const { login, loading: authLoading } = useAuth();
  const [error, setError] = useState<string | null>(null);

  const form = useForm({
    initialValues: {
      email: '',
      password: '',
      rememberMe: false,
    },
    validate: {
      email: (value) => (/^\S+@\S+$/.test(value) ? null : 'Invalid email format'),
      password: (value) => (value.length >= 6 ? null : 'Password must be at least 6 characters'),
    },
  });

  const handleSubmit = async (values: typeof form.values) => {
    setError(null);
    
    console.log('Attempting login with:', values.email); // Debug log
    
    const result = await login(values.email, values.password);
    
    console.log('Login result:', result); // Debug log
    
    if (!result.success) {
      setError(result.error || 'Login failed. Please try again.');
    }
  };

  return (
    <AuthLayout
      title="Welcome back"
      subtitle="Sign in to your TRA Tax Club account"
      footerText="Don't have an account?"
      footerLink={{ text: 'Create account', to: '/auth/register' }}
    >
      <form onSubmit={form.onSubmit(handleSubmit)}>
        <Stack>
          {error && (
            <Alert 
              icon={<IconAlertCircle size={16} />} 
              color="red" 
              title="Authentication failed"
              withCloseButton
              onClose={() => setError(null)}
            >
              {error}
            </Alert>
          )}

          <TextInput
            required
            label="Email Address"
            placeholder="superadmin@taxclub.go.tz"
            leftSection={<IconMail size={16} />}
            {...form.getInputProps('email')}
            disabled={authLoading}
          />

          <PasswordInput
            required
            label="Password"
            placeholder="Your password"
            leftSection={<IconLock size={16} />}
            {...form.getInputProps('password')}
            disabled={authLoading}
          />

          <Group justify="space-between">
            <Checkbox 
              label="Remember me" 
              {...form.getInputProps('rememberMe', { type: 'checkbox' })}
              disabled={authLoading}
            />
            <Button 
              variant="subtle" 
              size="sm" 
              component={Link}
              to="/auth/forgot-password"
              disabled={authLoading}
            >
              Forgot password?
            </Button>
          </Group>

          <Button 
            type="submit" 
            loading={authLoading} 
            fullWidth 
            mt="md"
            size="md"
          >
            Sign In
          </Button>
        </Stack>
      </form>
    </AuthLayout>
  );
};