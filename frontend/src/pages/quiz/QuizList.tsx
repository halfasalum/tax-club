// src/pages/quiz/QuizList.tsx
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
  IconSearch,
  IconQuestionMark,
  IconEye,
  IconEyeOff,
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';
import { notifications } from '@mantine/notifications';
import { QuizQuestions } from './QuizQuestions';

interface Quiz {
  quiz_id: string;
  title: string;
  description: string;
  content_id: string | null;
  total_marks: number;
  is_active: boolean;
  created_at: string;
  content?: {
    content_id: string;
    title: string;
  };
  questions_count?: number;
}

export const QuizList = () => {
  const { hasPermission } = useAuth();
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [questionsModalOpen, setQuestionsModalOpen] = useState(false);
  const [editingQuiz, setEditingQuiz] = useState<Quiz | null>(null);
  const [selectedQuiz, setSelectedQuiz] = useState<Quiz | null>(null);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [contents, setContents] = useState<any[]>([]);

  const { data: quizzesData, loading, execute: fetchQuizzes } = useApi(
    () => apiClient.get('/quizzes', { params: { page, search: search || undefined, per_page: 15 } }),
    { showErrorNotification: true }
  );

  const { execute: saveQuiz, loading: saving } = useApi(
    (data: any) => editingQuiz
      ? apiClient.put(`/quizzes/${editingQuiz.quiz_id}`, data)
      : apiClient.post('/quizzes', data),
    {
      showSuccessNotification: true,
      successMessage: editingQuiz ? 'Quiz updated successfully' : 'Quiz created successfully',
      onSuccess: () => {
        setModalOpen(false);
        fetchQuizzes();
      },
    }
  );

  const { execute: deleteQuiz, loading: deleting } = useApi(
    () => apiClient.delete(`/quizzes/${selectedQuiz?.quiz_id}`),
    {
      showSuccessNotification: true,
      successMessage: 'Quiz deleted successfully',
      onSuccess: () => {
        setDeleteModalOpen(false);
        fetchQuizzes();
      },
    }
  );

  const { execute: toggleQuiz } = useApi(
    (id: string) => apiClient.patch(`/quizzes/${id}/toggle`),
    {
      showSuccessNotification: true,
      successMessage: 'Quiz status updated',
      onSuccess: () => fetchQuizzes(),
    }
  );

  useEffect(() => {
    fetchQuizzes();
    loadContents();
  }, [page, search]);

  const loadContents = async () => {
    try {
      const response = await apiClient.get('/content', { params: { per_page: 100 } });
      setContents(response.data || []);
    } catch (error) {
      console.error('Failed to load contents:', error);
    }
  };

  const form = useForm({
    initialValues: {
      title: '',
      description: '',
      content_id: '',
      total_marks: 0,
      is_active: true,
    },
    validate: {
      title: (value) => (value ? null : 'Quiz title is required'),
      total_marks: (value) => (value > 0 ? null : 'Total marks must be greater than 0'),
    },
  });

  const handleEdit = (quiz: Quiz) => {
    setEditingQuiz(quiz);
    form.setValues({
      title: quiz.title,
      description: quiz.description || '',
      content_id: quiz.content_id || '',
      total_marks: quiz.total_marks,
      is_active: quiz.is_active,
    });
    setModalOpen(true);
  };

  const handleManageQuestions = (quiz: Quiz) => {
    setSelectedQuiz(quiz);
    setQuestionsModalOpen(true);
  };

  const quizzes = quizzesData?.data || [];
  const totalPages = quizzesData?.meta?.last_page || 1;

  if (!hasPermission('create_quiz')) {
    return (
      <Alert color="red" title="Access Denied">
        You don't have permission to manage quizzes.
      </Alert>
    );
  }

  return (
    <Stack gap="lg">
      <Group justify="space-between">
        <div>
          <Title order={2}>Quiz Management</Title>
          <Text c="dimmed" size="sm">Create and manage quizzes, questions, and answers</Text>
        </div>
        <Group>
          <Button
            variant="light"
            leftSection={<IconRefresh size={16} />}
            onClick={() => fetchQuizzes()}
            loading={loading}
          >
            Refresh
          </Button>
          <Button
            leftSection={<IconPlus size={16} />}
            onClick={() => {
              setEditingQuiz(null);
              form.reset();
              setModalOpen(true);
            }}
          >
            Create Quiz
          </Button>
        </Group>
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder>
        <TextInput
          placeholder="Search quizzes..."
          leftSection={<IconSearch size={16} />}
          value={search}
          onChange={(e) => setSearch(e.currentTarget.value)}
          mb="md"
        />

        {loading ? (
          <LoadingOverlay visible={true} />
        ) : (
          <>
            <Table striped highlightOnHover>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Title</Table.Th>
                  <Table.Th>Questions</Table.Th>
                  <Table.Th>Total Marks</Table.Th>
                  <Table.Th>Linked Content</Table.Th>
                  <Table.Th>Status</Table.Th>
                  <Table.Th>Created</Table.Th>
                  <Table.Th ta="center">Actions</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {quizzes.length === 0 ? (
                  <Table.Tr>
                    <Table.Td colSpan={7} ta="center">
                      <Text c="dimmed">No quizzes found. Create your first quiz!</Text>
                    </Table.Td>
                  </Table.Tr>
                ) : (
                  quizzes.map((quiz: Quiz) => (
                    <Table.Tr key={quiz.quiz_id}>
                      <Table.Td fw={500}>{quiz.title}</Table.Td>
                      <Table.Td>
                        <Badge variant="light" color="blue">
                          {quiz.questions_count || 0} questions
                        </Badge>
                      </Table.Td>
                      <Table.Td>{quiz.total_marks}</Table.Td>
                      <Table.Td>
                        {quiz.content?.title ? (
                          <Badge variant="light" size="sm">
                            {quiz.content.title}
                          </Badge>
                        ) : (
                          <Text size="xs" c="dimmed">None</Text>
                        )}
                      </Table.Td>
                      <Table.Td>
                        <Badge color={quiz.is_active ? 'green' : 'gray'}>
                          {quiz.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </Table.Td>
                      <Table.Td>
                        {new Date(quiz.created_at).toLocaleDateString()}
                      </Table.Td>
                      <Table.Td ta="center">
                        <Group gap="xs" justify="center">
                          <Tooltip label="Manage Questions">
                            <ActionIcon color="blue" onClick={() => handleManageQuestions(quiz)}>
                              <IconQuestionMark size={16} />
                            </ActionIcon>
                          </Tooltip>
                          <Tooltip label="Edit Quiz">
                            <ActionIcon color="yellow" onClick={() => handleEdit(quiz)}>
                              <IconEdit size={16} />
                            </ActionIcon>
                          </Tooltip>
                          <Tooltip label={quiz.is_active ? 'Deactivate' : 'Activate'}>
                            <ActionIcon onClick={() => toggleQuiz(quiz.quiz_id)}>
                              {quiz.is_active ? <IconEyeOff size={16} /> : <IconEye size={16} />}
                            </ActionIcon>
                          </Tooltip>
                          <Tooltip label="Delete Quiz">
                            <ActionIcon color="red" onClick={() => {
                              setSelectedQuiz(quiz);
                              setDeleteModalOpen(true);
                            }}>
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
          </>
        )}
      </Card>

      {/* Quiz Form Modal */}
      <Modal
        opened={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingQuiz ? 'Edit Quiz' : 'Create Quiz'}
        size="lg"
        centered
      >
        <form onSubmit={form.onSubmit((values) => saveQuiz(values))}>
          <Stack gap="md">
            <TextInput
              label="Quiz Title"
              placeholder="Enter quiz title"
              required
              withAsterisk
              {...form.getInputProps('title')}
            />
            
            <Textarea
              label="Description"
              placeholder="Enter quiz description"
              {...form.getInputProps('description')}
            />
            
            <Select
              label="Link to Content (Optional)"
              placeholder="Select content"
              data={contents.map((c: any) => ({
                value: c.content_id,
                label: c.title,
              }))}
              clearable
              {...form.getInputProps('content_id')}
            />
            
            <NumberInput
              label="Total Marks"
              placeholder="Enter total marks"
              required
              withAsterisk
              min={1}
              {...form.getInputProps('total_marks')}
            />
            
            <Switch
              label="Active"
              description="Make this quiz available to users"
              {...form.getInputProps('is_active', { type: 'checkbox' })}
            />
            
            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={() => setModalOpen(false)}>Cancel</Button>
              <Button type="submit" loading={saving}>
                {editingQuiz ? 'Update Quiz' : 'Create Quiz'}
              </Button>
            </Group>
          </Stack>
        </form>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        opened={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        title="Delete Quiz"
        centered
      >
        <Stack>
          <Alert color="red" title="Warning!">
            Are you sure you want to delete "{selectedQuiz?.title}"?
            <Text mt="sm" size="sm">
              This action cannot be undone. All questions and answers associated with this quiz will be permanently deleted.
            </Text>
          </Alert>
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setDeleteModalOpen(false)}>Cancel</Button>
            <Button color="red" onClick={deleteQuiz} loading={deleting}>
              Delete Quiz
            </Button>
          </Group>
        </Stack>
      </Modal>

      {/* Questions Management Modal */}
      <Modal
        opened={questionsModalOpen}
        onClose={() => setQuestionsModalOpen(false)}
        title={`Manage Questions: ${selectedQuiz?.title}`}
        size="xl"
        fullScreen
      >
        {selectedQuiz && (
          <QuizQuestions
            quizId={selectedQuiz.quiz_id}
            quizTitle={selectedQuiz.title}
            onClose={() => setQuestionsModalOpen(false)}
            onRefresh={() => fetchQuizzes()}
          />
        )}
      </Modal>
    </Stack>
  );
};