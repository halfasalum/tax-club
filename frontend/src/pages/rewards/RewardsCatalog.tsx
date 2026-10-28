// src/pages/rewards/RewardsCatalog.tsx
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Stack,
  Title,
  Text,
  SimpleGrid,
  Card,
  Badge,
  Group,
  Button,
  LoadingOverlay,
  Pagination,
  Alert,
  Paper,
  ThemeIcon,
  Modal,
} from '@mantine/core';
import {
  IconDeviceMobile,
  IconCertificate,
  IconGift,
  IconAward,
  IconCoin,
  IconHistory,
} from '@tabler/icons-react';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';
import { useAuth } from '../../hooks/useAuth';
import { notifications } from '@mantine/notifications';

interface Reward {
  reward_id: string;
  title: string;
  description: string | null;
  type: 'airtime' | 'certificate' | 'merchandise' | 'recognition';
  points_cost: number;
  stock: number | null;
  image_url: string | null;
  is_active: boolean;
}

const typeIcon = (type: string) => {
  switch (type) {
    case 'airtime': return IconDeviceMobile;
    case 'certificate': return IconCertificate;
    case 'merchandise': return IconGift;
    default: return IconAward;
  }
};

export const RewardsCatalog = () => {
  const navigate = useNavigate();
  const { user, refreshUser } = useAuth();
  const [page, setPage] = useState(1);
  const [redeeming, setRedeeming] = useState<string | null>(null);
  const [confirmReward, setConfirmReward] = useState<Reward | null>(null);
  const [points, setPoints] = useState<number>(user?.total_points ?? 0);

  const { data, loading, execute: fetchRewards } = useApi(
    () => apiClient.get('/rewards', { params: { page, per_page: 12 } }),
    { showErrorNotification: true }
  );

  useEffect(() => {
    fetchRewards();
  }, [page]);

  useEffect(() => {
    setPoints(user?.total_points ?? 0);
  }, [user?.total_points]);

  const rewards: Reward[] = data?.data || [];
  const totalPages = data?.meta?.last_page || 1;

  const handleRedeem = async (reward: Reward) => {
    setRedeeming(reward.reward_id);
    try {
      const response = await apiClient.post(`/rewards/${reward.reward_id}/redeem`);
      setPoints(response.data.remaining_points);
      notifications.show({
        title: 'Reward redeemed!',
        message: `${reward.title} — your request is pending fulfillment.`,
        color: 'green',
      });
      await refreshUser();
      fetchRewards();
    } catch (error: any) {
      notifications.show({
        title: 'Could not redeem',
        message: error.response?.data?.message || 'Something went wrong.',
        color: 'red',
      });
    } finally {
      setRedeeming(null);
      setConfirmReward(null);
    }
  };

  return (
    <Stack gap="lg">
      <Group justify="space-between" align="flex-start">
        <div>
          <Title order={2}>Rewards</Title>
          <Text c="dimmed" size="sm">Redeem your points for airtime, certificates, merchandise and recognition</Text>
        </div>
        <Button variant="light" leftSection={<IconHistory size={16} />} onClick={() => navigate('/rewards/my-redemptions')}>
          My Redemptions
        </Button>
      </Group>

      <Paper withBorder radius="md" p="md" style={{ borderColor: 'var(--mantine-color-traGold-4)' }}>
        <Group>
          <ThemeIcon size={44} radius="md" color="traGold" variant="light">
            <IconCoin size={24} />
          </ThemeIcon>
          <div>
            <Text size="xs" c="dimmed">Your balance</Text>
            <Text fw={700} size="xl">{points} points</Text>
          </div>
        </Group>
      </Paper>

      <div style={{ position: 'relative', minHeight: 200 }}>
        <LoadingOverlay visible={loading} />

        {!loading && rewards.length === 0 ? (
          <Alert color="traGold" title="No rewards available">
            Check back soon — new rewards are added regularly.
          </Alert>
        ) : (
          <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="md">
            {rewards.map((reward) => {
              const Icon = typeIcon(reward.type);
              const outOfStock = reward.stock !== null && reward.stock <= 0;
              const canAfford = points >= reward.points_cost;

              return (
                <Card key={reward.reward_id} shadow="sm" padding="lg" radius="md" withBorder>
                  <Stack gap="sm" h="100%" justify="space-between">
                    <div>
                      <Group justify="space-between" mb="xs">
                        <ThemeIcon size={40} radius="md" color="traGold" variant="light">
                          <Icon size={22} />
                        </ThemeIcon>
                        <Badge color="traGold" variant="filled">{reward.points_cost} pts</Badge>
                      </Group>
                      <Text fw={600} size="lg">{reward.title}</Text>
                      {reward.description && (
                        <Text c="dimmed" size="sm" lineClamp={3} mt={4}>{reward.description}</Text>
                      )}
                      <Group mt="sm" gap="xs">
                        <Badge variant="outline" size="sm">{reward.type}</Badge>
                        {reward.stock !== null && (
                          <Badge variant="outline" size="sm" color={outOfStock ? 'red' : 'gray'}>
                            {outOfStock ? 'Out of stock' : `${reward.stock} left`}
                          </Badge>
                        )}
                      </Group>
                    </div>
                    <Button
                      fullWidth
                      disabled={outOfStock || !canAfford}
                      loading={redeeming === reward.reward_id}
                      onClick={() => setConfirmReward(reward)}
                    >
                      {outOfStock ? 'Out of Stock' : canAfford ? 'Redeem' : 'Not Enough Points'}
                    </Button>
                  </Stack>
                </Card>
              );
            })}
          </SimpleGrid>
        )}
      </div>

      {totalPages > 1 && (
        <Group justify="center">
          <Pagination total={totalPages} value={page} onChange={setPage} />
        </Group>
      )}

      <Modal opened={!!confirmReward} onClose={() => setConfirmReward(null)} title="Confirm Redemption" centered>
        {confirmReward && (
          <Stack>
            <Text>
              Redeem <strong>{confirmReward.title}</strong> for <strong>{confirmReward.points_cost} points</strong>?
            </Text>
            <Text size="sm" c="dimmed">
              You'll have {points - confirmReward.points_cost} points remaining. This cannot be undone.
            </Text>
            <Group justify="flex-end">
              <Button variant="default" onClick={() => setConfirmReward(null)}>Cancel</Button>
              <Button loading={!!redeeming} onClick={() => handleRedeem(confirmReward)}>Confirm Redemption</Button>
            </Group>
          </Stack>
        )}
      </Modal>
    </Stack>
  );
};
