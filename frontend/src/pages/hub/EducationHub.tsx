// src/pages/hub/EducationHub.tsx
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Stack,
  Title,
  Text,
  SimpleGrid,
  Card,
  Badge,
  Group,
  TextInput,
  Select,
  LoadingOverlay,
  Pagination,
  Alert,
  Button,
} from '@mantine/core';
import {
  IconSearch,
  IconVideo,
  IconArticle,
  IconInfinity,
  IconCertificate,
  IconArrowRight,
} from '@tabler/icons-react';
import { contentService, type Content } from '../../api/services/contentService';
import { useApi } from '../../hooks/useApi';

const typeIcon = (type: string) => {
  switch (type) {
    case 'video': return <IconVideo size={16} />;
    case 'article': return <IconArticle size={16} />;
    case 'infographic': return <IconInfinity size={16} />;
    case 'quiz': return <IconCertificate size={16} />;
    default: return <IconArticle size={16} />;
  }
};

const typeColor = (type: string) => {
  switch (type) {
    case 'video': return 'red';
    case 'article': return 'blue';
    case 'infographic': return 'green';
    case 'quiz': return 'traGold';
    default: return 'gray';
  }
};

const stripHtml = (html: string | null) => (html ? html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : '');

export const EducationHub = () => {
  const navigate = useNavigate();
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [contentType, setContentType] = useState<string | null>(null);
  const [page, setPage] = useState(1);

  const { data, loading, execute: fetchContent } = useApi(
    () => contentService.getContent({
      page,
      per_page: 12,
      content_type: contentType || undefined,
      search: search || undefined,
    }),
    { showErrorNotification: true }
  );

  useEffect(() => {
    fetchContent();
  }, [page, contentType, search]);

  useEffect(() => {
    const timeout = setTimeout(() => {
      setSearch(searchInput);
      setPage(1);
    }, 400);
    return () => clearTimeout(timeout);
  }, [searchInput]);

  const items: Content[] = data?.data || [];
  const totalPages = data?.meta?.last_page || 1;

  return (
    <Stack gap="lg">
      <div>
        <Title order={2}>Education Hub</Title>
        <Text c="dimmed" size="sm">Browse tax education videos, articles and infographics</Text>
      </div>

      <Group>
        <TextInput
          placeholder="Search content..."
          leftSection={<IconSearch size={16} />}
          value={searchInput}
          onChange={(e) => setSearchInput(e.currentTarget.value)}
          style={{ flex: 1 }}
        />
        <Select
          placeholder="All types"
          data={[
            { value: 'video', label: 'Video' },
            { value: 'article', label: 'Article' },
            { value: 'infographic', label: 'Infographic' },
            { value: 'quiz', label: 'Quiz' },
          ]}
          value={contentType}
          onChange={(v) => { setContentType(v); setPage(1); }}
          clearable
          w={180}
        />
      </Group>

      <div style={{ position: 'relative', minHeight: 200 }}>
        <LoadingOverlay visible={loading} />

        {!loading && items.length === 0 ? (
          <Alert color="traGold">No content found. Try a different search or filter.</Alert>
        ) : (
          <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="md">
            {items.map((item) => (
              <Card key={item.content_id} shadow="sm" padding="lg" radius="md" withBorder>
                <Stack gap="sm" h="100%" justify="space-between">
                  <div>
                    <Badge color={typeColor(item.content_type)} leftSection={typeIcon(item.content_type)} mb="xs">
                      {item.content_type}
                    </Badge>
                    <Text fw={600} size="lg" lineClamp={2}>{item.title}</Text>
                    {item.body_text && (
                      <Text c="dimmed" size="sm" lineClamp={3} mt={4}>{stripHtml(item.body_text)}</Text>
                    )}
                  </div>
                  <Button
                    fullWidth
                    variant="light"
                    rightSection={<IconArrowRight size={16} />}
                    onClick={() => navigate(`/learn/${item.content_id}`)}
                  >
                    {item.content_type === 'video' ? 'Watch' : 'Read'}
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
