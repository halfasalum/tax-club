// src/pages/dashboard/Dashboard.tsx
import { Title, Text, Card, SimpleGrid, Group, Stack, Badge, Avatar } from '@mantine/core';
import { 
  IconUsers, 
  IconFileText, 
  IconChartBar, 
  IconSettings,
  IconShield,
  IconUserCheck,
  IconCalendar,
  IconBook
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';

export const Dashboard = () => {
  const { user, permissions, hasPermission } = useAuth();

  // Stats based on permissions
  const getStats = () => {
    const stats = [];
    
    if (hasPermission('view_users')) {
      stats.push({ title: 'Total Users', value: '1,234', icon: IconUsers, color: 'blue' });
    }
    if (hasPermission('view_modules')) {
      stats.push({ title: 'Active Modules', value: '12', icon: IconBook, color: 'green' });
    }
    if (hasPermission('view_event_attendees')) {
      stats.push({ title: 'Upcoming Events', value: '5', icon: IconCalendar, color: 'violet' });
    }
    
    // Default stats if no specific permissions
    if (stats.length === 0) {
      stats.push(
        { title: 'Welcome', value: 'TRA Tax Club', icon: IconUserCheck, color: 'blue' },
        { title: 'Member Since', value: user?.created_at ? new Date(user.created_at).getFullYear().toString() : '2024', icon: IconChartBar, color: 'green' }
      );
    }
    
    return stats;
  };

  return (
    <Stack gap="xl">
      {/* Welcome Section */}
      <Group justify="space-between" align="center">
        <div>
          <Title order={1}>Dashboard</Title>
          <Text c="dimmed" size="sm">
            Welcome back, {user?.full_name}!
          </Text>
        </div>
        <Badge size="lg" variant="gradient" gradient={{ from: 'blue', to: 'indigo' }}>
          {user?.membership_id}
        </Badge>
      </Group>

      {/* User Info Card */}
      <Card shadow="sm" padding="lg" radius="md" withBorder>
        <Group wrap="nowrap">
          <Avatar size="xl" radius="xl" color="indigo">
            {user?.full_name?.charAt(0)}
          </Avatar>
          <div style={{ flex: 1 }}>
            <Group justify="space-between">
              <div>
                <Text size="lg" fw={700}>{user?.full_name}</Text>
                <Text size="sm" c="dimmed">{user?.email}</Text>
              </div>
              <Badge color={user?.is_verified ? 'green' : 'yellow'}>
                {user?.is_verified ? 'Verified' : 'Pending Verification'}
              </Badge>
            </Group>
            <Group mt="sm">
              <Text size="xs" c="dimmed">Member ID: {user?.membership_id}</Text>
              <Text size="xs" c="dimmed">|</Text>
              <Text size="xs" c="dimmed">Type: {user?.user_type?.toUpperCase()}</Text>
            </Group>
          </div>
        </Group>
      </Card>

      {/* Stats Grid */}
      <SimpleGrid cols={{ base: 1, sm: 2, md: 4 }} spacing="md">
        {getStats().map((stat) => (
          <Card key={stat.title} shadow="sm" padding="lg" radius="md" withBorder>
            <Group justify="space-between" mb="xs">
              <Text size="sm" c="dimmed" fw={500}>{stat.title}</Text>
              <stat.icon size={24} color={`var(--mantine-color-${stat.color}-6)`} />
            </Group>
            <Text size="xl" fw={700}>{stat.value}</Text>
          </Card>
        ))}
      </SimpleGrid>

      {/* Permissions Section (Admin only) */}
      {permissions.length > 0 && (
        <Card shadow="sm" padding="lg" radius="md" withBorder>
          <Group mb="md">
            <IconShield size={20} />
            <Title order={3}>Your Access Rights</Title>
          </Group>
          <Group gap="xs">
            {permissions.slice(0, 10).map((permission) => (
              <Badge key={permission} variant="light" color="blue" size="sm">
                {permission.replace(/_/g, ' ')}
              </Badge>
            ))}
            {permissions.length > 10 && (
              <Badge variant="light" color="gray">
                +{permissions.length - 10} more
              </Badge>
            )}
          </Group>
        </Card>
      )}
    </Stack>
  );
};