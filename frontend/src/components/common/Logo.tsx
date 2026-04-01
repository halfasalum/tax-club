// src/components/common/Logo.tsx
import { Group, Text, useMantineTheme, useMantineColorScheme } from '@mantine/core';
import { IconBrandReact } from '@tabler/icons-react';

interface LogoProps {
  size?: 'sm' | 'md' | 'lg';
}

export const Logo = ({ size = 'md' }: LogoProps) => {
  const theme = useMantineTheme();
  const { colorScheme } = useMantineColorScheme();
  const isDark = colorScheme === 'dark';
  
  const sizes = {
    sm: { icon: 24, text: 'sm' },
    md: { icon: 32, text: 'md' },
    lg: { icon: 48, text: 'xl' },
  };
  
  return (
    <Group gap="xs" style={{ cursor: 'pointer' }}>
      <IconBrandReact 
        size={sizes[size].icon} 
        color={isDark ? theme.colors.blue[4] : theme.colors.indigo[6]}
      />
      <Text 
        size={sizes[size].text} 
        fw={700} 
        variant="gradient"
        gradient={{ from: 'blue', to: 'indigo', deg: 45 }}
      >
        MyApp
      </Text>
    </Group>
  );
};