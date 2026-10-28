// src/components/common/TierBadge.tsx
import { Badge, type BadgeProps } from '@mantine/core';
import { IconMedal } from '@tabler/icons-react';

export interface MembershipTier {
  slug: string;
  name: string;
  color: string;
  min_points: number;
  points: number;
  next_tier_points: number | null;
  progress_percent: number;
}

interface TierBadgeProps extends Omit<BadgeProps, 'color' | 'children'> {
  tier: MembershipTier | null | undefined;
}

export const TierBadge = ({ tier, size = 'md', ...rest }: TierBadgeProps) => {
  if (!tier) return null;

  return (
    <Badge
      size={size}
      variant="filled"
      color={tier.color}
      leftSection={<IconMedal size={14} />}
      {...rest}
    >
      {tier.name}
    </Badge>
  );
};
