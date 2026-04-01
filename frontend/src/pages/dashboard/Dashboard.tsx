// src/pages/dashboard/Dashboard.tsx
import { Title, Text, Card, SimpleGrid, Group, RingProgress, Stack } from '@mantine/core';
import { IconUsers, IconFileText, IconChartBar, IconSettings } from '@tabler/icons-react';

export const Dashboard = () => {
  const stats = [
    { title: 'Users', value: '1,234', icon: IconUsers, color: 'blue' },
    { title: 'Posts', value: '456', icon: IconFileText, color: 'green' },
    { title: 'Analytics', value: '$12,345', icon: IconChartBar, color: 'orange' },
    { title: 'Settings', value: 'Active', icon: IconSettings, color: 'violet' },
  ];

  return (
    <Stack gap="xl">
      <div>
        <Title order={1} mb="xs">Dashboard</Title>
        <Text c="dimmed">Welcome back, John! Here's what's happening with your app today.</Text>
      </div>
      
      <SimpleGrid cols={{ base: 1, sm: 2, md: 4 }} spacing="md">
        {stats.map((stat) => (
          <Card key={stat.title} shadow="sm" padding="lg" radius="md" withBorder>
            <Group justify="space-between" mb="xs">
              <Text size="sm" c="dimmed" fw={500}>{stat.title}</Text>
              <stat.icon size={24} color={`var(--mantine-color-${stat.color}-6)`} />
            </Group>
            <Text size="xl" fw={700}>{stat.value}</Text>
          </Card>
        ))}
      </SimpleGrid>
      
      <Card shadow="sm" padding="lg" radius="md" withBorder>
        <Title order={2} mb="lg">Recent Activity</Title>
        <Text c="dimmed">Your recent activity will appear here...</Text>
      </Card>
    </Stack>
  );
};