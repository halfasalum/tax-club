// src/pages/quiz/QuizQuestions.tsx
import { useState, useEffect } from 'react';
import {
    Stack,
    Card,
    Title,
    Text,
    Button,
    Group,
    TextInput,
    Textarea,
    Switch,
    ActionIcon,
    Table,
    Modal,
    Alert,
    Badge,
    Tooltip,
    Select,
    NumberInput,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import {
    IconPlus,
    IconEdit,
    IconTrash,
    IconChevronUp,
    IconChevronDown,
    IconCheck,
    IconX,
    IconArrowBackUp,
} from '@tabler/icons-react';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';
import { notifications } from '@mantine/notifications';

interface Answer {
    answer_id: string;
    answer_text: string;
    is_correct: boolean;
    order_index: number;
}

interface Question {
    question_id: string;
    question_text: string;
    question_type: 'multiple_choice' | 'true_false';
    points: number;
    order_index: number;
    is_active: boolean;
    answers: Answer[];
}

interface QuizQuestionsProps {
    quizId: string;
    quizTitle: string;
    onClose: () => void;
    onRefresh: () => void;
}

// Helper function to convert any value to boolean
const toBoolean = (value: any): boolean => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value === 1;
    if (typeof value === 'string') {
        return value === '1' || value === 'true' || value === 'True' || value === 'TRUE';
    }
    return false;
};

export const QuizQuestions = ({ quizId, quizTitle, onClose, onRefresh }: QuizQuestionsProps) => {
    const [questions, setQuestions] = useState<Question[]>([]);
    const [loading, setLoading] = useState(true);
    const [questionModalOpen, setQuestionModalOpen] = useState(false);
    const [answerModalOpen, setAnswerModalOpen] = useState(false);
    const [editingQuestion, setEditingQuestion] = useState<Question | null>(null);
    const [selectedQuestion, setSelectedQuestion] = useState<Question | null>(null);
    const [editingAnswer, setEditingAnswer] = useState<Answer | null>(null);

    // src/pages/quiz/QuizQuestions.tsx - Update the loadQuestions function

    const loadQuestions = async () => {
        setLoading(true);
        try {
            // Use the admin endpoint that includes is_correct
            const response = await apiClient.get(`/quizzes/${quizId}/manage-questions`);
            console.log('API Response:', response); // Debug log

            if (response.success) {
                // The response now has questions with is_correct included
                const transformedQuestions = (response.data.questions || []).map((q: any) => ({
                    ...q,
                    is_active: q.is_active === true || q.is_active === 1 || q.is_active === '1',
                    answers: (q.answers || []).map((a: any) => ({
                        ...a,
                        is_correct: a.is_correct === true || a.is_correct === 1 || a.is_correct === '1',
                    })),
                }));

                console.log('Transformed questions:', transformedQuestions);
                setQuestions(transformedQuestions);
            }
        } catch (error) {
            console.error('Failed to load questions:', error);
            notifications.show({ title: 'Error', message: 'Failed to load questions', color: 'red' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadQuestions();
    }, [quizId]);

    const questionForm = useForm({
        initialValues: {
            question_text: '',
            question_type: 'multiple_choice',
            points: 1,
            is_active: true,
        },
        validate: {
            question_text: (value) => (value ? null : 'Question text is required'),
            points: (value) => (value > 0 ? null : 'Points must be greater than 0'),
        },
    });

    const answerForm = useForm({
        initialValues: {
            answer_text: '',
            is_correct: false,
        },
        validate: {
            answer_text: (value) => (value ? null : 'Answer text is required'),
        },
    });

    const { execute: saveQuestion, loading: savingQuestion } = useApi(
        (data: any) => editingQuestion
            ? apiClient.put(`/quizzes/questions/${editingQuestion.question_id}`, data)
            : apiClient.post(`/quizzes/${quizId}/questions`, data),
        {
            showSuccessNotification: true,
            successMessage: editingQuestion ? 'Question updated' : 'Question added',
            onSuccess: () => {
                setQuestionModalOpen(false);
                loadQuestions();
                onRefresh();
            },
        }
    );

    const { execute: deleteQuestion } = useApi(
        (id: string) => apiClient.delete(`/quizzes/questions/${id}`),
        {
            showSuccessNotification: true,
            successMessage: 'Question deleted',
            onSuccess: () => loadQuestions(),
        }
    );

    const { execute: saveAnswer, loading: savingAnswer } = useApi(
        (data: any) => {
            // Ensure is_correct is sent as boolean/integer that backend expects
            const payload = {
                answer_text: data.answer_text,
                is_correct: data.is_correct === true || data.is_correct === 'true' ? true : false,
            };
            console.log('Saving answer payload:', payload); // Debug log

            return editingAnswer
                ? apiClient.put(`/quizzes/answers/${editingAnswer.answer_id}`, payload)
                : apiClient.post(`/quizzes/questions/${selectedQuestion?.question_id}/answers`, payload);
        },
        {
            showSuccessNotification: true,
            successMessage: editingAnswer ? 'Answer updated' : 'Answer added',
            onSuccess: () => {
                setAnswerModalOpen(false);
                loadQuestions();
            },
        }
    );

    const { execute: deleteAnswer } = useApi(
        (id: string) => apiClient.delete(`/quizzes/answers/${id}`),
        {
            showSuccessNotification: true,
            successMessage: 'Answer deleted',
            onSuccess: () => loadQuestions(),
        }
    );

    const handleReorder = async (questionId: string, direction: 'up' | 'down') => {
        const index = questions.findIndex(q => q.question_id === questionId);
        if (direction === 'up' && index === 0) return;
        if (direction === 'down' && index === questions.length - 1) return;

        const newQuestions = [...questions];
        const newIndex = direction === 'up' ? index - 1 : index + 1;
        [newQuestions[index], newQuestions[newIndex]] = [newQuestions[newIndex], newQuestions[index]];

        try {
            await apiClient.post('/quizzes/reorder-questions', {
                questions: newQuestions.map((q, idx) => ({
                    question_id: q.question_id,
                    order_index: idx
                })),
            });
            setQuestions(newQuestions);
            notifications.show({ title: 'Success', message: 'Order updated', color: 'green' });
        } catch (error) {
            notifications.show({ title: 'Error', message: 'Failed to reorder', color: 'red' });
        }
    };

    return (
        <Stack gap="md" p="md">
            {/* Header */}
            <Group justify="space-between">
                <Group>
                    <ActionIcon onClick={onClose} size="lg" variant="subtle">
                        <IconArrowBackUp size={20} />
                    </ActionIcon>
                    <div>
                        <Title order={3}>Manage Questions</Title>
                        <Text size="sm" c="dimmed">{quizTitle}</Text>
                    </div>
                </Group>
                <Button
                    leftSection={<IconPlus size={16} />}
                    onClick={() => {
                        setEditingQuestion(null);
                        questionForm.reset();
                        setQuestionModalOpen(true);
                    }}
                >
                    Add Question
                </Button>
            </Group>

            {/* Questions List */}
            {loading ? (
                <Text>Loading questions...</Text>
            ) : questions.length === 0 ? (
                <Alert color="blue" title="No Questions">
                    This quiz has no questions yet. Click "Add Question" to get started.
                </Alert>
            ) : (
                questions.map((question, idx) => (
                    <Card key={question.question_id} withBorder p="md">
                        <Stack gap="md">
                            <Group justify="space-between">
                                <Group>
                                    <Badge size="lg" variant="filled" color="blue">
                                        Q{idx + 1}
                                    </Badge>
                                    <Text fw={500} size="lg">
                                        {question.question_text}
                                    </Text>
                                    {!question.is_active && (
                                        <Badge color="gray" size="sm">Inactive</Badge>
                                    )}
                                </Group>
                                <Group gap="xs">
                                    <Tooltip label="Move Up">
                                        <ActionIcon onClick={() => handleReorder(question.question_id, 'up')}>
                                            <IconChevronUp size={16} />
                                        </ActionIcon>
                                    </Tooltip>
                                    <Tooltip label="Move Down">
                                        <ActionIcon onClick={() => handleReorder(question.question_id, 'down')}>
                                            <IconChevronDown size={16} />
                                        </ActionIcon>
                                    </Tooltip>
                                    <Tooltip label="Edit Question">
                                        <ActionIcon
                                            color="yellow"
                                            onClick={() => {
                                                setEditingQuestion(question);
                                                questionForm.setValues({
                                                    question_text: question.question_text,
                                                    question_type: question.question_type,
                                                    points: question.points,
                                                    is_active: question.is_active,
                                                });
                                                setQuestionModalOpen(true);
                                            }}
                                        >
                                            <IconEdit size={16} />
                                        </ActionIcon>
                                    </Tooltip>
                                    <Tooltip label="Delete Question">
                                        <ActionIcon
                                            color="red"
                                            onClick={() => {
                                                if (window.confirm('Delete this question? All answers will be deleted.')) {
                                                    deleteQuestion(question.question_id);
                                                }
                                            }}
                                        >
                                            <IconTrash size={16} />
                                        </ActionIcon>
                                    </Tooltip>
                                </Group>
                            </Group>

                            {/* Question Meta Info */}
                            <Group gap="md">
                                <Badge variant="light" color="gray">
                                    Type: {question.question_type === 'multiple_choice' ? 'Multiple Choice' : 'True/False'}
                                </Badge>
                                <Badge variant="light" color="yellow">
                                    Points: {question.points}
                                </Badge>
                            </Group>

                            {/* Answers Section */}
                            <Stack gap="xs">
                                <Group justify="space-between">
                                    <Text size="sm" fw={500}>Answers</Text>
                                    <Button
                                        size="xs"
                                        variant="light"
                                        leftSection={<IconPlus size={14} />}
                                        onClick={() => {
                                            setSelectedQuestion(question);
                                            setEditingAnswer(null);
                                            answerForm.reset();
                                            setAnswerModalOpen(true);
                                        }}
                                    >
                                        Add Answer
                                    </Button>
                                </Group>

                                {question.answers.length === 0 ? (
                                    <Alert color="yellow">
                                        No answers added yet. Click "Add Answer" to create answer options.
                                    </Alert>
                                ) : (
                                    <Table striped highlightOnHover>
                                        <Table.Thead>
                                            <Table.Tr>
                                                <Table.Th>Answer</Table.Th>
                                                <Table.Th style={{ width: 100 }}>Correct</Table.Th>
                                                <Table.Th style={{ width: 100 }}>Actions</Table.Th>
                                            </Table.Tr>
                                        </Table.Thead>
                                        <Table.Tbody>
                                            {question.answers.map((answer) => (
                                                <Table.Tr key={answer.answer_id}>
                                                    <Table.Td>{answer.answer_text}</Table.Td>
                                                    <Table.Td>
                                                        {answer.is_correct === true ? (
                                                            <IconCheck size={18} color="green" />
                                                        ) : (
                                                            <IconX size={18} color="red" />
                                                        )}
                                                    </Table.Td>
                                                    <Table.Td>
                                                        <Group gap="xs">
                                                            <ActionIcon
                                                                size="sm"
                                                                color="yellow"
                                                                onClick={() => {
                                                                    setSelectedQuestion(question);
                                                                    setEditingAnswer(answer);
                                                                    answerForm.setValues({
                                                                        answer_text: answer.answer_text,
                                                                        is_correct: answer.is_correct,
                                                                    });
                                                                    setAnswerModalOpen(true);
                                                                }}
                                                            >
                                                                <IconEdit size={14} />
                                                            </ActionIcon>
                                                            <ActionIcon
                                                                size="sm"
                                                                color="red"
                                                                onClick={() => {
                                                                    if (window.confirm('Delete this answer?')) {
                                                                        deleteAnswer(answer.answer_id);
                                                                    }
                                                                }}
                                                            >
                                                                <IconTrash size={14} />
                                                            </ActionIcon>
                                                        </Group>
                                                    </Table.Td>
                                                </Table.Tr>
                                            ))}
                                        </Table.Tbody>
                                    </Table>
                                )}

                                {/* Warning for True/False questions */}
                                {question.question_type === 'true_false' && question.answers.length > 0 && (
                                    <Alert color="info">
                                        Note: True/False questions should have exactly 2 answers (True and False).
                                        Only one should be marked as correct.
                                    </Alert>
                                )}
                            </Stack>
                        </Stack>
                    </Card>
                ))
            )}

            {/* Question Modal */}
            <Modal
                opened={questionModalOpen}
                onClose={() => setQuestionModalOpen(false)}
                title={editingQuestion ? 'Edit Question' : 'Add Question'}
                centered
                size="lg"
            >
                <form onSubmit={questionForm.onSubmit((values) => saveQuestion(values))}>
                    <Stack gap="md">
                        <Textarea
                            label="Question Text"
                            placeholder="Enter the question"
                            required
                            minRows={3}
                            {...questionForm.getInputProps('question_text')}
                        />

                        <Select
                            label="Question Type"
                            data={[
                                { value: 'multiple_choice', label: 'Multiple Choice' },
                                { value: 'true_false', label: 'True / False' },
                            ]}
                            {...questionForm.getInputProps('question_type')}
                        />

                        <NumberInput
                            label="Points"
                            placeholder="Points for correct answer"
                            min={1}
                            {...questionForm.getInputProps('points')}
                        />

                        <Switch
                            label="Active"
                            description="Make this question visible to users"
                            {...questionForm.getInputProps('is_active', { type: 'checkbox' })}
                        />

                        <Group justify="flex-end" mt="md">
                            <Button variant="default" onClick={() => setQuestionModalOpen(false)}>Cancel</Button>
                            <Button type="submit" loading={savingQuestion}>
                                {editingQuestion ? 'Update Question' : 'Add Question'}
                            </Button>
                        </Group>
                    </Stack>
                </form>
            </Modal>

            {/* Answer Modal */}
            <Modal
                opened={answerModalOpen}
                onClose={() => setAnswerModalOpen(false)}
                title={editingAnswer ? 'Edit Answer' : 'Add Answer'}
                centered
            >
                <form onSubmit={answerForm.onSubmit((values) => saveAnswer(values))}>
                    <Stack gap="md">
                        <TextInput
                            label="Answer Text"
                            placeholder="Enter the answer option"
                            required
                            {...answerForm.getInputProps('answer_text')}
                        />

                        <Switch
                            label="Correct Answer"
                            description="Mark this as the correct answer"
                            {...answerForm.getInputProps('is_correct', { type: 'checkbox' })}
                        />

                        <Group justify="flex-end" mt="md">
                            <Button variant="default" onClick={() => setAnswerModalOpen(false)}>Cancel</Button>
                            <Button type="submit" loading={savingAnswer}>
                                {editingAnswer ? 'Update Answer' : 'Add Answer'}
                            </Button>
                        </Group>
                    </Stack>
                </form>
            </Modal>
        </Stack>
    );
};