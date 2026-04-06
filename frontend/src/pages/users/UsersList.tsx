// src/pages/users/UsersList.tsx
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
  TextInput,
  Modal,
  Select,
  Avatar,
  Menu,
  Loader,
  Center,
  Pagination,
  Alert
} from '@mantine/core';
import { 
  IconPlus, 
  IconEdit, 
  IconTrash, 
  IconSearch, 
  IconDotsVertical,
  IconUserPlus,
  IconShield,
  IconMail,
  IconPhone,
  IconCalendar
} from '@tabler/icons-react';
import { useAuth } from '../../hooks/useAuth';
import { userService,type  User } from '../../api/services/userService';
import { useApi } from '../../hooks/useApi';
import { Link } from 'react-router-dom';
import { notifications } from '@mantine/notifications';

export const UsersList = () => {
  const { hasPermission, user: currentUser } = useAuth();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  const { data, loading, execute: fetchUsers } = useApi(
    () => userService.getUsers({ page, search: search || undefined }),
    { showErrorNotification: true }
  );

  const { execute: deleteUser, loading: deleting } = useApi(
    (userId: string) => userService.deleteUser(userId),
    {
      showSuccessNotification: true,
      successMessage: 'User deleted successfully',
      onSuccess: () => {
        setDeleteModalOpen(false);
        fetchUsers();
      },
    }
  );

  useEffect(() => {
    fetchUsers();
  }, [page, search]);

  const handleDelete = async () => {
    if (selectedUser) {
      await deleteUser(selectedUser.user_id);
    }
  };

  const users = data?.data || [];
  const totalPages = data?.last_page || 1;

  if (!hasPermission('view_users')) {
    return (
      <Alert color="red" title="Access Denied">
        You don't have permission to view users.
      </Alert>
    );
  }

  return (
    <Stack gap="lg">
      <Group justify="space-between">
        <div>
          <Title order={2}>Users Management</Title>
          <Text c="dimmed" size="sm">Manage system users and their roles</Text>
        </div>
        {hasPermission('add_user') && (
          <Button component={Link} to="/users/create" leftSection={<IconPlus size={16} />}>
            Add User
          </Button>
        )}
      </Group>

      <Card shadow="sm" padding="lg" radius="md" withBorder>
        <Group justify="space-between" mb="md">
          <TextInput
            placeholder="Search by name or email..."
            leftSection={<IconSearch size={16} />}
            value={search}
            onChange={(e) => setSearch(e.currentTarget.value)}
            style={{ flex: 1 }}
          />
        </Group>

        {loading ? (
          <Center py="xl">
            <Loader />
          </Center>
        ) : (
          <>
            <Table striped highlightOnHover>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th>User</Table.Th>
                  <Table.Th>Email</Table.Th>
                  <Table.Th>Membership ID</Table.Th>
                  <Table.Th>Type</Table.Th>
                  <Table.Th>Status</Table.Th>
                  <Table.Th>Last Login</Table.Th>
                  <Table.Th ta="center">Actions</Table.Th>
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {users.map((user) => (
                  <Table.Tr key={user.user_id}>
                    <Table.Td>
                      <Group gap="sm">
                        <Avatar radius="xl" color="blue">
                          {user.full_name.charAt(0)}
                        </Avatar>
                        <div>
                          <Text size="sm" fw={500}>{user.full_name}</Text>
                          <Text size="xs" c="dimmed">ID: {user.user_id.slice(0, 8)}</Text>
                        </div>
                      </Group>
                    </Table.Td>
                    <Table.Td>
                      <Group gap="xs">
                        <IconMail size={14} />
                        <Text size="sm">{user.email}</Text>
                      </Group>
                      {user.phone && (
                        <Group gap="xs" mt={4}>
                          <IconPhone size={12} />
                          <Text size="xs" c="dimmed">{user.phone}</Text>
                        </Group>
                      )}
                    </Table.Td>
                    <Table.Td>
                      <Badge variant="light" size="sm">{user.membership_id}</Badge>
                    </Table.Td>
                    <Table.Td>
                      <Badge color="teal" size="sm">{user.user_type}</Badge>
                    </Table.Td>
                    <Table.Td>
                      <Badge color={user.is_verified ? 'green' : 'yellow'}>
                        {user.is_verified ? 'Verified' : 'Pending'}
                      </Badge>
                    </Table.Td>
                    <Table.Td>
                      <Group gap="xs">
                        <IconCalendar size={14} />
                        <Text size="xs">
                          {user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}
                        </Text>
                      </Group>
                    </Table.Td>
                    <Table.Td ta="center">
                      <Menu position="bottom-end">
                        <Menu.Target>
                          <ActionIcon variant="subtle">
                            <IconDotsVertical size={16} />
                          </ActionIcon>
                        </Menu.Target>
                        <Menu.Dropdown>
                          {hasPermission('edit_user') && (
                            <Menu.Item 
                              component={Link} 
                              to={`/users/${user.user_id}/edit`}
                              leftSection={<IconEdit size={14} />}
                            >
                              Edit User
                            </Menu.Item>
                          )}
                          {hasPermission('assign_role') && (
                            <Menu.Item 
                              component={Link}
                              to={`/users/${user.user_id}/roles`}
                              leftSection={<IconShield size={14} />}
                            >
                              Manage Roles
                            </Menu.Item>
                          )}
                          {hasPermission('delete_user') && user.user_id !== currentUser?.user_id && (
                            <Menu.Item 
                              color="red"
                              leftSection={<IconTrash size={14} />}
                              onClick={() => {
                                setSelectedUser(user);
                                setDeleteModalOpen(true);
                              }}
                            >
                              Delete User
                            </Menu.Item>
                          )}
                        </Menu.Dropdown>
                      </Menu>
                    </Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>

            {totalPages > 1 && (
              <Group justify="center" mt="md">
                <Pagination total={totalPages} value={page} onChange={setPage} />
              </Group>
            )}
          </>
        )}
      </Card>

      <Modal
        opened={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        title="Delete User"
        centered
      >
        <Stack>
          <Text>Are you sure you want to delete {selectedUser?.full_name}?</Text>
          <Text size="sm" c="dimmed">This action cannot be undone.</Text>
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setDeleteModalOpen(false)}>Cancel</Button>
            <Button color="red" loading={deleting} onClick={handleDelete}>Delete</Button>
          </Group>
        </Stack>
      </Modal>
    </Stack>
  );
};