// src/pages/institutions/InstitutionsList.tsx
import { useState, useEffect, useCallback, useRef } from 'react';
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
  Alert,
  LoadingOverlay,
  Pagination,
  Select,
  Grid,
  Menu,
  Tooltip,
  Paper
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { 
  IconPlus, 
  IconEdit, 
  IconTrash, 
  IconRefresh,
  IconSearch,
  IconFilter,
  IconX,
  IconBuilding,
  IconMapPin,
  IconUsers
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';

interface Institution {
  institution_id: string;
  name: string;
  type: 'secondary' | 'college' | 'university';
  region: string;
  district: string | null;
  created_at: string;
  updated_at: string;
  users_count?: number;
}

interface PaginatedResponse {
  current_page: number;
  data: Institution[];
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

export const InstitutionsList = () => {
  const { hasPermission } = useAuth();
  const [modalOpen, setModalOpen] = useState(false);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [editingInstitution, setEditingInstitution] = useState<Institution | null>(null);
  const [selectedInstitution, setSelectedInstitution] = useState<Institution | null>(null);
  const [filters, setFilters] = useState({
    search: '',
    type: '',
    region: '',
  });
  const [page, setPage] = useState(1);
  const [showFilters, setShowFilters] = useState(false);
  const [searchInput, setSearchInput] = useState('');

  // Debounce search
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleSearchChange = (value: string) => {
    setSearchInput(value);
    
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }
    
    searchTimeoutRef.current = setTimeout(() => {
      setFilters(prev => ({ ...prev, search: value }));
      setPage(1);
    }, 500);
  };

  // Cleanup timeout on unmount
  useEffect(() => {
    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, []);

  // Fetch institutions with filters
  const { data: institutionsData, loading, execute: fetchInstitutions } = useApi(
    () => apiClient.get('/institutions', { 
      params: { 
        search: filters.search || undefined,
        type: filters.type || undefined,
        region: filters.region || undefined,
        page,
        per_page: 20
      } 
    }),
    { showErrorNotification: true }
  );

  // Extract paginated data
  const institutions = (institutionsData as PaginatedResponse)?.data || [];
  const totalPages = (institutionsData as PaginatedResponse)?.last_page || 1;
  const total = (institutionsData as PaginatedResponse)?.total || 0;

  // Save institution (create or update)
  const { execute: saveInstitution, loading: saving } = useApi(
    (data: any) => editingInstitution 
      ? apiClient.put(`/institutions/${editingInstitution.institution_id}`, data)
      : apiClient.post('/institutions', data),
    {
      showSuccessNotification: true,
      successMessage: editingInstitution ? 'Institution updated successfully' : 'Institution created successfully',
      onSuccess: () => {
        setModalOpen(false);
        fetchInstitutions();
      },
    }
  );

  // Delete institution
  const { execute: deleteInstitution, loading: deleting } = useApi(
    () => apiClient.delete(`/institutions/${selectedInstitution?.institution_id}`),
    {
      showSuccessNotification: true,
      successMessage: 'Institution deleted successfully',
      onSuccess: () => {
        setDeleteModalOpen(false);
        fetchInstitutions();
      },
    }
  );

  useEffect(() => {
    fetchInstitutions();
  }, [page, filters.type, filters.region, filters.search]);

  const form = useForm({
    initialValues: {
      name: '',
      type: '',
      region: '',
      district: '',
    },
    validate: {
      name: (value) => (value ? null : 'Institution name is required'),
      type: (value) => (value ? null : 'Institution type is required'),
      region: (value) => (value ? null : 'Region is required'),
    },
  });

  const handleEdit = (institution: Institution) => {
    setEditingInstitution(institution);
    form.setValues({
      name: institution.name,
      type: institution.type,
      region: institution.region,
      district: institution.district || '',
    });
    setModalOpen(true);
  };

  const handleDelete = (institution: Institution) => {
    setSelectedInstitution(institution);
    setDeleteModalOpen(true);
  };

  const handleDeleteConfirm = async () => {
    await deleteInstitution();
  };

  const resetFilters = () => {
    setFilters({ search: '', type: '', region: '' });
    setSearchInput('');
    setPage(1);
  };

  const getTypeColor = (type: string) => {
    switch (type) {
      case 'university': return 'violet';
      case 'college': return 'blue';
      case 'secondary': return 'green';
      default: return 'gray';
    }
  };

  const getTypeLabel = (type: string) => {
    switch (type) {
      case 'university': return 'University';
      case 'college': return 'College';
      case 'secondary': return 'Secondary School';
      default: return type;
    }
  };

  const canManage = hasPermission('add_institution') || hasPermission('edit_institution') || hasPermission('delete_institution');

  return (
    <Stack gap="lg">
      <Group justify="space-between" align="flex-start">
        <div>
          <Title order={2}>Institutions Management</Title>
          <Text c="dimmed" size="sm">
            Manage registered schools, colleges, and universities
          </Text>
        </div>
        <Group>
          <Button 
            variant="light"
            leftSection={<IconRefresh size={16} />}
            onClick={() => fetchInstitutions()}
            loading={loading}
          >
            Refresh
          </Button>
          {hasPermission('add_institution') && (
            <Button 
              leftSection={<IconPlus size={16} />}
              onClick={() => {
                setEditingInstitution(null);
                form.reset();
                setModalOpen(true);
              }}
            >
              Add Institution
            </Button>
          )}
        </Group>
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder>
        {/* Search and Filters */}
        <Stack gap="md">
          <Group justify="space-between">
            <TextInput
              placeholder="Search by institution name..."
              leftSection={<IconSearch size={16} />}
              rightSection={
                searchInput && (
                  <ActionIcon size="sm" variant="subtle" onClick={() => {
                    setSearchInput('');
                    setFilters(prev => ({ ...prev, search: '' }));
                  }}>
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
                    label="Institution Type"
                    placeholder="All Types"
                    data={[
                      { value: '', label: 'All Types' },
                      { value: 'secondary', label: 'Secondary School' },
                      { value: 'college', label: 'College' },
                      { value: 'university', label: 'University' },
                    ]}
                    value={filters.type}
                    onChange={(value) => {
                      setFilters(prev => ({ ...prev, type: value || '' }));
                      setPage(1);
                    }}
                    clearable
                  />
                </Grid.Col>
                <Grid.Col span={6}>
                  <TextInput
                    label="Region"
                    placeholder="Filter by region"
                    value={filters.region}
                    onChange={(e) => {
                      setFilters(prev => ({ ...prev, region: e.currentTarget.value }));
                      setPage(1);
                    }}
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
            Showing {institutions.length} of {total} institutions
          </Text>
        </Group>

        {/* Institutions Table */}
        {loading ? (
          <LoadingOverlay visible={true} />
        ) : (
          <>
            <Table striped highlightOnHover mt="md">
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>Institution Name</Table.Th>
                  <Table.Th>Type</Table.Th>
                  <Table.Th>Location</Table.Th>
                  <Table.Th>Members</Table.Th>
                  <Table.Th>Created</Table.Th>
                  {canManage && <Table.Th ta="center">Actions</Table.Th>}
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {institutions.length === 0 ? (
                  <Table.Tr>
                    <Table.Td colSpan={canManage ? 6 : 5} ta="center">
                      <Stack align="center" py="xl">
                        <IconBuilding size={48} stroke={1.5} color="gray" />
                        <Text c="dimmed">No institutions found</Text>
                        {hasPermission('add_institution') && (
                          <Button 
                            variant="light" 
                            onClick={() => {
                              setEditingInstitution(null);
                              form.reset();
                              setModalOpen(true);
                            }}
                          >
                            Add your first institution
                          </Button>
                        )}
                      </Stack>
                    </Table.Td>
                  </Table.Tr>
                ) : (
                  institutions.map((institution: Institution) => (
                    <Table.Tr key={institution.institution_id}>
                      <Table.Td fw={500}>
                        <Group gap="xs">
                          <IconBuilding size={16} color="gray" />
                          <Text fw={500}>{institution.name}</Text>
                        </Group>
                      </Table.Td>
                      <Table.Td>
                        <Badge color={getTypeColor(institution.type)}>
                          {getTypeLabel(institution.type)}
                        </Badge>
                      </Table.Td>
                      <Table.Td>
                        <Group gap="xs">
                          <IconMapPin size={14} color="gray" />
                          <Text size="sm">{institution.region}</Text>
                          {institution.district && (
                            <Text size="sm" c="dimmed">({institution.district})</Text>
                          )}
                        </Group>
                      </Table.Td>
                      <Table.Td>
                        <Group gap="xs">
                          <IconUsers size={14} color="gray" />
                          <Text size="sm">{institution.users_count || 0}</Text>
                        </Group>
                      </Table.Td>
                      <Table.Td>
                        <Text size="xs" c="dimmed">
                          {new Date(institution.created_at).toLocaleDateString()}
                        </Text>
                      </Table.Td>
                      {canManage && (
                        <Table.Td ta="center">
                          <Group gap="xs" justify="center">
                            {hasPermission('edit_institution') && (
                              <Tooltip label="Edit Institution">
                                <ActionIcon color="blue" onClick={() => handleEdit(institution)}>
                                  <IconEdit size={16} />
                                </ActionIcon>
                              </Tooltip>
                            )}
                            {hasPermission('delete_institution') && (institution.users_count === 0 || !institution.users_count) && (
                              <Tooltip label="Delete Institution">
                                <ActionIcon color="red" onClick={() => handleDelete(institution)}>
                                  <IconTrash size={16} />
                                </ActionIcon>
                              </Tooltip>
                            )}
                            {hasPermission('delete_institution') && institution.users_count && institution.users_count > 0 && (
                              <Tooltip label="Cannot delete - has members">
                                <ActionIcon color="gray" disabled>
                                  <IconTrash size={16} />
                                </ActionIcon>
                              </Tooltip>
                            )}
                          </Group>
                        </Table.Td>
                      )}
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

      {/* Institution Form Modal */}
      <Modal
        opened={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingInstitution ? 'Edit Institution' : 'Add New Institution'}
        centered
        size="lg"
      >
        <form onSubmit={form.onSubmit((values) => saveInstitution(values))}>
          <Stack gap="md">
            <TextInput
              label="Institution Name"
              placeholder="e.g., University of Dar es Salaam"
              required
              withAsterisk
              {...form.getInputProps('name')}
            />
            
            <Select
              label="Institution Type"
              placeholder="Select institution type"
              required
              withAsterisk
              data={[
                { value: 'secondary', label: 'Secondary School' },
                { value: 'college', label: 'College' },
                { value: 'university', label: 'University' },
              ]}
              {...form.getInputProps('type')}
            />
            
            <TextInput
              label="Region"
              placeholder="e.g., Dar es Salaam"
              required
              withAsterisk
              {...form.getInputProps('region')}
            />
            
            <TextInput
              label="District (Optional)"
              placeholder="e.g., Ilala"
              {...form.getInputProps('district')}
            />

            <Group justify="flex-end" mt="md">
              <Button variant="default" onClick={() => setModalOpen(false)}>Cancel</Button>
              <Button type="submit" loading={saving}>
                {editingInstitution ? 'Update Institution' : 'Create Institution'}
              </Button>
            </Group>
          </Stack>
        </form>
      </Modal>

      {/* Delete Confirmation Modal */}
      <Modal
        opened={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        title="Delete Institution"
        centered
      >
        <Stack>
          <Alert color="red" title="Warning!">
            Are you sure you want to delete "{selectedInstitution?.name}"?
            {selectedInstitution?.users_count && selectedInstitution.users_count > 0 ? (
              <Text mt="sm" size="sm" color="red">
                Warning: This institution has {selectedInstitution.users_count} member(s). 
                You cannot delete it until all members are removed.
              </Text>
            ) : (
              <Text mt="sm" size="sm">
                This action cannot be undone. All data associated with this institution will be permanently deleted.
              </Text>
            )}
          </Alert>
          
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setDeleteModalOpen(false)}>Cancel</Button>
            <Button 
              color="red" 
              onClick={handleDeleteConfirm} 
              loading={deleting}
              disabled={selectedInstitution?.users_count ? selectedInstitution.users_count > 0 : false}
            >
              Delete Institution
            </Button>
          </Group>
        </Stack>
      </Modal>
    </Stack>
  );
};