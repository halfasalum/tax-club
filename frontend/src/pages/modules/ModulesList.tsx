// src/pages/modules/ModulesList.tsx
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
  Menu,
  Pill,
  Accordion,
  Divider
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { 
  IconPlus, 
  IconEdit, 
  IconTrash, 
  IconLock, 
  IconRefresh,
  IconChevronRight,
  IconDotsVertical,
  IconEye,
  IconEyeOff
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';
import { notifications } from '@mantine/notifications';

interface Control {
  control_id: string;
  name: string;
  slug: string;
  module_id: string;
  description: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

interface Module {
  module_id: string;
  name: string;
  slug: string;
  description: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  active_controls: Control[];
}

export const ModulesList = () => {
  const { hasPermission } = useAuth();
  const [modalOpen, setModalOpen] = useState(false);
  const [controlModalOpen, setControlModalOpen] = useState(false);
  const [editingModule, setEditingModule] = useState<Module | null>(null);
  const [editingControl, setEditingControl] = useState<Control | null>(null);
  const [selectedModule, setSelectedModule] = useState<Module | null>(null);
  const [expandedModules, setExpandedModules] = useState<string[]>([]);

  // Fetch modules
  const { data: modulesData, loading, execute: fetchModules } = useApi(
    () => apiClient.get('/modules'),
    { showErrorNotification: true }
  );

  const modules = modulesData?.data || modulesData || [];

  // Save module
  const { execute: saveModule, loading: savingModule } = useApi(
    (data: any) => editingModule 
      ? apiClient.put(`/modules/${editingModule.module_id}`, data)
      : apiClient.post('/modules', data),
    {
      showSuccessNotification: true,
      successMessage: editingModule ? 'Module updated successfully' : 'Module created successfully',
      onSuccess: () => {
        setModalOpen(false);
        fetchModules();
      },
    }
  );

  // Delete module
  const { execute: deleteModule, loading: deletingModule } = useApi(
    (moduleId: string) => apiClient.delete(`/modules/${moduleId}`),
    {
      showSuccessNotification: true,
      successMessage: 'Module deleted successfully',
      onSuccess: () => fetchModules(),
    }
  );

  // Toggle module status
  const { execute: toggleModule, loading: togglingModule } = useApi(
    (moduleId: string) => apiClient.patch(`/modules/${moduleId}/toggle`, {}),
    {
      showSuccessNotification: true,
      successMessage: 'Module status updated successfully',
      onSuccess: () => fetchModules(),
    }
  );

  // Save control
  const { execute: saveControl, loading: savingControl } = useApi(
    (data: any) => {
      if (editingControl) {
        return apiClient.put(`/modules/controls/${editingControl.control_id}`, data);
      } else if (selectedModule) {
        return apiClient.post(`/modules/${selectedModule.module_id}/controls`, data);
      }
      return Promise.reject('No module selected');
    },
    {
      showSuccessNotification: true,
      successMessage: editingControl ? 'Control updated successfully' : 'Control created successfully',
      onSuccess: () => {
        setControlModalOpen(false);
        fetchModules();
      },
    }
  );

  // Delete control
  const { execute: deleteControl, loading: deletingControl } = useApi(
    (controlId: string) => apiClient.delete(`/modules/controls/${controlId}`),
    {
      showSuccessNotification: true,
      successMessage: 'Control deleted successfully',
      onSuccess: () => fetchModules(),
    }
  );

  useEffect(() => {
    fetchModules();
  }, []);

  const moduleForm = useForm({
    initialValues: {
      name: '',
      description: '',
    },
    validate: {
      name: (value) => (value ? null : 'Module name is required'),
    },
  });

  const controlForm = useForm({
    initialValues: {
      name: '',
      description: '',
    },
    validate: {
      name: (value) => (value ? null : 'Control name is required'),
    },
  });

  const handleEditModule = (module: Module) => {
    setEditingModule(module);
    moduleForm.setValues({ name: module.name, description: module.description || '' });
    setModalOpen(true);
  };

  const handleDeleteModule = async (module: Module) => {
    if (window.confirm(`Are you sure you want to delete module "${module.name}"? This will also delete all its controls.`)) {
      await deleteModule(module.module_id);
    }
  };

  const handleAddControl = (module: Module) => {
    setSelectedModule(module);
    setEditingControl(null);
    controlForm.reset();
    setControlModalOpen(true);
  };

  const handleEditControl = (control: Control) => {
    setEditingControl(control);
    controlForm.setValues({ name: control.name, description: control.description || '' });
    setControlModalOpen(true);
  };

  const handleDeleteControl = async (control: Control) => {
    if (window.confirm(`Are you sure you want to delete control "${control.name}"?`)) {
      await deleteControl(control.control_id);
    }
  };

  const toggleModuleExpand = (moduleId: string) => {
    setExpandedModules(prev => 
      prev.includes(moduleId) 
        ? prev.filter(id => id !== moduleId)
        : [...prev, moduleId]
    );
  };

  if (!hasPermission('view_modules')) {
    return (
      <Alert color="red" title="Access Denied">
        You don't have permission to view modules.
      </Alert>
    );
  }

  return (
    <Stack gap="lg">
      <Group justify="space-between">
        <div>
          <Title order={2}>Modules & Permissions</Title>
          <Text c="dimmed" size="sm">Manage system modules and their access controls</Text>
        </div>
        <Group>
          <Button 
            variant="light"
            leftSection={<IconRefresh size={16} />}
            onClick={() => fetchModules()}
            loading={loading}
          >
            Refresh
          </Button>
          {hasPermission('add_module') && (
            <Button 
              leftSection={<IconPlus size={16} />}
              onClick={() => {
                setEditingModule(null);
                moduleForm.reset();
                setModalOpen(true);
              }}
            >
              Create Module
            </Button>
          )}
        </Group>
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder>
        {loading ? (
          <LoadingOverlay visible={true} />
        ) : (
          <Stack gap="md">
            {modules.length === 0 ? (
              <Alert color="blue" title="No Modules">
                No modules have been created yet. Click "Create Module" to get started.
              </Alert>
            ) : (
              modules.map((module: Module) => (
                <Card key={module.module_id} withBorder p="md">
                  <Group justify="space-between" mb="sm">
                    <Group>
                      <ActionIcon 
                        variant="subtle" 
                        onClick={() => toggleModuleExpand(module.module_id)}
                      >
                        <IconChevronRight 
                          size={16} 
                          style={{ 
                            transform: expandedModules.includes(module.module_id) 
                              ? 'rotate(90deg)' 
                              : 'rotate(0deg)',
                            transition: 'transform 0.2s'
                          }}
                        />
                      </ActionIcon>
                      <div>
                        <Group gap="xs">
                          <Text size="lg" fw={600}>{module.name}</Text>
                          <Badge variant="light" size="sm">{module.slug}</Badge>
                          <Badge color={module.is_active ? 'green' : 'gray'} size="sm">
                            {module.is_active ? 'Active' : 'Inactive'}
                          </Badge>
                        </Group>
                        <Text size="sm" c="dimmed" mt={4}>{module.description}</Text>
                      </div>
                    </Group>
                    
                    <Group gap="xs">
                      {hasPermission('edit_module') && (
                        <ActionIcon 
                          color="blue" 
                          onClick={() => handleEditModule(module)}
                          title="Edit Module"
                        >
                          <IconEdit size={16} />
                        </ActionIcon>
                      )}
                      {hasPermission('edit_module') && (
                        <ActionIcon 
                          color={module.is_active ? 'yellow' : 'green'}
                          onClick={() => toggleModule(module.module_id)}
                          loading={togglingModule}
                          title={module.is_active ? 'Deactivate' : 'Activate'}
                        >
                          {module.is_active ? <IconEyeOff size={16} /> : <IconEye size={16} />}
                        </ActionIcon>
                      )}
                      {hasPermission('add_module') && (
                        <ActionIcon 
                          color="green" 
                          onClick={() => handleAddControl(module)}
                          title="Add Control"
                        >
                          <IconPlus size={16} />
                        </ActionIcon>
                      )}
                      {hasPermission('delete_module') && (
                        <ActionIcon 
                          color="red" 
                          onClick={() => handleDeleteModule(module)}
                          loading={deletingModule}
                          title="Delete Module"
                        >
                          <IconTrash size={16} />
                        </ActionIcon>
                      )}
                    </Group>
                  </Group>

                  {expandedModules.includes(module.module_id) && (
                    <>
                      <Divider my="md" />
                      <Stack gap="sm">
                        <Group justify="space-between">
                          <Text fw={500} size="sm">Permissions / Controls</Text>
                          <Badge size="sm">{module.active_controls.length} controls</Badge>
                        </Group>
                        
                        {module.active_controls.length === 0 ? (
                          <Alert color="blue" size="sm">
                            No controls added yet. Click the + button to add permissions to this module.
                          </Alert>
                        ) : (
                          <Table striped highlightOnHover>
                            <Table.Thead>
                              <Table.Tr>
                                <Table.Th>Name</Table.Th>
                                <Table.Th>Slug</Table.Th>
                                <Table.Th>Description</Table.Th>
                                <Table.Th>Status</Table.Th>
                                <Table.Th ta="center">Actions</Table.Th>
                              </Table.Tr>
                            </Table.Thead>
                            <Table.Tbody>
                              {module.active_controls.map((control) => (
                                <Table.Tr key={control.control_id}>
                                  <Table.Td fw={500}>{control.name}</Table.Td>
                                  <Table.Td>
                                    <Badge variant="light" size="xs">{control.slug}</Badge>
                                  </Table.Td>
                                  <Table.Td>{control.description || '-'}</Table.Td>
                                  <Table.Td>
                                    <Badge color={control.is_active ? 'green' : 'gray'} size="xs">
                                      {control.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                  </Table.Td>
                                  <Table.Td ta="center">
                                    <Group gap="xs" justify="center">
                                      {hasPermission('edit_module') && (
                                        <ActionIcon 
                                          size="sm" 
                                          color="blue" 
                                          onClick={() => handleEditControl(control)}
                                        >
                                          <IconEdit size={14} />
                                        </ActionIcon>
                                      )}
                                      {hasPermission('delete_module') && (
                                        <ActionIcon 
                                          size="sm" 
                                          color="red" 
                                          onClick={() => handleDeleteControl(control)}
                                        >
                                          <IconTrash size={14} />
                                        </ActionIcon>
                                      )}
                                    </Group>
                                  </Table.Td>
                                </Table.Tr>
                              ))}
                            </Table.Tbody>
                          </Table>
                        )}
                      </Stack>
                    </>
                  )}
                </Card>
              ))
            )}
          </Stack>
        )}
      </Card>

      {/* Module Form Modal */}
      <Modal
        opened={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingModule ? 'Edit Module' : 'Create Module'}
        centered
      >
        <form onSubmit={moduleForm.onSubmit((values) => saveModule(values))}>
          <Stack>
            <TextInput
              label="Module Name"
              placeholder="e.g., Content Management"
              required
              {...moduleForm.getInputProps('name')}
            />
            <Textarea
              label="Description"
              placeholder="Describe what this module does"
              {...moduleForm.getInputProps('description')}
            />
            <Group justify="flex-end">
              <Button variant="default" onClick={() => setModalOpen(false)}>Cancel</Button>
              <Button type="submit" loading={savingModule}>Save Module</Button>
            </Group>
          </Stack>
        </form>
      </Modal>

      {/* Control Form Modal */}
      <Modal
        opened={controlModalOpen}
        onClose={() => setControlModalOpen(false)}
        title={
          editingControl 
            ? 'Edit Permission' 
            : `Add Permission to ${selectedModule?.name}`
        }
        centered
      >
        <form onSubmit={controlForm.onSubmit((values) => saveControl(values))}>
          <Stack>
            <TextInput
              label="Permission Name"
              placeholder="e.g., View Content"
              required
              description="This will be converted to a slug (e.g., view_content)"
              {...controlForm.getInputProps('name')}
            />
            <Textarea
              label="Description"
              placeholder="Describe what this permission allows"
              {...controlForm.getInputProps('description')}
            />
            <Group justify="flex-end">
              <Button variant="default" onClick={() => setControlModalOpen(false)}>Cancel</Button>
              <Button type="submit" loading={savingControl}>Save Permission</Button>
            </Group>
          </Stack>
        </form>
      </Modal>
    </Stack>
  );
};