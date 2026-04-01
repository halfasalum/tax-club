// src/pages/auth/ResetPassword.tsx
import { useState } from 'react';
import { PasswordInput, Button, Stack, Alert, Text } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconLock, IconAlertCircle, IconCheck } from '@tabler/icons-react';
import { AuthLayout } from '../../layouts/AuthLayout';
import { useNavigate, useParams } from 'react-router-dom';

export const ResetPassword = () => {
  const navigate = useNavigate();
  const { token } = useParams(); // Assuming token is in URL: /auth/reset-password/:token
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const form = useForm({
    initialValues: {
      password: '',
      confirmPassword: '',
    },
    validate: {
      password: (value) => (value.length >= 6 ? null : 'Password must be at least 6 characters'),
      confirmPassword: (value, values) =>
        value === values.password ? null : 'Passwords do not match',
    },
  });

  const handleSubmit = async (values: typeof form.values) => {
    setLoading(true);
    setError(null);
    
    try {
      // Replace with your actual password reset logic
      await new Promise(resolve => setTimeout(resolve, 1000));
      console.log('Password reset with token:', token, values.password);
      setSubmitted(true);
      
      // Redirect to sign in after 3 seconds
      setTimeout(() => {
        navigate('/auth/signin');
      }, 3000);
    } catch (err) {
      setError('Failed to reset password. The link may have expired.');
    } finally {
      setLoading(false);
    }
  };

  if (submitted) {
    return (
      <AuthLayout
        title="Password reset successful!"
        subtitle="Your password has been changed"
        footerText="Ready to sign in?"
        footerLink={{ text: 'Sign in now', to: '/auth/signin' }}
      >
        <Alert icon={<IconCheck size={16} />} color="green" title="Success!">
          <Text size="sm">
            Your password has been successfully reset. You'll be redirected to the sign in page.
          </Text>
        </Alert>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout
      title="Reset your password"
      subtitle="Please enter your new password"
      footerText="Remember your password?"
      footerLink={{ text: 'Sign in', to: '/auth/signin' }}
    >
      <form onSubmit={form.onSubmit(handleSubmit)}>
        <Stack>
          {error && (
            <Alert icon={<IconAlertCircle size={16} />} color="red" title="Error">
              {error}
            </Alert>
          )}

          <PasswordInput
            required
            label="New password"
            placeholder="Enter new password"
            leftSection={<IconLock size={16} />}
            {...form.getInputProps('password')}
          />

          <PasswordInput
            required
            label="Confirm password"
            placeholder="Confirm your new password"
            leftSection={<IconLock size={16} />}
            {...form.getInputProps('confirmPassword')}
          />

          <Button type="submit" loading={loading} fullWidth mt="md">
            Reset Password
          </Button>
        </Stack>
      </form>
    </AuthLayout>
  );
};