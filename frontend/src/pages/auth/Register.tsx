// src/pages/auth/Register.tsx
import { useEffect, useState } from 'react';
import { TextInput, PasswordInput, Select, Button, Stack, Alert, Group } from '@mantine/core';
import { useForm } from '@mantine/form';
import { IconUser, IconMail, IconLock, IconPhone, IconAlertCircle, IconSchool } from '@tabler/icons-react';
import { AuthLayout } from '../../layouts/AuthLayout';
import { useAuth } from '../../hooks/useAuth';
import { institutionService, type Institution } from '../../api/services/institutionService';

const USER_TYPES = [
  { value: 'secondary', label: 'Secondary School Student' },
  { value: 'college', label: 'College Student' },
  { value: 'university', label: 'University Student' },
  { value: 'alumni', label: 'Alumni' },
];

export const Register = () => {
  const { register, loading: authLoading } = useAuth();
  const [error, setError] = useState<string | null>(null);
  const [institutions, setInstitutions] = useState<Institution[]>([]);

  useEffect(() => {
    institutionService
      .getInstitutions({ per_page: 200 })
      .then((res) => setInstitutions(res.data || []))
      .catch(() => setInstitutions([]));
  }, []);

  const form = useForm({
    initialValues: {
      full_name: '',
      email: '',
      phone: '',
      user_type: '',
      institution_id: '',
      password: '',
      password_confirmation: '',
    },
    validate: {
      full_name: (value) => (value.trim().length >= 2 ? null : 'Enter your full name'),
      email: (value) => (/^\S+@\S+\.\S+$/.test(value) ? null : 'Invalid email format'),
      user_type: (value) => (value ? null : 'Select who you are'),
      password: (value) => (value.length >= 8 ? null : 'Password must be at least 8 characters'),
      password_confirmation: (value, values) => (value === values.password ? null : 'Passwords do not match'),
    },
  });

  const handleSubmit = async (values: typeof form.values) => {
    setError(null);

    const result = await register({
      full_name: values.full_name,
      email: values.email,
      phone: values.phone || undefined,
      password: values.password,
      password_confirmation: values.password_confirmation,
      user_type: values.user_type as any,
      institution_id: values.institution_id || null,
    });

    if (!result.success) {
      setError(result.error || 'Registration failed. Please try again.');
      if ((result as any).errors) {
        const fieldErrors: Record<string, string> = {};
        Object.entries((result as any).errors as Record<string, string[]>).forEach(([field, messages]) => {
          fieldErrors[field] = messages[0];
        });
        form.setErrors(fieldErrors);
      }
    }
  };

  return (
    <AuthLayout
      title="Join the Tax Club"
      subtitle="Create your free account and start earning points"
      footerText="Already have an account?"
      footerLink={{ text: 'Sign in', to: '/auth/signin' }}
    >
      <form onSubmit={form.onSubmit(handleSubmit)}>
        <Stack>
          {error && (
            <Alert
              icon={<IconAlertCircle size={16} />}
              color="red"
              title="Registration failed"
              withCloseButton
              onClose={() => setError(null)}
            >
              {error}
            </Alert>
          )}

          <TextInput
            required
            label="Full Name"
            placeholder="Your full name"
            leftSection={<IconUser size={16} />}
            {...form.getInputProps('full_name')}
            disabled={authLoading}
          />

          <TextInput
            required
            label="Email Address"
            placeholder="you@example.com"
            leftSection={<IconMail size={16} />}
            {...form.getInputProps('email')}
            disabled={authLoading}
          />

          <TextInput
            label="Phone Number"
            placeholder="Optional"
            leftSection={<IconPhone size={16} />}
            {...form.getInputProps('phone')}
            disabled={authLoading}
          />

          <Select
            required
            label="I am a"
            placeholder="Select your status"
            leftSection={<IconSchool size={16} />}
            data={USER_TYPES}
            {...form.getInputProps('user_type')}
            disabled={authLoading}
          />

          <Select
            label="Institution"
            placeholder="Optional — select your school/college/university"
            data={institutions.map((i) => ({ value: i.institution_id, label: i.name }))}
            searchable
            clearable
            {...form.getInputProps('institution_id')}
            disabled={authLoading}
          />

          <Group grow>
            <PasswordInput
              required
              label="Password"
              placeholder="At least 8 characters"
              leftSection={<IconLock size={16} />}
              {...form.getInputProps('password')}
              disabled={authLoading}
            />
            <PasswordInput
              required
              label="Confirm Password"
              placeholder="Repeat password"
              leftSection={<IconLock size={16} />}
              {...form.getInputProps('password_confirmation')}
              disabled={authLoading}
            />
          </Group>

          <Button type="submit" loading={authLoading} fullWidth mt="md" size="md">
            Create Account
          </Button>
        </Stack>
      </form>
    </AuthLayout>
  );
};
