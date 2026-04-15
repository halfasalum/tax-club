// src/pages/content/ContentList.tsx
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
  Select,
  Textarea,
  Switch,
  Alert,
  LoadingOverlay,
  Pagination,
  Menu,
  Tooltip,
  Paper,
  Grid,
  Image,
  AspectRatio,
  Chip,
  Input,
  Divider,
  Typography
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { DatePickerInput } from '@mantine/dates';
import { RichTextEditor, Link } from '@mantine/tiptap';
import { useEditor } from '@tiptap/react';
import Highlight from '@tiptap/extension-highlight';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Superscript from '@tiptap/extension-superscript';
import SubScript from '@tiptap/extension-subscript';
import {
  IconPlus,
  IconEdit,
  IconTrash,
  IconRefresh,
  IconSearch,
  IconFilter,
  IconX,
  IconVideo,
  IconArticle,
  IconInfinity,
  IconCertificate,
  IconEye,
  IconEyeOff,
  IconDotsVertical,
  IconCalendar,
  IconUser,
  IconClock
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';
import { contentService, type Content } from '../../api/services/contentService';
import { useApi } from '../../hooks/useApi';
import { notifications } from '@mantine/notifications';
import dayjs from 'dayjs';

export const ContentList = () => {
  const { hasPermission, user } = useAuth();
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [viewModalOpen, setViewModalOpen] = useState(false);
  const [editingContent, setEditingContent] = useState<Content | null>(null);
  const [selectedContent, setSelectedContent] = useState<Content | null>(null);
  const [filters, setFilters] = useState({
    search: '',
    content_type: '',
  });
  const [page, setPage] = useState(1);
  const [showFilters, setShowFilters] = useState(false);
  const [searchInput, setSearchInput] = useState('');

  // Debounce search
  const searchTimeoutRef = useState<ReturnType<typeof setTimeout>>();

  const handleSearchChange = (value: string) => {
    setSearchInput(value);
    
    if (searchTimeoutRef[0]) {
      clearTimeout(searchTimeoutRef[0]);
    }
    
    const timeout = setTimeout(() => {
      setFilters(prev => ({ ...prev, search: value }));
      setPage(1);
    }, 500);
    
    searchTimeoutRef[0] = timeout;
  };

  // Fetch content
  const { data: contentData, loading, execute: fetchContent } = useApi(
    () => contentService.getContent({
      page,
      per_page: 15,
      content_type: filters.content_type || undefined,
      search: filters.search || undefined,
    }),
    { showErrorNotification: true }
  );

  const contents = contentData?.data || [];
  const totalPages = contentData?.last_page || 1;
  const total = contentData?.total || 0;

  // Save content
  const { execute: saveContent, loading: saving } = useApi(
    (data: any) => editingContent
      ? contentService.updateContent(editingContent.content_id, data)
      : contentService.createContent(data),
    {
      showSuccessNotification: true,
      successMessage: editingContent ? 'Content updated successfully' : 'Content created successfully',
      onSuccess: () => {
        setModalOpen(false);
        fetchContent();
      },
    }
  );

  // Delete content
  const { execute: deleteContent, loading: deleting } = useApi(
    () => contentService.deleteContent(selectedContent!.content_id),
    {
      showSuccessNotification: true,
      successMessage: 'Content deleted successfully',
      onSuccess: () => {
        setDeleteModalOpen(false);
        fetchContent();
      },
    }
  );

  // Toggle content status
  const { execute: toggleContent } = useApi(
    (id: string) => contentService.toggleContent(id),
    {
      showSuccessNotification: true,
      successMessage: 'Content status updated',
      onSuccess: () => fetchContent(),
    }
  );

  useEffect(() => {
    fetchContent();
  }, [page, filters.content_type, filters.search]);

  useEffect(() => {
    return () => {
      if (searchTimeoutRef[0]) {
        clearTimeout(searchTimeoutRef[0]);
      }
    };
  }, []);

  const form = useForm({
    initialValues: {
      title: '',
      content_type: '',
      file_url: '',
      body_text: '',
      published_at: null as Date | null,
      is_active: true,
    },
    validate: {
      title: (value) => (value ? null : 'Title is required'),
      content_type: (value) => (value ? null : 'Content type is required'),
    },
  });

  const editor = useEditor({
    extensions: [
      StarterKit,
      Underline,
      Link,
      Superscript,
      SubScript,
      Highlight,
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
    ],
    content: form.values.body_text,
    onUpdate({ editor }) {
      form.setFieldValue('body_text', editor.getHTML());
    },
  });

  const handleEdit = (content: Content) => {
    setEditingContent(content);
    form.setValues({
      title: content.title,
      content_type: content.content_type,
      file_url: content.file_url || '',
      body_text: content.body_text || '',
      published_at: content.published_at ? new Date(content.published_at) : null,
      is_active: content.is_active,
    });
    editor?.commands.setContent(content.body_text || '');
    setModalOpen(true);
  };

  const handleView = (content: Content) => {
    setSelectedContent(content);
    setViewModalOpen(true);
  };

  const handleDelete = (content: Content) => {
    setSelectedContent(content);
    setDeleteModalOpen(true);
  };

  const handleToggle = async (content: Content) => {
    await toggleContent(content.content_id);
  };

  const resetFilters = () => {
    setFilters({ search: '', content_type: '' });
    setSearchInput('');
    setPage(1);
  };

  const getContentTypeIcon = (type: string) => {
    switch (type) {
      case 'video': return <IconVideo size={16} />;
      case 'article': return <IconArticle size={16} />;
      case 'infographic': return <IconInfinity size={16} />;
      case 'quiz': return <IconCertificate size={16} />;
      default: return <IconArticle size={16} />;
    }
  };

  const getContentTypeColor = (type: string) => {
    switch (type) {
      case 'video': return 'red';
      case 'article': return 'blue';
      case 'infographic': return 'green';
      case 'quiz': return 'orange';
      default: return 'gray';
    }
  };

  const canManage = hasPermission('upload_content') || hasPermission('edit_content') || hasPermission('delete_content');

  return (
    <Stack gap="lg">
      <Group justify="space-between" align="flex-start">
        <div>
          <Title order={2}>Content Management</Title>
          <Text c="dimmed" size="sm">
            Manage learning content — videos, articles, infographics, and quizzes
          </Text>
        </div>
        <Group>
          <Button
            variant="light"
            leftSection={<IconRefresh size={16} />}
            onClick={() => fetchContent()}
            loading={loading}
          >
            Refresh
          </Button>
          {hasPermission('upload_content') && (
            <Button
              leftSection={<IconPlus size={16} />}
              onClick={() => {
                setEditingContent(null);
                form.reset();
                editor?.commands.setContent('');
                setModalOpen(true);
              }}
            >
              Upload Content
            </Button>
          )}
        </Group>
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder>
        {/* Search and Filters */}
        <Stack gap="md">
          <Group justify="space-between">
            <TextInput
              placeholder="Search by title..."
              leftSection={<IconSearch size={16} />}
              rightSection={
                searchInput && (
                  <ActionIcon size="sm" variant="subtle" onClick={() => handleSearchChange('')}>
                    <IconX size={14} />
                  </ActionIcon>
                )
              }
              value={searchInput}
              onChange={(e) => handleSearchChange(e.currentTarget.value)}
              style={{ flex: 1 }}
            />
            <Button
              variant={showFilters ? 'filled' : 'light'}
              onClick={() => setShowFilters(!showFilters)}
              leftSection={<IconFilter size={16} />}
            >
              Filters
            </Button>
          </Group>

          {showFilters && (
            <Paper withBorder p="md" radius="md">
              <Grid>
                <Grid.Col span={6}>
                  <Select
                    label="Content Type"
                    placeholder="All Types"
                    data={[
                      { value: '', label: 'All Types' },
                      { value: 'video', label: 'Video' },
                      { value: 'article', label: 'Article' },
                      { value: 'infographic', label: 'Infographic' },
                      { value: 'quiz', label: 'Quiz' },
                    ]}
                    value={filters.content_type}
                    onChange={(value) => {
                      setFilters(prev => ({ ...prev, content_type: value || '' }));
                      setPage(1);
                    }}
                    clearable
                  />
                </Grid.Col>
              </Grid>
              <Group justify="flex-end" mt="md">
                <Button variant="subtle" onClick={resetFilters} size="sm">
                  Reset Filters
                </Button>
              </Group>
            </Paper>
          )}
        </Stack>

        {/* Results count */}
        <Group justify="space-between" mt="md">
          <Text size="sm" c="dimmed">
            Showing {contents.length} of {total} content items
          </Text>
        </Group>

        {/* Content Table */}
        {loading ? (
          <LoadingOverlay visible={true} />
        ) : (
          <>
            <Table striped highlightOnHover mt="md">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Title</Table.Th>
                  <Table.Th>Type</Table.Th>
                  <Table.Th>Status</Table.Th>
                  <Table.Th>Published</Table.Th>
                  <Table.Th>Uploaded By</Table.Th>
                  <Table.Th ta="center">Actions</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {contents.length === 0 ? (
                  <Table.Tr>
                    <Table.Td colSpan={6} ta="center">
                      <Stack align="center" py="xl">
                        <IconArticle size={48} stroke={1.5} color="gray" />
                        <Text c="dimmed">No content found</Text>
                        {hasPermission('upload_content') && (
                          <Button
                            variant="light"
                            onClick={() => {
                              setEditingContent(null);
                              form.reset();
                              editor?.commands.setContent('');
                              setModalOpen(true);
                            }}
                          >
                            Upload your first content
                          </Button>
                        )}
                      </Stack>
                    </Table.Td>
                  </Table.Tr>
                ) : (
                  contents.map((content: Content) => (
                    <Table.Tr key={content.content_id}>
                      <Table.Td>
                        <Group gap="xs">
                          {getContentTypeIcon(content.content_type)}
                          <Text fw={500} size="sm">{content.title}</Text>
                        </Group>
                      </Table.Td>
                      <Table.Td>
                        <Badge color={getContentTypeColor(content.content_type)}>
                          {content.content_type.charAt(0).toUpperCase() + content.content_type.slice(1)}
                        </Badge>
                      </Table.Td>
                      <Table.Td>
                        <Badge color={content.is_active ? 'green' : 'gray'}>
                          {content.is_active ? 'Published' : 'Draft'}
                        </Badge>
                      </Table.Td>
                      <Table.Td>
                        <Group gap="xs">
                          <IconCalendar size={14} />
                          <Text size="xs">
                            {dayjs(content.published_at).format('MMM D, YYYY')}
                          </Text>
                        </Group>
                      </Table.Td>
                      <Table.Td>
                        <Group gap="xs">
                          <IconUser size={14} />
                          <Text size="xs">{content.uploader?.full_name || 'Unknown'}</Text>
                        </Group>
                      </Table.Td>
                      <Table.Td ta="center">
                        <Group gap="xs" justify="center">
                          <Tooltip label="View">
                            <ActionIcon color="blue" onClick={() => handleView(content)}>
                              <IconEye size={16} />
                            </ActionIcon>
                          </Tooltip>
                          {hasPermission('edit_content') && (
                            <Tooltip label="Edit">
                              <ActionIcon color="yellow" onClick={() => handleEdit(content)}>
                                <IconEdit size={16} />
                              </ActionIcon>
                            </Tooltip>
                          )}
                          {hasPermission('edit_content') && (
                            <Tooltip label={content.is_active ? 'Unpublish' : 'Publish'}>
                              <ActionIcon onClick={() => handleToggle(content)}>
                                {content.is_active ? <IconEyeOff size={16} /> : <IconEye size={16} />}
                              </ActionIcon>
                            </Tooltip>
                          )}
                          {hasPermission('delete_content') && (
                            <Tooltip label="Delete">
                              <ActionIcon color="red" onClick={() => handleDelete(content)}>
                                <IconTrash size={16} />
                              </ActionIcon>
                            </Tooltip>
                          )}
                        </Group>
                      </Table.Td>
                    </Table.Tr>
                  ))
                )}
              </Table.Tbody>
            </Table>

            {/* Pagination */}
            {totalPages > 1 && (
              <Group justify="center" mt="xl">
                <Pagination
                  total={totalPages}
                  value={page}
                  onChange={setPage}
                  size="sm"
                />
              </Group>
            )}
          </>
        )}
      </Card>

      {/* Content Form Modal */}
      <Modal
        opened={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingContent ? 'Edit Content' : 'Upload New Content'}
        fullScreen
      >
        <form onSubmit={form.onSubmit((values) => saveContent(values))}>
          <Stack gap="md">
            <TextInput
              label="Title"
              placeholder="Enter content title"
              required
              withAsterisk
              {...form.getInputProps('title')}
            />

            <Select
              label="Content Type"
              placeholder="Select content type"
              required
              withAsterisk
              data={[
                { value: 'video', label: 'Video' },
                { value: 'article', label: 'Article' },
                { value: 'infographic', label: 'Infographic' },
                { value: 'quiz', label: 'Quiz' },
              ]}
              {...form.getInputProps('content_type')}
            />

            <TextInput
              label="File URL"
              placeholder="https://example.com/video.mp4"
              description="URL for video, infographic image, or quiz link"
              {...form.getInputProps('file_url')}
            />

            <div>
              <Text size="sm" fw={500} mb={3}>Body Text</Text>
              <RichTextEditor editor={editor}>
                <RichTextEditor.Toolbar sticky stickyOffset={60}>
                  <RichTextEditor.ControlsGroup>
                    <RichTextEditor.Bold />
                    <RichTextEditor.Italic />
                    <RichTextEditor.Underline />
                    <RichTextEditor.Strikethrough />
                    <RichTextEditor.ClearFormatting />
                    <RichTextEditor.Highlight />
                    <RichTextEditor.Code />
                  </RichTextEditor.ControlsGroup>

                  <RichTextEditor.ControlsGroup>
                    <RichTextEditor.H1 />
                    <RichTextEditor.H2 />
                    <RichTextEditor.H3 />
                    <RichTextEditor.H4 />
                  </RichTextEditor.ControlsGroup>

                  <RichTextEditor.ControlsGroup>
                    <RichTextEditor.Blockquote />
                    <RichTextEditor.Hr />
                    <RichTextEditor.BulletList />
                    <RichTextEditor.OrderedList />
                    <RichTextEditor.Subscript />
                    <RichTextEditor.Superscript />
                  </RichTextEditor.ControlsGroup>

                  <RichTextEditor.ControlsGroup>
                    <RichTextEditor.Link />
                    <RichTextEditor.Unlink />
                  </RichTextEditor.ControlsGroup>

                  <RichTextEditor.ControlsGroup>
                    <RichTextEditor.AlignLeft />
                    <RichTextEditor.AlignCenter />
                    <RichTextEditor.AlignJustify />
                    <RichTextEditor.AlignRight />
                  </RichTextEditor.ControlsGroup>

                  <RichTextEditor.ControlsGroup>
                    <RichTextEditor.Undo />
                    <RichTextEditor.Redo />
                  </RichTextEditor.ControlsGroup>
                </RichTextEditor.Toolbar>
                <RichTextEditor.Content style={{ minHeight: '60vh' }} />
              </RichTextEditor>
              {form.errors.body_text && <Text c="red" size="xs" mt="xs">{form.errors.body_text}</Text>}
            </div>

            <DatePickerInput
              label="Publish Date"
              placeholder="Select publish date"
              {...form.getInputProps('published_at')}
            />

            <Switch
              label="Active / Published"
              description="Make this content visible to users"
              {...form.getInputProps('is_active', { type: 'checkbox' })}
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={() => setModalOpen(false)}>Cancel</Button>
              <Button type="submit" loading={saving}>
                {editingContent ? 'Update Content' : 'Create Content'}
              </Button>
            </Group>
          </Stack>
        </form>
      </Modal>

      {/* View Content Modal */}
      <Modal
        opened={viewModalOpen}
        onClose={() => setViewModalOpen(false)}
        title={selectedContent?.title}
        size="xl"
        fullScreen
      >
        {selectedContent && (
          <Stack gap="md">
            <Group justify="space-between">
              <Badge size="lg" color={getContentTypeColor(selectedContent.content_type)}>
                {selectedContent.content_type.toUpperCase()}
              </Badge>
              <Badge color={selectedContent.is_active ? 'green' : 'gray'}>
                {selectedContent.is_active ? 'Published' : 'Draft'}
              </Badge>
            </Group>

            {selectedContent.content_type === 'video' && selectedContent.file_url && (
              <AspectRatio ratio={16 / 9}>
                <iframe
                  src={selectedContent.file_url}
                  title={selectedContent.title}
                  frameBorder="0"
                  allowFullScreen
                />
              </AspectRatio>
            )}

            {selectedContent.content_type === 'infographic' && selectedContent.file_url && (
              <Image
                src={selectedContent.file_url}
                alt={selectedContent.title}
                fit="contain"
              />
            )}

            {selectedContent.body_text && (
              <Paper p="md" withBorder>
                <Typography>
                  <div dangerouslySetInnerHTML={{ __html: selectedContent.body_text }} />
                </Typography>
              </Paper>
            )}

            <Divider />

            <Group justify="space-between">
              <Group gap="xs">
                <IconUser size={14} />
                <Text size="sm">Uploaded by: {selectedContent.uploader?.full_name || 'Unknown'}</Text>
              </Group>
              <Group gap="xs">
                <IconCalendar size={14} />
                <Text size="sm">Published: {dayjs(selectedContent.published_at).format('MMMM D, YYYY')}</Text>
              </Group>
            </Group>
          </Stack>
        )}
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        opened={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        title="Delete Content"
        centered
      >
        <Stack>
          <Alert color="red" title="Warning!">
            Are you sure you want to delete "{selectedContent?.title}"?
            <Text mt="sm" size="sm">
              This action cannot be undone. All data associated with this content will be permanently deleted.
            </Text>
          </Alert>

          <Group justify="flex-end">
            <Button variant="default" onClick={() => setDeleteModalOpen(false)}>Cancel</Button>
            <Button color="red" onClick={deleteContent} loading={deleting}>
              Delete Content
            </Button>
          </Group>
        </Stack>
      </Modal>
    </Stack>
  );
};