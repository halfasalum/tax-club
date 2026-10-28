// src/pages/rewards/RewardsManagement.tsx
import { useState, useEffect } from 'react';
import {
  Table,
  Button,
  Group,
  Text,
  Card,
  Title,
  Stack,
  Badge,
  ActionIcon,
  Modal,
  TextInput,
  Textarea,
  Switch,
  Alert,
  LoadingOverlay,
  Pagination,
  Tooltip,
  Select,
  NumberInput,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import {
  IconPlus,
  IconEdit,
  IconTrash,
  IconRefresh,
  IconEye,
  IconEyeOff,
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';

interface Reward {
  reward_id: string;
  title: string;
  description: string | null;
  type: string;
  points_cost: number;
  stock: number | null;
  image_url: string | null;
  is_active: boolean;
}

const TYPE_OPTIONS = [
  { value: 'airtime', label: 'Airtime' },
  { value: 'certificate', label: 'Certificate' },
  { value: 'merchandise', label: 'Merchandise' },
  { value: 'recognition', label: 'Recognition' },
];

export const RewardsManagement = () => {
  const { hasPermission } = useAuth();
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [editingReward, setEditingReward] = useState<Reward | null>(null);
  const [selectedReward, setSelectedReward] = useState<Reward | null>(null);
  const [page, setPage] = useState(1);

  const { data, loading, execute: fetchRewards } = useApi(
    () => apiClient.get('/rewards', { params: { page, per_page: 15 } }),
    { showErrorNotification: true }
  );

  const { execute: saveReward, loading: saving } = useApi(
    (payload: any) => editingReward
      ? apiClient.put(`/rewards/${editingReward.reward_id}`, payload)
      : apiClient.post('/rewards', payload),
    {
      showSuccessNotification: true,
      successMessage: editingReward ? 'Reward updated successfully' : 'Reward created successfully',
      onSuccess: () => {
        setModalOpen(false);
        fetchRewards();
      },
    }
  );

  const { execute: deleteReward, loading: deleting } = useApi(
    () => apiClient.delete(`/rewards/${selectedReward?.reward_id}`),
    {
      showSuccessNotification: true,
      successMessage: 'Reward deleted successfully',
      onSuccess: () => {
        setDeleteModalOpen(false);
        fetchRewards();
      },
    }
  );

  const { execute: toggleReward } = useApi(
    (id: string) => apiClient.patch(`/rewards/${id}/toggle`),
    {
      showSuccessNotification: true,
      successMessage: 'Reward status updated',
      onSuccess: () => fetchRewards(),
    }
  );

  useEffect(() => {
    fetchRewards();
  }, [page]);

  const form = useForm({
    initialValues: {
      title: '',
      description: '',
      type: 'airtime',
      points_cost: 50,
      stock: null as number | null,
      image_url: '',
      is_active: true,
    },
    validate: {
      title: (value) => (value ? null : 'Title is required'),
      points_cost: (value) => (value > 0 ? null : 'Points cost must be greater than 0'),
    },
  });

  const handleEdit = (reward: Reward) => {
    setEditingReward(reward);
    form.setValues({
      title: reward.title,
      description: reward.description || '',
      type: reward.type,
      points_cost: reward.points_cost,
      stock: reward.stock,
      image_url: reward.image_url || '',
      is_active: reward.is_active,
    });
    setModalOpen(true);
  };

  const rewards: Reward[] = data?.data || [];
  const totalPages = data?.meta?.last_page || 1;

  if (!hasPermission('manage_rewards')) {
    return (
      <Alert color="red" title="Access Denied">
        You don't have permission to manage rewards.
      </Alert>
    );
  }

  return (
    <Stack gap="lg">
      <Group justify="space-between">
        <div>
          <Title order={2}>Rewards Management</Title>
          <Text c="dimmed" size="sm">Create and manage the rewards catalog</Text>
        </div>
        <Group>
          <Button variant="light" leftSection={<IconRefresh size={16} />} onClick={() => fetchRewards()} loading={loading}>
            Refresh
          </Button>
          <Button
            leftSection={<IconPlus size={16} />}
            onClick={() => {
              setEditingReward(null);
              form.reset();
              setModalOpen(true);
            }}
          >
            Add Reward
          </Button>
        </Group>
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder style={{ position: 'relative', minHeight: 200 }}>
        <LoadingOverlay visible={loading} />

        <Table striped highlightOnHover>
          <Table.Thead>
            <Table.Tr>
              <Table.Th>Title</Table.Th>
              <Table.Th>Type</Table.Th>
              <Table.Th>Points</Table.Th>
              <Table.Th>Stock</Table.Th>
              <Table.Th>Status</Table.Th>
              <Table.Th ta="center">Actions</Table.Th>
            </Table.Tr>
          </Table.Thead>
          <Table.Tbody>
            {rewards.length === 0 ? (
              <Table.Tr>
                <Table.Td colSpan={6} ta="center">
                  <Text c="dimmed">No rewards yet. Create your first reward!</Text>
                </Table.Td>
              </Table.Tr>
            ) : (
              rewards.map((reward) => (
                <Table.Tr key={reward.reward_id}>
                  <Table.Td fw={500}>{reward.title}</Table.Td>
                  <Table.Td><Badge variant="light" tt="capitalize">{reward.type}</Badge></Table.Td>
                  <Table.Td>{reward.points_cost}</Table.Td>
                  <Table.Td>{reward.stock === null ? 'Unlimited' : reward.stock}</Table.Td>
                  <Table.Td>
                    <Badge color={reward.is_active ? 'green' : 'gray'}>{reward.is_active ? 'Active' : 'Inactive'}</Badge>
                  </Table.Td>
                  <Table.Td ta="center">
                    <Group gap="xs" justify="center">
                      <Tooltip label="Edit Reward">
                        <ActionIcon color="yellow" onClick={() => handleEdit(reward)}>
                          <IconEdit size={16} />
                        </ActionIcon>
                      </Tooltip>
                      <Tooltip label={reward.is_active ? 'Deactivate' : 'Activate'}>
                        <ActionIcon onClick={() => toggleReward(reward.reward_id)}>
                          {reward.is_active ? <IconEyeOff size={16} /> : <IconEye size={16} />}
                        </ActionIcon>
                      </Tooltip>
                      <Tooltip label="Delete Reward">
                        <ActionIcon color="red" onClick={() => { setSelectedReward(reward); setDeleteModalOpen(true); }}>
                          <IconTrash size={16} />
                        </ActionIcon>
                      </Tooltip>
                    </Group>
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

      <Modal opened={modalOpen} onClose={() => setModalOpen(false)} title={editingReward ? 'Edit Reward' : 'Add Reward'} size="lg" centered>
        <form onSubmit={form.onSubmit((values) => saveReward({ ...values, stock: values.stock ?? null }))}>
          <Stack gap="md">
            <TextInput label="Title" placeholder="e.g. 5,000 TSh Airtime" required withAsterisk {...form.getInputProps('title')} />
            <Textarea label="Description" placeholder="Describe this reward" {...form.getInputProps('description')} />
            <Select label="Type" data={TYPE_OPTIONS} required {...form.getInputProps('type')} />
            <NumberInput label="Points Cost" required withAsterisk min={1} {...form.getInputProps('points_cost')} />
            <NumberInput
              label="Stock"
              description="Leave empty for unlimited"
              placeholder="Unlimited"
              min={0}
              {...form.getInputProps('stock')}
            />
            <TextInput label="Image URL" placeholder="https://example.com/reward.png" {...form.getInputProps('image_url')} />
            <Switch label="Active" description="Make this reward available for redemption" {...form.getInputProps('is_active', { type: 'checkbox' })} />
            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={() => setModalOpen(false)}>Cancel</Button>
              <Button type="submit" loading={saving}>{editingReward ? 'Update Reward' : 'Create Reward'}</Button>
            </Group>
          </Stack>
        </form>
      </Modal>

      <Modal opened={deleteModalOpen} onClose={() => setDeleteModalOpen(false)} title="Delete Reward" centered>
        <Stack>
          <Alert color="red" title="Warning!">
            Are you sure you want to delete "{selectedReward?.title}"?
            <Text mt="sm" size="sm">This action cannot be undone. Rewards with redemption history cannot be deleted — deactivate them instead.</Text>
          </Alert>
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setDeleteModalOpen(false)}>Cancel</Button>
            <Button color="red" onClick={deleteReward} loading={deleting}>Delete Reward</Button>
          </Group>
        </Stack>
      </Modal>
    </Stack>
  );
};
