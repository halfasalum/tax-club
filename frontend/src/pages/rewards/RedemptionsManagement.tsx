// src/pages/rewards/RedemptionsManagement.tsx
import { useEffect, useState } from 'react';
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
  Select,
  Modal,
  Textarea,
  ActionIcon,
  Tooltip,
} from '@mantine/core';
import { IconCheck, IconX, IconPackage } from '@tabler/icons-react';
import { apiClient } from '../../api/apiClient';
import { useAuth } from '../../hooks/useAuth';
import { useApi } from '../../hooks/useApi';
import { notifications } from '@mantine/notifications';

interface Redemption {
  redemption_id: string;
  points_spent: number;
  status: 'pending' | 'approved' | 'fulfilled' | 'rejected';
  notes: string | null;
  created_at: string;
  user: { user_id: string; full_name: string; membership_id: string; email: string } | null;
  reward: { reward_id: string; title: string; type: string; points_cost: number } | null;
}

const statusColor = (status: string) => {
  switch (status) {
    case 'fulfilled': return 'green';
    case 'approved': return 'blue';
    case 'rejected': return 'red';
    default: return 'yellow';
  }
};

export const RedemptionsManagement = () => {
  const { hasPermission } = useAuth();
  const [page, setPage] = useState(1);
  const [statusFilter, setStatusFilter] = useState<string | null>(null);
  const [actioning, setActioning] = useState<{ redemption: Redemption; status: string } | null>(null);
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const { data, loading, execute: fetchRedemptions } = useApi(
    () => apiClient.get('/rewards/redemptions', { params: { page, per_page: 20, status: statusFilter || undefined } }),
    { showErrorNotification: true }
  );

  useEffect(() => {
    fetchRedemptions();
  }, [page, statusFilter]);

  const redemptions: Redemption[] = data?.data || [];
  const totalPages = data?.meta?.last_page || 1;

  const handleAction = async () => {
    if (!actioning) return;
    setSubmitting(true);
    try {
      await apiClient.patch(`/rewards/redemptions/${actioning.redemption.redemption_id}/status`, {
        status: actioning.status,
        notes: notes || undefined,
      });
      notifications.show({ title: 'Success', message: `Redemption ${actioning.status}.`, color: 'green' });
      setActioning(null);
      setNotes('');
      fetchRedemptions();
    } catch (error: any) {
      notifications.show({ title: 'Error', message: error.response?.data?.message || 'Failed to update redemption', color: 'red' });
    } finally {
      setSubmitting(false);
    }
  };

  if (!hasPermission('process_redemptions')) {
    return (
      <Alert color="red" title="Access Denied">
        You don't have permission to process redemptions.
      </Alert>
    );
  }

  return (
    <Stack gap="lg">
      <Group justify="space-between">
        <div>
          <Title order={2}>Redemption Requests</Title>
          <Text c="dimmed" size="sm">Review and fulfill member reward redemptions</Text>
        </div>
        <Select
          placeholder="All statuses"
          data={[
            { value: 'pending', label: 'Pending' },
            { value: 'approved', label: 'Approved' },
            { value: 'fulfilled', label: 'Fulfilled' },
            { value: 'rejected', label: 'Rejected' },
          ]}
          value={statusFilter}
          onChange={(v) => { setStatusFilter(v); setPage(1); }}
          clearable
          w={200}
        />
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder style={{ position: 'relative', minHeight: 200 }}>
        <LoadingOverlay visible={loading} />

        <Table striped highlightOnHover>
          <Table.Thead>
            <Table.Tr>
              <Table.Th>Member</Table.Th>
              <Table.Th>Reward</Table.Th>
              <Table.Th>Points</Table.Th>
              <Table.Th>Status</Table.Th>
              <Table.Th>Requested</Table.Th>
              <Table.Th ta="center">Actions</Table.Th>
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {redemptions.length === 0 ? (
              <Table.Tr>
                <Table.Td colSpan={6} ta="center">
                  <Text c="dimmed">No redemption requests found.</Text>
                </Table.Td>
              </Table.Tr>
            ) : (
              redemptions.map((r) => (
                <Table.Tr key={r.redemption_id}>
                  <Table.Td>
                    <Text fw={500} size="sm">{r.user?.full_name || 'Unknown'}</Text>
                    <Text size="xs" c="dimmed">{r.user?.membership_id}</Text>
                  </Table.Td>
                  <Table.Td>{r.reward?.title || 'Reward removed'}</Table.Td>
                  <Table.Td>{r.points_spent}</Table.Td>
                  <Table.Td>
                    <Badge color={statusColor(r.status)} variant="light" tt="capitalize">{r.status}</Badge>
                  </Table.Td>
                  <Table.Td>{new Date(r.created_at).toLocaleDateString()}</Table.Td>
                  <Table.Td ta="center">
                    {r.status === 'pending' || r.status === 'approved' ? (
                      <Group gap="xs" justify="center">
                        {r.status === 'pending' && (
                          <Tooltip label="Approve">
                            <ActionIcon color="blue" onClick={() => setActioning({ redemption: r, status: 'approved' })}>
                              <IconCheck size={16} />
                            </ActionIcon>
                          </Tooltip>
                        )}
                        <Tooltip label="Mark Fulfilled">
                          <ActionIcon color="green" onClick={() => setActioning({ redemption: r, status: 'fulfilled' })}>
                            <IconPackage size={16} />
                          </ActionIcon>
                        </Tooltip>
                        <Tooltip label="Reject & Refund">
                          <ActionIcon color="red" onClick={() => setActioning({ redemption: r, status: 'rejected' })}>
                            <IconX size={16} />
                          </ActionIcon>
                        </Tooltip>
                      </Group>
                    ) : (
                      <Text size="xs" c="dimmed">—</Text>
                    )}
                  </Table.Td>
                </Table.Tr>
              ))
            )}
          </Table.Tbody>
        </Table>

        {totalPages > 1 && (
          <Group justify="center" mt="xl">
            <Pagination total={totalPages} value={page} onChange={setPage} />
          </Group>
        )}
      </Card>

      <Modal opened={!!actioning} onClose={() => { setActioning(null); setNotes(''); }} title={`Mark as ${actioning?.status}`} centered>
        {actioning && (
          <Stack>
            <Text size="sm">
              {actioning.status === 'rejected'
                ? `This will refund ${actioning.redemption.points_spent} points to ${actioning.redemption.user?.full_name}.`
                : `Update "${actioning.redemption.reward?.title}" for ${actioning.redemption.user?.full_name} to ${actioning.status}.`}
            </Text>
            <Textarea
              label="Notes (optional)"
              placeholder={actioning.status === 'rejected' ? 'Reason for rejection' : 'Fulfillment details, e.g. confirmation code'}
              value={notes}
              onChange={(e) => setNotes(e.currentTarget.value)}
            />
            <Group justify="flex-end">
              <Button variant="default" onClick={() => { setActioning(null); setNotes(''); }}>Cancel</Button>
              <Button color={actioning.status === 'rejected' ? 'red' : 'traGold'} loading={submitting} onClick={handleAction}>
                Confirm
              </Button>
            </Group>
          </Stack>
        )}
      </Modal>
    </Stack>
  );
};
