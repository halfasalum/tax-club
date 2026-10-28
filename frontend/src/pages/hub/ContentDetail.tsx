// src/pages/hub/ContentDetail.tsx
import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Stack,
  Title,
  Text,
  Card,
  Badge,
  Group,
  Button,
  ActionIcon,
  LoadingOverlay,
  AspectRatio,
  Image,
  Paper,
  Progress,
  Typography,
  Divider,
} from '@mantine/core';
import { IconArrowLeft, IconCertificate, IconCircleCheck } from '@tabler/icons-react';
import { contentService, type Content } from '../../api/services/contentService';
import { apiClient } from '../../api/apiClient';
import { notifications } from '@mantine/notifications';
import dayjs from 'dayjs';

export const ContentDetail = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [content, setContent] = useState<Content | null>(null);
  const [progress, setProgress] = useState<{ status: string; progress_percent: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [updating, setUpdating] = useState(false);

  useEffect(() => {
    if (!id) return;
    load();
  }, [id]);

  const load = async () => {
    setLoading(true);
    try {
      const response = await contentService.getContentById(id!);
      setContent(response.data.content);
      setProgress(response.data.progress);
    } catch (error) {
      notifications.show({ title: 'Error', message: 'Failed to load content', color: 'red' });
      navigate('/learn');
    } finally {
      setLoading(false);
    }
  };

  const updateProgress = async (percent: number, status: string) => {
    if (!id) return;
    setUpdating(true);
    try {
      await apiClient.post('/progress', { content_id: id, progress_percent: percent, status });
      setProgress({ status, progress_percent: percent });
      notifications.show({ title: 'Progress saved', message: status === 'completed' ? 'Marked as complete!' : 'Progress updated', color: 'green' });
    } catch (error) {
      notifications.show({ title: 'Error', message: 'Failed to update progress', color: 'red' });
    } finally {
      setUpdating(false);
    }
  };

  if (loading) return <LoadingOverlay visible />;
  if (!content) return null;

  const isCompleted = progress?.status === 'completed';

  return (
    <Stack gap="lg" maw={860} mx="auto">
      <Group>
        <ActionIcon variant="subtle" size="lg" onClick={() => navigate('/learn')}>
          <IconArrowLeft size={20} />
        </ActionIcon>
        <Badge color="traGold">{content.content_type}</Badge>
      </Group>

      <Title order={2}>{content.title}</Title>
      <Text c="dimmed" size="sm">
        By {content.uploader?.full_name || 'TRA Tax Club'} · {dayjs(content.published_at).format('MMMM D, YYYY')}
      </Text>

      {content.content_type === 'video' && content.file_url && (
        <AspectRatio ratio={16 / 9}>
          <iframe src={content.file_url} title={content.title} frameBorder="0" allowFullScreen />
        </AspectRatio>
      )}

      {content.content_type === 'infographic' && content.file_url && (
        <Image src={content.file_url} alt={content.title} radius="md" fit="contain" />
      )}

      {content.body_text && (
        <Paper p="md" withBorder radius="md">
          <Typography>
            <div dangerouslySetInnerHTML={{ __html: content.body_text }} />
          </Typography>
        </Paper>
      )}

      {content.quiz && (
        <Card withBorder radius="md" padding="md" style={{ borderColor: 'var(--mantine-color-traGold-4)' }}>
          <Group justify="space-between">
            <Group gap="sm">
              <IconCertificate size={28} color="var(--mantine-color-traGold-6)" />
              <div>
                <Text fw={600}>Test your knowledge</Text>
                <Text size="sm" c="dimmed">A quiz is linked to this lesson</Text>
              </div>
            </Group>
            <Button onClick={() => navigate(`/quizzes/${content.quiz.quiz_id}/take`)}>Take the Quiz</Button>
          </Group>
        </Card>
      )}

      <Divider />

      <Card withBorder radius="md" padding="md">
        <Group justify="space-between" mb="sm">
          <Text fw={600}>Your Progress</Text>
          {isCompleted && <Badge color="green" leftSection={<IconCircleCheck size={14} />}>Completed</Badge>}
        </Group>
        <Progress value={progress?.progress_percent || 0} color={isCompleted ? 'green' : 'traGold'} size="lg" radius="xl" mb="md" />
        <Group>
          <Button
            variant="light"
            loading={updating}
            disabled={isCompleted}
            onClick={() => updateProgress(50, 'in_progress')}
          >
            Mark In Progress
          </Button>
          <Button
            loading={updating}
            disabled={isCompleted}
            onClick={() => updateProgress(100, 'completed')}
          >
            Mark as Complete
          </Button>
        </Group>
      </Card>
    </Stack>
  );
};
