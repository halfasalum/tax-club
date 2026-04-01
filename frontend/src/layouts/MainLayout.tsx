// src/layouts/MainLayout.tsx
import type { ReactNode } from 'react';
import { Outlet } from 'react-router-dom';
import { 
  AppShell, 
  Burger, 
  NavLink, 
  Group, 
  Avatar, 
  Menu, 
  Text, 
  UnstyledButton,
  useMantineTheme,
  useMantineColorScheme,
  Switch
} from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { 
  IconHome, 
  IconUser, 
  IconSettings, 
  IconLogout, 
  IconMoon, 
  IconSun,
  IconDashboard
} from '@tabler/icons-react';
import { Logo } from '../components/common/Logo';

interface MainLayoutProps {
  children?: ReactNode;
  activePath?: string;
}

export const MainLayout = ({ children, activePath = '/' }: MainLayoutProps) => {
  const [opened, { toggle }] = useDisclosure();
  const theme = useMantineTheme();
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  const isDark = colorScheme === 'dark';

  const navItems = [
    { label: 'Dashboard', icon: IconDashboard, to: '/dashboard' },
    { label: 'Profile', icon: IconUser, to: '/profile' },
    { label: 'Settings', icon: IconSettings, to: '/settings' },
  ];

  return (
    <AppShell
      header={{ height: 60 }}
      navbar={{
        width: 300,
        breakpoint: 'sm',
        collapsed: { mobile: !opened },
      }}
      padding="md"
      style={{
        background: isDark ? theme.colors.dark[8] : theme.colors.gray[0],
      }}
    >
      {/* Header */}
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between">
          <Group>
            <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
            <Logo size="sm" />
          </Group>

          <Group>
            {/* Theme Toggle */}
            <Switch
              checked={colorScheme === 'dark'}
              onChange={() => toggleColorScheme()}
              size="lg"
              onLabel={<IconSun size={16} />}
              offLabel={<IconMoon size={16} />}
            />

            {/* User Menu */}
            <Menu shadow="md" width={200}>
              <Menu.Target>
                <UnstyledButton>
                  <Group gap={7}>
                    <Avatar 
                      src={null} 
                      alt="User" 
                      radius="xl" 
                      color={isDark ? 'blue' : 'indigo'}
                    >
                      JD
                    </Avatar>
                    <Text size="sm" fw={500} visibleFrom="sm">
                      John Doe
                    </Text>
                  </Group>
                </UnstyledButton>
              </Menu.Target>

              <Menu.Dropdown>
                <Menu.Label>Account</Menu.Label>
                <Menu.Item leftSection={<IconUser size={14} />}>
                  Profile
                </Menu.Item>
                <Menu.Item leftSection={<IconSettings size={14} />}>
                  Settings
                </Menu.Item>
                <Menu.Divider />
                <Menu.Item 
                  leftSection={<IconLogout size={14} />} 
                  color="red"
                >
                  Logout
                </Menu.Item>
              </Menu.Dropdown>
            </Menu>
          </Group>
        </Group>
      </AppShell.Header>

      {/* Sidebar Navigation */}
      <AppShell.Navbar p="md">
        {navItems.map((item) => (
          <NavLink
            key={item.to}
            label={item.label}
            leftSection={<item.icon size={20} />}
            active={activePath === item.to}
            href={item.to}
            variant="subtle"
            style={{ marginBottom: 4 }}
          />
        ))}
      </AppShell.Navbar>

      {/* Main Content */}
      <AppShell.Main>
        {children || <Outlet />}
      </AppShell.Main>
    </AppShell>
  );
};