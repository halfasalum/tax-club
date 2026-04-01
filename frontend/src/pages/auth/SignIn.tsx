// src/pages/auth/SignIn.tsx
import { useState } from 'react';
import { TextInput, PasswordInput, Button, Stack, Checkbox, Group, Alert } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconMail, IconLock, IconAlertCircle } from '@tabler/icons-react';
import { AuthLayout } from '../../layouts/AuthLayout';
import { useNavigate } from 'react-router-dom';

export const SignIn = () => {
  const navigate = useNavigate();
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const form = useForm({
    initialValues: {
      email: '',
      password: '',
      rememberMe: false,
    },
    validate: {
      email: (value) => (/^\S+@\S+$/.test(value) ? null : 'Invalid email'),
      password: (value) => (value.length >= 6 ? null : 'Password must be at least 6 characters'),
    },
  });

  const handleSubmit = async (values: typeof form.values) => {
    setLoading(true);
    setError(null);
    
    try {
      // Replace with your actual auth logic
      await new Promise(resolve => setTimeout(resolve, 1000));
      console.log('Login values:', values);
      
      // On success, redirect to dashboard
      navigate('/dashboard');
    } catch (err) {
      setError('Invalid email or password');
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout
      title="Welcome back"
      subtitle="Sign in to your account to continue"
      footerText="Don't have an account?"
      footerLink={{ text: 'Create account', to: '/auth/signup' }}
    >
      <form onSubmit={form.onSubmit(handleSubmit)}>
        <Stack>
          {error && (
            <Alert icon={<IconAlertCircle size={16} />} color="red" title="Authentication failed">
              {error}
            </Alert>
          )}

          <TextInput
            required
            label="Email"
            placeholder="hello@example.com"
            leftSection={<IconMail size={16} />}
            {...form.getInputProps('email')}
          />

          <PasswordInput
            required
            label="Password"
            placeholder="Your password"
            leftSection={<IconLock size={16} />}
            {...form.getInputProps('password')}
          />

          <Group justify="space-between">
            <Checkbox 
              label="Remember me" 
              {...form.getInputProps('rememberMe', { type: 'checkbox' })}
            />
            <Button 
              variant="subtle" 
              size="sm" 
              onClick={() => navigate('/auth/forgot-password')}
            >
              Forgot password?
            </Button>
          </Group>

          <Button type="submit" loading={loading} fullWidth mt="md">
            Sign In
          </Button>
        </Stack>
      </form>
    </AuthLayout>
  );
};