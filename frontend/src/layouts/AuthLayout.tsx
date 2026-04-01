// src/layouts/AuthLayout.tsx
import type { ReactNode } from 'react';
import { 
  Container, 
  Paper, 
  Title, 
  Text, 
  Stack, 
  Box, 
  Anchor,
  useMantineTheme,
  useMantineColorScheme
} from '@mantine/core';
import { Logo } from '../components/common/Logo';

interface AuthLayoutProps {
  children: ReactNode;
  title: string;
  subtitle?: string;
  footerText?: string;
  footerLink?: {
    text: string;
    to: string;
  };
}

export const AuthLayout = ({ 
  children, 
  title, 
  subtitle, 
  footerText, 
  footerLink 
}: AuthLayoutProps) => {
  const theme = useMantineTheme();
  const { colorScheme } = useMantineColorScheme();
  const isDark = colorScheme === 'dark';

  return (
    <Box 
      style={{
        minHeight: '100vh',
        background: isDark 
          ? `linear-gradient(135deg, ${theme.colors.dark[8]} 0%, ${theme.colors.dark[9]} 100%)`
          : `linear-gradient(135deg, ${theme.colors.gray[1]} 0%, ${theme.colors.gray[2]} 100%)`,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      <Container size={420} my={40}>
        {/* Logo */}
        <Box style={{ textAlign: 'center', marginBottom: theme.spacing.xl }}>
          <Logo size="lg" />
        </Box>

        {/* Auth Card */}
        <Paper 
          radius="lg" 
          p="xl" 
          shadow="xl"
          style={{
            background: isDark ? theme.colors.dark[7] : theme.white,
            border: `1px solid ${isDark ? theme.colors.dark[5] : theme.colors.gray[3]}`,
          }}
        >
          <Stack gap="xs" mb="lg">
            <Title 
              order={2} 
              size="h1" 
              ta="center" 
              style={{ fontWeight: 600 }}
            >
              {title}
            </Title>
            {subtitle && (
              <Text 
                size="sm" 
                ta="center" 
                c="dimmed"
              >
                {subtitle}
              </Text>
            )}
          </Stack>

          {children}

          {(footerText || footerLink) && (
            <Box mt="xl" ta="center">
              <Text size="sm" c="dimmed" component="span">
                {footerText}{' '}
              </Text>
              {footerLink && (
                <Anchor 
                  href={footerLink.to} 
                  size="sm" 
                  style={{ fontWeight: 500 }}
                >
                  {footerLink.text}
                </Anchor>
              )}
            </Box>
          )}
        </Paper>
      </Container>
    </Box>
  );
};