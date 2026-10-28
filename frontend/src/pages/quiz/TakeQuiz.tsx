// src/pages/quiz/TakeQuiz.tsx
import { useEffect, useRef, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Stack,
  Card,
  Title,
  Text,
  Radio,
  Button,
  Group,
  Badge,
  LoadingOverlay,
  Alert,
  RingProgress,
  Center,
  ActionIcon,
} from '@mantine/core';
import { IconArrowLeft, IconCircleCheck, IconCircleX, IconTrophy } from '@tabler/icons-react';
import { apiClient } from '../../api/apiClient';
import { notifications } from '@mantine/notifications';

interface Answer {
  answer_id: string;
  answer_text: string;
}

interface Question {
  question_id: string;
  question_text: string;
  question_type: string;
  points: number;
  answers: Answer[];
}

interface SubmitResult {
  attempt_id: string;
  score: number;
  max_score: number;
  score_percentage: number;
  points_earned: number;
  total_user_points: number;
  passed: boolean;
  is_best_attempt: boolean;
  message: string;
}

export const TakeQuiz = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();

  const [quizTitle, setQuizTitle] = useState('');
  const [questions, setQuestions] = useState<Question[]>([]);
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState<SubmitResult | null>(null);
  const startedAt = useRef(Date.now());

  useEffect(() => {
    if (!id) return;
    loadQuestions();
  }, [id]);

  const loadQuestions = async () => {
    setLoading(true);
    try {
      const response = await apiClient.get(`/quizzes/${id}/questions`);
      setQuizTitle(response.data.quiz.title);
      setQuestions(response.data.questions || []);
      startedAt.current = Date.now();
    } catch (error) {
      notifications.show({ title: 'Error', message: 'Failed to load quiz', color: 'red' });
      navigate('/quizzes/browse');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async () => {
    setSubmitting(true);
    try {
      const timeSpent = Math.round((Date.now() - startedAt.current) / 1000);
      const payload = {
        answers: questions.map((q) => ({
          question_id: q.question_id,
          answer_id: answers[q.question_id] || null,
        })),
        time_spent_seconds: timeSpent,
      };
      const response = await apiClient.post(`/quizzes/${id}/submit`, payload);
      setResult(response.data);
    } catch (error: any) {
      notifications.show({
        title: 'Error',
        message: error.response?.data?.message || 'Failed to submit quiz',
        color: 'red',
      });
    } finally {
      setSubmitting(false);
    }
  };

  const answeredCount = Object.keys(answers).length;

  if (loading) {
    return <LoadingOverlay visible />;
  }

  if (result) {
    return (
      <Stack gap="lg" maw={560} mx="auto">
        <Card shadow="sm" padding="xl" radius="md" withBorder>
          <Stack align="center" gap="md">
            <RingProgress
              size={160}
              thickness={14}
              roundCaps
              sections={[{ value: result.score_percentage, color: result.passed ? 'green' : 'traGold' }]}
              label={
                <Center>
                  <Stack gap={0} align="center">
                    <Text fw={700} size="xl">{result.score_percentage}%</Text>
                    <Text size="xs" c="dimmed">{result.score}/{result.max_score}</Text>
                  </Stack>
                </Center>
              }
            />
            <Badge size="lg" color={result.passed ? 'green' : 'red'} leftSection={result.passed ? <IconCircleCheck size={16} /> : <IconCircleX size={16} />}>
              {result.passed ? 'Passed' : 'Not Passed'}
            </Badge>
            <Text ta="center">{result.message}</Text>
            <Group>
              <Badge color="traGold" variant="light" leftSection={<IconTrophy size={14} />}>
                +{result.points_earned} points earned
              </Badge>
              <Badge variant="light">Total: {result.total_user_points} pts</Badge>
            </Group>

            <Group mt="md">
              <Button variant="default" onClick={() => navigate('/quizzes/browse')}>Back to Quizzes</Button>
              <Button variant="light" onClick={() => navigate(`/quizzes/${id}/results/${result.attempt_id}`)}>
                View Detailed Results
              </Button>
              <Button onClick={() => navigate(`/quizzes/${id}/leaderboard`)}>Leaderboard</Button>
            </Group>
          </Stack>
        </Card>
      </Stack>
    );
  }

  return (
    <Stack gap="lg" maw={720} mx="auto">
      <Group>
        <ActionIcon variant="subtle" size="lg" onClick={() => navigate('/quizzes/browse')}>
          <IconArrowLeft size={20} />
        </ActionIcon>
        <div>
          <Title order={2}>{quizTitle}</Title>
          <Text c="dimmed" size="sm">{answeredCount} of {questions.length} answered</Text>
        </div>
      </Group>

      {questions.length === 0 ? (
        <Alert color="yellow">This quiz has no questions yet.</Alert>
      ) : (
        questions.map((question, idx) => (
          <Card key={question.question_id} withBorder padding="lg" radius="md">
            <Stack gap="sm">
              <Group justify="space-between">
                <Text fw={600}>Q{idx + 1}. {question.question_text}</Text>
                <Badge variant="light" color="traGold">{question.points} pts</Badge>
              </Group>
              <Radio.Group
                value={answers[question.question_id] || ''}
                onChange={(value) => setAnswers((prev) => ({ ...prev, [question.question_id]: value }))}
              >
                <Stack gap="xs" mt="xs">
                  {question.answers.map((answer) => (
                    <Radio key={answer.answer_id} value={answer.answer_id} label={answer.answer_text} />
                  ))}
                </Stack>
              </Radio.Group>
            </Stack>
          </Card>
        ))
      )}

      {questions.length > 0 && (
        <Group justify="flex-end">
          <Button size="lg" loading={submitting} onClick={handleSubmit}>
            Submit Quiz
          </Button>
        </Group>
      )}
    </Stack>
  );
};
