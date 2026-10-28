// src/pages/quiz/Leaderboard.tsx
import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Stack,
  Title,
  Text,
  Table,
  Card,
  Group,
  Button,
  LoadingOverlay,
  Avatar,
  Alert,
} from '@mantine/core';
import { IconArrowLeft, IconTrophy } from '@tabler/icons-react';
import { apiClient } from '../../api/apiClient';
import { authService } from '../../api/services/authService';
import { TierBadge, type MembershipTier } from '../../components/common/TierBadge';

interface GlobalRow {
  rank: number;
  user_id: string;
  full_name: string;
  membership_id: string;
  total_points: number;
  tier: MembershipTier;
}

interface QuizRow {
  rank: number;
  user: { user_id: string; full_name: string; membership_id: string };
  score: number;
  max_score: number;
  score_percentage: number;
}

const medalColor = (rank: number) => (rank === 1 ? 'traGold' : rank === 2 ? 'gray' : rank === 3 ? 'orange' : undefined);

export const Leaderboard = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [rows, setRows] = useState<(GlobalRow | QuizRow)[]>([]);
  const [loading, setLoading] = useState(true);
  const currentUserId = authService.getUser()?.user_id;
  const isQuizScoped = Boolean(id);

  useEffect(() => {
    load();
  }, [id]);

  const load = async () => {
    setLoading(true);
    try {
      if (id) {
        const response = await apiClient.get(`/quizzes/${id}/leaderboard`);
        setRows(response.data || []);
      } else {
        const response = await apiClient.get('/leaderboard');
        setRows(response.data?.leaderboard || []);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Stack gap="lg">
      <Group>
        <Button variant="subtle" leftSection={<IconArrowLeft size={16} />} onClick={() => navigate(-1)}>
          Back
        </Button>
      </Group>

      <Group gap="xs">
        <IconTrophy color="var(--mantine-color-traGold-6)" size={28} />
        <div>
          <Title order={2}>{isQuizScoped ? 'Quiz Leaderboard' : 'Global Leaderboard'}</Title>
          <Text c="dimmed" size="sm">
            {isQuizScoped ? 'Top scorers for this quiz' : 'Top members ranked by total points earned'}
          </Text>
        </div>
      </Group>

      <Card withBorder padding="lg" radius="md" style={{ position: 'relative', minHeight: 200 }}>
        <LoadingOverlay visible={loading} />

        {!loading && rows.length === 0 ? (
          <Alert color="traGold">No rankings yet — be the first to earn points!</Alert>
        ) : (
          <Table striped highlightOnHover verticalSpacing="sm">
            <Table.Thead>
              <Table.Tr>
                <Table.Th w={70}>Rank</Table.Th>
                <Table.Th>Member</Table.Th>
                {isQuizScoped ? <Table.Th>Score</Table.Th> : <Table.Th>Points</Table.Th>}
                {!isQuizScoped && <Table.Th>Tier</Table.Th>}
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {rows.map((row) => {
                const user = 'user' in row ? row.user : row;
                const isMe = user.user_id === currentUserId;
                return (
                  <Table.Tr key={user.user_id} bg={isMe ? 'var(--mantine-color-traGold-0)' : undefined}>
                    <Table.Td>
                      <Avatar radius="xl" size="sm" color={medalColor(row.rank) || 'traDark'} variant="filled">
                        {row.rank}
                      </Avatar>
                    </Table.Td>
                    <Table.Td>
                      <Text fw={isMe ? 700 : 500}>
                        {user.full_name} {isMe && <Text component="span" c="traGold.7" size="xs">(You)</Text>}
                      </Text>
                      <Text size="xs" c="dimmed">{user.membership_id}</Text>
                    </Table.Td>
                    {'user' in row ? (
                      <Table.Td>{row.score}/{row.max_score} ({row.score_percentage}%)</Table.Td>
                    ) : (
                      <Table.Td fw={600}>{row.total_points}</Table.Td>
                    )}
                    {!isQuizScoped && 'tier' in row && (
                      <Table.Td><TierBadge tier={row.tier} size="sm" /></Table.Td>
                    )}
                  </Table.Tr>
                );
              })}
            </Table.Tbody>
          </Table>
        )}
      </Card>
    </Stack>
  );
};
