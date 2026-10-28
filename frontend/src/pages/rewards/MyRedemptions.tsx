// src/pages/rewards/MyRedemptions.tsx
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Stack,
  Title,
  Text,
  Table,
  Badge,
  Card,
  Group,
  Button,
  LoadingOverlay,
  Pagination,
  Alert,
} from '@mantine/core';
import { IconArrowLeft } from '@tabler/icons-react';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';

interface Redemption {
  redemption_id: string;
  points_spent: number;
  status: 'pending' | 'approved' | 'fulfilled' | 'rejected';
  notes: string | null;
  created_at: string;
  reward: { reward_id: string; title: string; type: string; image_url: string | null } | null;
}

const statusColor = (status: string) => {
  switch (status) {
    case 'fulfilled': return 'green';
    case 'approved': return 'blue';
    case 'rejected': return 'red';
    default: return 'yellow';
  }
};

export const MyRedemptions = () => {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);

  const { data, loading, execute: fetchRedemptions } = useApi(
    () => apiClient.get('/rewards/my-redemptions', { params: { page, per_page: 15 } }),
    { showErrorNotification: true }
  );

  useEffect(() => {
    fetchRedemptions();
  }, [page]);

  const redemptions: Redemption[] = data?.data || [];
  const totalPages = data?.meta?.last_page || 1;

  return (
    <Stack gap="lg">
      <Group>
        <Button variant="subtle" leftSection={<IconArrowLeft size={16} />} onClick={() => navigate('/rewards')}>
          Back to Rewards
        </Button>
      </Group>

      <div>
        <Title order={2}>My Redemptions</Title>
        <Text c="dimmed" size="sm">Track the status of your reward requests</Text>
      </div>

      <Card withBorder padding="lg" radius="md" style={{ position: 'relative', minHeight: 200 }}>
        <LoadingOverlay visible={loading} />

        {!loading && redemptions.length === 0 ? (
          <Alert color="traGold">You haven't redeemed any rewards yet.</Alert>
        ) : (
          <>
            <Table striped highlightOnHover>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Reward</Table.Th>
                  <Table.Th>Points</Table.Th>
                  <Table.Th>Status</Table.Th>
                  <Table.Th>Requested</Table.Th>
                  <Table.Th>Notes</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {redemptions.map((r) => (
                  <Table.Tr key={r.redemption_id}>
                    <Table.Td fw={500}>{r.reward?.title || 'Reward removed'}</Table.Td>
                    <Table.Td>{r.points_spent}</Table.Td>
                    <Table.Td>
                      <Badge color={statusColor(r.status)} variant="light" tt="capitalize">{r.status}</Badge>
                    </Table.Td>
                    <Table.Td>{new Date(r.created_at).toLocaleDateString()}</Table.Td>
                    <Table.Td>
                      <Text size="xs" c="dimmed">{r.notes || '—'}</Text>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>

            {totalPages > 1 && (
              <Group justify="center" mt="xl">
                <Pagination total={totalPages} value={page} onChange={setPage} />
              </Group>
            )}
          </>
        )}
      </Card>
    </Stack>
  );
};
