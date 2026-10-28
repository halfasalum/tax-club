// src/pages/quiz/QuizResults.tsx
import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Stack,
  Card,
  Title,
  Text,
  Group,
  Badge,
  LoadingOverlay,
  ActionIcon,
  ThemeIcon,
  Divider,
} from '@mantine/core';
import { IconArrowLeft, IconCheck, IconX } from '@tabler/icons-react';
import { apiClient } from '../../api/apiClient';
import { notifications } from '@mantine/notifications';

interface ResultItem {
  question_id: string;
  question_text: string;
  user_answer: string | null;
  correct_answer: string | null;
  is_correct: boolean;
  points_earned: number;
  max_points: number;
}

interface AttemptSummary {
  attempt_id: string;
  score: number;
  max_score: number;
  score_percentage: number;
  points_earned: number;
  attempted_at: string;
}

export const QuizResults = () => {
  const { id, attemptId } = useParams<{ id: string; attemptId: string }>();
  const navigate = useNavigate();
  const [attempt, setAttempt] = useState<AttemptSummary | null>(null);
  const [results, setResults] = useState<ResultItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!id || !attemptId) return;
    load();
  }, [id, attemptId]);

  const load = async () => {
    setLoading(true);
    try {
      const response = await apiClient.get(`/quizzes/${id}/attempt/${attemptId}/results`);
      setAttempt(response.data.attempt);
      setResults(response.data.results || []);
    } catch (error) {
      notifications.show({ title: 'Error', message: 'Failed to load results', color: 'red' });
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <LoadingOverlay visible />;

  return (
    <Stack gap="lg" maw={720} mx="auto">
      <Group>
        <ActionIcon variant="subtle" size="lg" onClick={() => navigate('/quizzes/my-attempts')}>
          <IconArrowLeft size={20} />
        </ActionIcon>
        <Title order={2}>Quiz Results</Title>
      </Group>

      {attempt && (
        <Card withBorder padding="lg" radius="md">
          <Group justify="space-between">
            <div>
              <Text size="sm" c="dimmed">Score</Text>
              <Text fw={700} size="xl">{attempt.score} / {attempt.max_score}</Text>
            </div>
            <Badge size="lg" color={attempt.score_percentage >= 50 ? 'green' : 'red'}>
              {attempt.score_percentage}%
            </Badge>
            <div>
              <Text size="sm" c="dimmed">Points Earned</Text>
              <Text fw={700} size="xl" c="traGold.7">{attempt.points_earned}</Text>
            </div>
          </Group>
        </Card>
      )}

      <Stack gap="sm">
        {results.map((r, idx) => (
          <Card key={r.question_id} withBorder padding="md" radius="md">
            <Group justify="space-between" align="flex-start">
              <Group align="flex-start" gap="sm" wrap="nowrap">
                <ThemeIcon color={r.is_correct ? 'green' : 'red'} variant="light" radius="xl">
                  {r.is_correct ? <IconCheck size={16} /> : <IconX size={16} />}
                </ThemeIcon>
                <div>
                  <Text fw={500}>Q{idx + 1}. {r.question_text}</Text>
                  <Text size="sm" c="dimmed" mt={4}>
                    Your answer: {r.user_answer || <em>No answer</em>}
                  </Text>
                  {!r.is_correct && (
                    <Text size="sm" c="green.7">Correct answer: {r.correct_answer}</Text>
                  )}
                </div>
              </Group>
              <Badge variant="light" color={r.is_correct ? 'green' : 'gray'}>
                {r.points_earned}/{r.max_points} pts
              </Badge>
            </Group>
          </Card>
        ))}
      </Stack>

      <Divider />
      <Group justify="center">
        <Text
          component="a"
          href={`/quizzes/${id}/take`}
          onClick={(e) => { e.preventDefault(); navigate(`/quizzes/${id}/take`); }}
          size="sm"
          c="traGold.7"
          fw={600}
          style={{ cursor: 'pointer' }}
        >
          Retake this quiz
        </Text>
      </Group>
    </Stack>
  );
};
