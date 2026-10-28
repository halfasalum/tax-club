// src/pages/quiz/QuizCatalog.tsx
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  SimpleGrid,
  Card,
  Title,
  Text,
  Stack,
  Badge,
  Button,
  Group,
  LoadingOverlay,
  Pagination,
  Alert,
} from '@mantine/core';
import { IconCertificate, IconTrophy, IconHistory, IconArrowRight } from '@tabler/icons-react';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';

interface Quiz {
  quiz_id: string;
  title: string;
  description: string | null;
  total_marks: number;
  is_active: boolean;
  content?: { content_id: string; title: string } | null;
}

export const QuizCatalog = () => {
  const navigate = useNavigate();
  const [page, setPage] = useState(1);

  const { data: quizzesData, loading, execute: fetchQuizzes } = useApi(
    () => apiClient.get('/quizzes', { params: { page, per_page: 12 } }),
    { showErrorNotification: true }
  );

  useEffect(() => {
    fetchQuizzes();
  }, [page]);

  const quizzes: Quiz[] = quizzesData?.data || [];
  const totalPages = quizzesData?.meta?.last_page || 1;

  return (
    <Stack gap="lg">
      <Group justify="space-between" align="flex-start">
        <div>
          <Title order={2}>Take a Quiz</Title>
          <Text c="dimmed" size="sm">Test what you've learned and earn points toward your membership tier</Text>
        </div>
        <Group>
          <Button variant="light" leftSection={<IconHistory size={16} />} onClick={() => navigate('/quizzes/my-attempts')}>
            My Attempts
          </Button>
          <Button variant="light" leftSection={<IconTrophy size={16} />} onClick={() => navigate('/leaderboard')}>
            Leaderboard
          </Button>
        </Group>
      </Group>

      <div style={{ position: 'relative', minHeight: 200 }}>
        <LoadingOverlay visible={loading} />

        {!loading && quizzes.length === 0 ? (
          <Alert color="traGold" title="No quizzes available">
            Check back soon — new quizzes are added regularly.
          </Alert>
        ) : (
          <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="md">
            {quizzes.map((quiz) => (
              <Card key={quiz.quiz_id} shadow="sm" padding="lg" radius="md" withBorder>
                <Stack gap="sm" h="100%" justify="space-between">
                  <div>
                    <Group justify="space-between" mb="xs">
                      <IconCertificate size={28} color="var(--mantine-color-traGold-6)" />
                      <Badge color="traGold" variant="light">{quiz.total_marks} marks</Badge>
                    </Group>
                    <Text fw={600} size="lg">{quiz.title}</Text>
                    {quiz.description && (
                      <Text c="dimmed" size="sm" lineClamp={3} mt={4}>{quiz.description}</Text>
                    )}
                    {quiz.content?.title && (
                      <Badge variant="outline" size="sm" mt="sm">Linked to: {quiz.content.title}</Badge>
                    )}
                  </div>
                  <Button
                    fullWidth
                    rightSection={<IconArrowRight size={16} />}
                    onClick={() => navigate(`/quizzes/${quiz.quiz_id}/take`)}
                  >
                    Start Quiz
                  </Button>
                </Stack>
              </Card>
            ))}
          </SimpleGrid>
        )}
      </div>

      {totalPages > 1 && (
        <Group justify="center">
          <Pagination total={totalPages} value={page} onChange={setPage} />
        </Group>
      )}
    </Stack>
  );
};
