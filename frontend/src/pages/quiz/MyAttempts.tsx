// src/pages/quiz/MyAttempts.tsx
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

interface Attempt {
  attempt_id: string;
  score: number;
  max_score: number;
  score_percentage: number;
  total_points_earned: number;
  is_best_attempt: boolean;
  attempted_at: string;
  quiz: { quiz_id: string; title: string; total_marks: number } | null;
}

export const MyAttempts = () => {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);

  const { data, loading, execute: fetchAttempts } = useApi(
    () => apiClient.get('/quizzes/my-attempts', { params: { page, per_page: 15 } }),
    { showErrorNotification: true }
  );

  useEffect(() => {
    fetchAttempts();
  }, [page]);

  const attempts: Attempt[] = data?.data || [];
  const totalPages = data?.meta?.last_page || 1;

  return (
    <Stack gap="lg">
      <Group>
        <Button variant="subtle" leftSection={<IconArrowLeft size={16} />} onClick={() => navigate('/quizzes/browse')}>
          Back to Quizzes
        </Button>
      </Group>

      <div>
        <Title order={2}>My Quiz Attempts</Title>
        <Text c="dimmed" size="sm">Your quiz history and scores</Text>
      </div>

      <Card withBorder padding="lg" radius="md" style={{ position: 'relative', minHeight: 200 }}>
        <LoadingOverlay visible={loading} />

        {!loading && attempts.length === 0 ? (
          <Alert color="traGold">You haven't attempted any quizzes yet.</Alert>
        ) : (
          <>
            <Table striped highlightOnHover>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Quiz</Table.Th>
                  <Table.Th>Score</Table.Th>
                  <Table.Th>Points</Table.Th>
                  <Table.Th>Date</Table.Th>
                  <Table.Th ta="center">Actions</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {attempts.map((attempt) => (
                  <Table.Tr key={attempt.attempt_id}>
                    <Table.Td fw={500}>
                      {attempt.quiz?.title || 'Deleted quiz'}
                      {attempt.is_best_attempt && (
                        <Badge ml="xs" size="xs" color="traGold">Best</Badge>
                      )}
                    </Table.Td>
                    <Table.Td>
                      <Badge color={attempt.score_percentage >= 50 ? 'green' : 'red'} variant="light">
                        {attempt.score}/{attempt.max_score} ({attempt.score_percentage}%)
                      </Badge>
                    </Table.Td>
                    <Table.Td>{attempt.total_points_earned}</Table.Td>
                    <Table.Td>{new Date(attempt.attempted_at).toLocaleDateString()}</Table.Td>
                    <Table.Td ta="center">
                      {attempt.quiz && (
                        <Button
                          size="xs"
                          variant="light"
                          onClick={() => navigate(`/quizzes/${attempt.quiz!.quiz_id}/results/${attempt.attempt_id}`)}
                        >
                          View Results
                        </Button>
                      )}
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
