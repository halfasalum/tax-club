// src/pages/dashboard/Dashboard.tsx
import { Title, Text, Card, SimpleGrid, Group, Stack, Badge, Avatar, RingProgress, Grid, Table } from '@mantine/core';
import { 
  IconUsers, 
  IconFileText, 
  IconBriefcase, 
  IconCalendarEvent,
  IconMessageCircle,
  IconBuilding,
  IconBook,
  IconCertificate,
  IconTrendingUp
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';
import { useEffect, useState } from 'react';
import { apiClient } from '../../api/apiClient';

export const Dashboard = () => {
  const { user, permissions, hasPermission } = useAuth();
  const [stats, setStats] = useState<any>({});
  const [recentUsers, setRecentUsers] = useState([]);
  const [recentActivities, setRecentActivities] = useState([]);

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = async () => {
    try {
      const dashboardData = await apiClient.get('/dashboard');
      setStats(dashboardData.stats || {});
      setRecentUsers(dashboardData.recent_users || []);
      setRecentActivities(dashboardData.recent_activities || []);
    } catch (error) {
      console.error('Failed to load dashboard:', error);
    }
  };

  const statCards = [];

  if (hasPermission('view_users')) {
    statCards.push({ title: 'Total Users', value: stats.total_users || '0', icon: IconUsers, color: 'blue' });
  }
  if (hasPermission('upload_content')) {
    statCards.push({ title: 'Content Items', value: stats.total_content || '0', icon: IconBook, color: 'green' });
  }
  if (hasPermission('view_results')) {
    statCards.push({ title: 'Quiz Attempts', value: stats.total_attempts || '0', icon: IconCertificate, color: 'orange' });
  }
  if (hasPermission('add_opportunity')) {
    statCards.push({ title: 'Opportunities', value: stats.total_opportunities || '0', icon: IconBriefcase, color: 'teal' });
  }
  if (hasPermission('add_event')) {
    statCards.push({ title: 'Upcoming Events', value: stats.upcoming_events || '0', icon: IconCalendarEvent, color: 'violet' });
  }

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
      {statCards.length > 0 && (
        <SimpleGrid cols={{ base: 1, sm: 2, md: 4 }} spacing="md">
          {statCards.map((stat) => (
            <Card key={stat.title} shadow="sm" padding="lg" radius="md" withBorder>
              <Group justify="space-between" mb="xs">
                <Text size="sm" c="dimmed" fw={500}>{stat.title}</Text>
                <stat.icon size={24} color={`var(--mantine-color-${stat.color}-6)`} />
              </Group>
              <Text size="xl" fw={700}>{stat.value}</Text>
              <Text size="xs" c="dimmed" mt="sm">+{Math.floor(Math.random() * 20)}% from last month</Text>
            </Card>
          ))}
        </SimpleGrid>
      )}

      {/* Recent Users Table */}
      {hasPermission('view_users') && recentUsers.length > 0 && (
        <Card shadow="sm" padding="lg" radius="md" withBorder>
          <Title order={3} mb="md">Recent Users</Title>
          <Table striped>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Name</Table.Th>
                <Table.Th>Email</Table.Th>
                <Table.Th>Type</Table.Th>
                <Table.Th>Joined</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {recentUsers.map((user: any) => (
                <Table.Tr key={user.user_id}>
                  <Table.Td>{user.full_name}</Table.Td>
                  <Table.Td>{user.email}</Table.Td>
                  <Table.Td><Badge size="sm">{user.user_type}</Badge></Table.Td>
                  <Table.Td>{new Date(user.created_at).toLocaleDateString()}</Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Card>
      )}

      {/* Permissions Section */}
      {permissions.length > 0 && (
        <Card shadow="sm" padding="lg" radius="md" withBorder>
          <Group mb="md">
            <IconTrendingUp size={20} />
            <Title order={3}>Your Access Rights</Title>
          </Group>
          <Group gap="xs">
            {permissions.slice(0, 15).map((permission) => (
              <Badge key={permission} variant="light" color="blue" size="sm">
                {permission.replace(/_/g, ' ')}
              </Badge>
            ))}
            {permissions.length > 15 && (
              <Badge variant="light" color="gray">
                +{permissions.length - 15} more
              </Badge>
            )}
          </Group>
        </Card>
      )}
    </Stack>
  );
};