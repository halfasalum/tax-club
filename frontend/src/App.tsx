// src/App.tsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { MantineProvider } from '@mantine/core';
import { Notifications } from '@mantine/notifications';
import { theme } from './theme';
import { SignIn } from './pages/auth/SignIn';
import { ForgotPassword } from './pages/auth/ForgotPassword';
import { ResetPassword } from './pages/auth/ResetPassword';
import { Dashboard } from './pages/dashboard/Dashboard';
import { UsersList } from './pages/users/UsersList';
import { RolesList } from './pages/roles/RolesList';
import { MainLayout } from './layouts/MainLayout';
import { ProtectedRoute } from './components/ProtectedRoute';
import '@mantine/core/styles.css';
import '@mantine/notifications/styles.css';
import { ModulesList } from './pages/modules/ModulesList';
import { InstitutionsList } from './pages/institutions/InstitutionsList';

function App() {
  return (
    <MantineProvider theme={theme} defaultColorScheme="auto">
      <Notifications />
      <BrowserRouter>
        <Routes>
          {/* Auth Routes */}
          <Route path="/auth">
            <Route path="signin" element={<SignIn />} />
            <Route path="forgot-password" element={<ForgotPassword />} />
            <Route path="reset-password/:token" element={<ResetPassword />} />
          </Route>

          {/* Protected Routes with MainLayout */}
          <Route
            element={
              <ProtectedRoute>
                <MainLayout />
              </ProtectedRoute>
            }
          >
            <Route path="modules" element={<ModulesList />} />
            <Route path="modules/create" element={<ModulesList />} />
            <Route path="institutions" element={<InstitutionsList />} />
            <Route index element={<Navigate to="/dashboard" replace />} />
            <Route path="dashboard" element={<Dashboard />} />
            <Route path="users" element={<UsersList />} />
            <Route path="users/create" element={<div>Create User Page</div>} />
            <Route path="users/:id/edit" element={<div>Edit User Page</div>} />
            <Route path="users/:id/roles" element={<div>Manage User Roles Page</div>} />
            <Route path="roles" element={<RolesList />} />
            <Route path="modules" element={<div>Modules Page</div>} />
            <Route path="modules/create" element={<div>Create Module Page</div>} />
            <Route path="institutions" element={<div>Institutions Page</div>} />
            <Route path="content" element={<div>Content Page</div>} />
            <Route path="content/create" element={<div>Create Content Page</div>} />
            <Route path="quizzes" element={<div>Quizzes Page</div>} />
            <Route path="quizzes/create" element={<div>Create Quiz Page</div>} />
            <Route path="quizzes/results" element={<div>Quiz Results Page</div>} />
            <Route path="opportunities" element={<div>Opportunities Page</div>} />
            <Route path="opportunities/create" element={<div>Create Opportunity Page</div>} />
            <Route path="forum" element={<div>Forum Page</div>} />
            <Route path="events" element={<div>Events Page</div>} />
            <Route path="events/create" element={<div>Create Event Page</div>} />
            <Route path="events/attendees" element={<div>Event Attendees Page</div>} />
            <Route path="profile" element={<div>Profile Page</div>} />
            <Route path="settings" element={<div>Settings Page</div>} />
          </Route>
        </Routes>
      </BrowserRouter>
    </MantineProvider>
  );
}

export default App;