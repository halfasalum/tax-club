// src/pages/auth/ForgotPassword.tsx
import { useState } from 'react';
import { TextInput, Button, Stack, Alert, Text } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconMail, IconAlertCircle, IconCheck } from '@tabler/icons-react';
import { AuthLayout } from '../../layouts/AuthLayout';
import { useNavigate } from 'react-router-dom';

export const ForgotPassword = () => {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const form = useForm({
    initialValues: {
      email: '',
    },
    validate: {
      email: (value) => (/^\S+@\S+$/.test(value) ? null : 'Invalid email'),
    },
  });

  const handleSubmit = async (values: typeof form.values) => {
    setLoading(true);
    setError(null);
    
    try {
      // Replace with your actual password reset logic
      await new Promise(resolve => setTimeout(resolve, 1000));
      console.log('Password reset requested for:', values.email);
      setSubmitted(true);
    } catch (err) {
      setError('Failed to send reset email. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  if (submitted) {
    return (
      <AuthLayout
        title="Check your email"
        subtitle="We've sent you a password reset link"
        footerText="Remember your password?"
        footerLink={{ text: 'Sign in', to: '/auth/signin' }}
      >
        <Alert icon={<IconCheck size={16} />} color="green" title="Email sent!">
          <Text size="sm">
            We've sent a password reset link to <strong>{form.values.email}</strong>. 
            Please check your inbox and follow the instructions.
          </Text>
        </Alert>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout
      title="Forgot password?"
      subtitle="Enter your email address and we'll send you a link to reset your password"
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

          <TextInput
            required
            label="Email"
            placeholder="hello@example.com"
            leftSection={<IconMail size={16} />}
            {...form.getInputProps('email')}
          />

          <Button type="submit" loading={loading} fullWidth mt="md">
            Send Reset Link
          </Button>
        </Stack>
      </form>
    </AuthLayout>
  );
};