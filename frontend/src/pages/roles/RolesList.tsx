// src/pages/roles/RolesList.tsx
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
  Checkbox,
  LoadingOverlay,
  Alert,
  Pill,
  Divider
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { 
  IconPlus, 
  IconEdit, 
  IconTrash, 
  IconShield,
  IconLock,
  IconRefresh
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';
import { apiClient } from '../../api/apiClient';
import { useApi } from '../../hooks/useApi';
import { notifications } from '@mantine/notifications';

interface Role {
  role_id: string;
  name: string;
  slug: string;
  description: string;
  controls?: Control[];
}

interface Control {
  control_id: string;
  name: string;
  slug: string;
  module_id: string;
  description: string;
  is_active: boolean;
}

interface Module {
  module_id: string;
  name: string;
  slug: string;
  description: string;
  active_controls: Control[];
}

export const RolesList = () => {
  const { hasPermission } = useAuth();
  const [modalOpen, setModalOpen] = useState(false);
  const [editingRole, setEditingRole] = useState<Role | null>(null);
  const [permissionsModalOpen, setPermissionsModalOpen] = useState(false);
  const [selectedRole, setSelectedRole] = useState<Role | null>(null);
  const [allModules, setAllModules] = useState<Module[]>([]);
  const [selectedControls, setSelectedControls] = useState<string[]>([]);
  const [loadingControls, setLoadingControls] = useState(false);

  // Fetch roles
  const { data: rolesData, loading: rolesLoading, execute: fetchRoles } = useApi(
    () => apiClient.get('/roles'),
    { showErrorNotification: true }
  );

  // Extract roles array from response
  const roles = rolesData?.data || rolesData || [];

  // Fetch modules with controls
  const fetchModules = async () => {
    setLoadingControls(true);
    try {
      const response = await apiClient.get('/modules');
      // Extract modules from the nested response structure
      const modulesData = response?.data || response;
      setAllModules(Array.isArray(modulesData) ? modulesData : []);
    } catch (error) {
      console.error('Failed to load modules:', error);
      notifications.show({
        title: 'Error',
        message: 'Failed to load modules and permissions',
        color: 'red',
      });
    } finally {
      setLoadingControls(false);
    }
  };

  // Save role (create or update)
  const { execute: saveRole, loading: saving } = useApi(
    (data: any) => editingRole 
      ? apiClient.put(`/roles/${editingRole.role_id}`, data)
      : apiClient.post('/roles', data),
    {
      showSuccessNotification: true,
      successMessage: editingRole ? 'Role updated successfully' : 'Role created successfully',
      onSuccess: () => {
        setModalOpen(false);
        fetchRoles();
      },
    }
  );

  // Delete role
  const { execute: deleteRole, loading: deleting } = useApi(
    (roleId: string) => apiClient.delete(`/roles/${roleId}`),
    {
      showSuccessNotification: true,
      successMessage: 'Role deleted successfully',
      onSuccess: () => fetchRoles(),
    }
  );

  // Assign controls to role
  const { execute: assignControls, loading: assigning } = useApi(
    (data: { roleId: string; controlIds: string[] }) => 
      apiClient.post(`/roles/${data.roleId}/controls`, { control_ids: data.controlIds }),
    {
      showSuccessNotification: true,
      successMessage: 'Permissions updated successfully',
      onSuccess: () => {
        setPermissionsModalOpen(false);
        fetchRoles();
      },
    }
  );

  useEffect(() => {
    fetchRoles();
    fetchModules();
  }, []);

  const form = useForm({
    initialValues: {
      name: '',
      description: '',
    },
    validate: {
      name: (value) => (value ? null : 'Role name is required'),
    },
  });

  const handleEdit = (role: Role) => {
    setEditingRole(role);
    form.setValues({ name: role.name, description: role.description || '' });
    setModalOpen(true);
  };

  const handleDelete = async (roleId: string, roleName: string) => {
    if (window.confirm(`Are you sure you want to delete role "${roleName}"? This action cannot be undone.`)) {
      await deleteRole(roleId);
    }
  };

  const handlePermissions = async (role: Role) => {
    setSelectedRole(role);
    setSelectedControls(role.controls?.map(c => c.control_id) || []);
    setPermissionsModalOpen(true);
  };

  const handleSavePermissions = async () => {
    if (selectedRole) {
      await assignControls({ 
        roleId: selectedRole.role_id, 
        controlIds: selectedControls 
      });
    }
  };

  const toggleControl = (controlId: string) => {
    setSelectedControls(prev => 
      prev.includes(controlId) 
        ? prev.filter(id => id !== controlId)
        : [...prev, controlId]
    );
  };

  const toggleAllInModule = (moduleId: string, controls: Control[]) => {
    const controlIds = controls.map(c => c.control_id);
    const allSelected = controlIds.every(id => selectedControls.includes(id));
    
    if (allSelected) {
      setSelectedControls(prev => prev.filter(id => !controlIds.includes(id)));
    } else {
      setSelectedControls(prev => [...prev, ...controlIds.filter(id => !prev.includes(id))]);
    }
  };

  const isModuleFullySelected = (module: Module) => {
    return module.active_controls.every(control => selectedControls.includes(control.control_id));
  };

  const isModulePartiallySelected = (module: Module) => {
    const selectedCount = module.active_controls.filter(control => selectedControls.includes(control.control_id)).length;
    return selectedCount > 0 && selectedCount < module.active_controls.length;
  };

  if (!hasPermission('view_roles')) {
    return (
      <Alert color="red" title="Access Denied">
        You don't have permission to view roles.
      </Alert>
    );
  }

  return (
    <Stack gap="lg">
      <Group justify="space-between">
        <div>
          <Title order={2}>Roles Management</Title>
          <Text c="dimmed" size="sm">Manage user roles and their permissions</Text>
        </div>
        <Group>
          <Button 
            variant="light"
            leftSection={<IconRefresh size={16} />}
            onClick={() => {
              fetchRoles();
              fetchModules();
            }}
          >
            Refresh
          </Button>
          {hasPermission('add_role') && (
            <Button 
              leftSection={<IconPlus size={16} />}
              onClick={() => {
                setEditingRole(null);
                form.reset();
                setModalOpen(true);
              }}
            >
              Create Role
            </Button>
          )}
        </Group>
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder>
        {rolesLoading ? (
          <LoadingOverlay visible={true} />
        ) : (
          <Table striped highlightOnHover>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Role Name</Table.Th>
                <Table.Th>Slug</Table.Th>
                <Table.Th>Description</Table.Th>
                <Table.Th>Permissions</Table.Th>
                <Table.Th ta="center">Actions</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {roles.length === 0 ? (
                <Table.Tr>
                  <Table.Td colSpan={5} ta="center">
                    <Text c="dimmed">No roles found. Create your first role!</Text>
                  </Table.Td>
                </Table.Tr>
              ) : (
                roles.map((role: Role) => (
                  <Table.Tr key={role.role_id}>
                    <Table.Td fw={500}>{role.name}</Table.Td>
                    <Table.Td>
                      <Badge variant="light" size="sm">{role.slug}</Badge>
                    </Table.Td>
                    <Table.Td>{role.description || '-'}</Table.Td>
                    <Table.Td>
                      <Pill size="sm">{role.controls?.length || 0} permissions</Pill>
                    </Table.Td>
                    <Table.Td ta="center">
                      <Group gap="xs" justify="center">
                        {hasPermission('edit_role') && (
                          <ActionIcon color="blue" onClick={() => handleEdit(role)}>
                            <IconEdit size={16} />
                          </ActionIcon>
                        )}
                        {hasPermission('edit_role') && (
                          <ActionIcon color="green" onClick={() => handlePermissions(role)}>
                            <IconLock size={16} />
                          </ActionIcon>
                        )}
                        {hasPermission('delete_role') && role.slug !== 'super_admin' && (
                          <ActionIcon color="red" onClick={() => handleDelete(role.role_id, role.name)}>
                            <IconTrash size={16} />
                          </ActionIcon>
                        )}
                      </Group>
                    </Table.Td>
                  </Table.Tr>
                ))
              )}
            </Table.Tbody>
          </Table>
        )}
      </Card>

      {/* Role Form Modal */}
      <Modal
        opened={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingRole ? 'Edit Role' : 'Create Role'}
        centered
      >
        <form onSubmit={form.onSubmit((values) => saveRole(values))}>
          <Stack>
            <TextInput
              label="Role Name"
              placeholder="e.g., Content Manager"
              required
              {...form.getInputProps('name')}
            />
            <Textarea
              label="Description"
              placeholder="Describe the role's purpose"
              {...form.getInputProps('description')}
            />
            <Group justify="flex-end">
              <Button variant="default" onClick={() => setModalOpen(false)}>Cancel</Button>
              <Button type="submit" loading={saving}>Save</Button>
            </Group>
          </Stack>
        </form>
      </Modal>

      {/* Permissions Modal */}
      <Modal
        opened={permissionsModalOpen}
        onClose={() => setPermissionsModalOpen(false)}
        title={`Permissions for ${selectedRole?.name}`}
        size="xl"
        scrollAreaComponent={Modal.NativeScrollArea}
      >
        <Stack>
          <Text size="sm" c="dimmed">
            Select the permissions to assign to this role. These determine what users with this role can access.
          </Text>
          
          {loadingControls ? (
            <LoadingOverlay visible={true} />
          ) : (
            <div style={{ maxHeight: '500px', overflowY: 'auto', padding: '4px' }}>
              {allModules.map((module) => (
                <Card key={module.module_id} withBorder mb="md" p="md">
                  <Group justify="space-between" mb="sm">
                    <Title order={4}>{module.name}</Title>
                    <Checkbox
                      checked={isModuleFullySelected(module)}
                      indeterminate={isModulePartiallySelected(module)}
                      onChange={() => toggleAllInModule(module.module_id, module.active_controls)}
                      label="Select All"
                    />
                  </Group>
                  <Divider mb="sm" />
                  <Stack gap="xs">
                    {module.active_controls.map((control) => (
                      <Checkbox
                        key={control.control_id}
                        label={control.name}
                        description={control.description}
                        checked={selectedControls.includes(control.control_id)}
                        onChange={() => toggleControl(control.control_id)}
                        size="sm"
                      />
                    ))}
                  </Stack>
                </Card>
              ))}
              
              {allModules.length === 0 && (
                <Alert color="yellow" title="No Modules Found">
                  No modules or permissions have been configured yet. Please run the module seeder first.
                </Alert>
              )}
            </div>
          )}
          
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={() => setPermissionsModalOpen(false)}>Cancel</Button>
            <Button 
              onClick={handleSavePermissions} 
              loading={assigning}
              disabled={loadingControls}
            >
              Save Permissions
            </Button>
          </Group>
        </Stack>
      </Modal>
    </Stack>
  );
};