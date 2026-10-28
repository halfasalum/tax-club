// src/components/common/TierProgress.tsx
import { Group, Progress, Text, Stack } from '@mantine/core';
import { type MembershipTier } from './TierBadge';

export const TierProgress = ({ tier }: { tier: MembershipTier | null | undefined }) => {
  if (!tier) return null;

  return (
    <Stack gap={4}>
      <Group justify="space-between">
        <Text size="xs" c="dimmed">{tier.points} pts</Text>
        <Text size="xs" c="dimmed">
          {tier.next_tier_points ? `${tier.next_tier_points} pts to next tier` : 'Top tier reached'}
        </Text>
      </Group>
      <Progress value={tier.progress_percent} color={tier.color} size="sm" radius="xl" />
    </Stack>
  );
};
