// src/layouts/MainLayout.tsx
import { AppShell, Burger, Group, Avatar, Menu, Text, UnstyledButton, useMantineColorScheme, Switch, Box } from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import { IconMoon, IconSun, IconLogout, IconUser, IconSettings } from '@tabler/icons-react';
import { Logo } from '../components/common/Logo';
import { SidebarNav } from '../components/navigation/SidebarNav';
import { useAuth } from '../hooks/useAuth';
import { Outlet, useNavigate } from 'react-router-dom';

export const MainLayout = () => {
  const [opened, { toggle }] = useDisclosure();
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const isDark = colorScheme === 'dark';

  const handleLogout = async () => {
    await logout();
    navigate('/auth/signin');
  };

  return (
    <AppShell
      header={{ height: 60 }}
      navbar={{
        width: 280,
        breakpoint: 'sm',
        collapsed: { mobile: !opened },
      }}
      padding="md"
    >
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between">
          <Group>
            <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
            <Logo />
          </Group>

          <Group>
            <Switch
              checked={colorScheme === 'dark'}
              onChange={() => toggleColorScheme()}
              size="lg"
              onLabel={<IconSun size={16} />}
              offLabel={<IconMoon size={16} />}
            />

            <Menu shadow="md" width={200}>
              <Menu.Target>
                <UnstyledButton>
                  <Group gap={7}>
                    <Avatar radius="xl" color="indigo">
                      {user?.full_name?.charAt(0)}
                    </Avatar>
                    <Text size="sm" fw={500} visibleFrom="sm">
                      {user?.full_name?.split(' ')[0]}
                    </Text>
                  </Group>
                </UnstyledButton>
              </Menu.Target>

              <Menu.Dropdown>
                <Menu.Label>Account</Menu.Label>
                <Menu.Item 
                  leftSection={<IconUser size={14} />}
                  onClick={() => navigate('/profile')}
                >
                  Profile
                </Menu.Item>
                <Menu.Item 
                  leftSection={<IconSettings size={14} />}
                  onClick={() => navigate('/settings')}
                >
                  Settings
                </Menu.Item>
                <Menu.Divider />
                <Menu.Item 
                  leftSection={<IconLogout size={14} />} 
                  color="red"
                  onClick={handleLogout}
                >
                  Logout
                </Menu.Item>
              </Menu.Dropdown>
            </Menu>
          </Group>
        </Group>
      </AppShell.Header>

      <AppShell.Navbar p="md">
        <SidebarNav />
      </AppShell.Navbar>

      <AppShell.Main>
        <Outlet />
      </AppShell.Main>
    </AppShell>
  );
};