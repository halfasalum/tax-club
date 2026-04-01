// src/App.tsx
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { MantineProvider } from '@mantine/core';
import { Notifications } from '@mantine/notifications';
import { theme } from './theme';
import { SignIn } from './pages/auth/SignIn';
import { ForgotPassword } from './pages/auth/ForgotPassword';
import { ResetPassword } from './pages/auth/ResetPassword';
import { Dashboard } from './pages/dashboard/Dashboard';
import { MainLayout } from './layouts/MainLayout';

// Protected Route Component
const ProtectedRoute = ({ children }: { children: React.ReactNode }) => {
  const isAuthenticated = localStorage.getItem('auth_token'); // Replace with your auth logic
  
  if (!isAuthenticated) {
    return <Navigate to="/auth/signin" replace />;
  }
  
  return children;
};

function App() {
  return (
    <MantineProvider theme={theme} defaultColorScheme="auto">
      <Notifications />
      <BrowserRouter>
        <Routes>
          {/* Auth Routes - No Layout */}
          <Route path="/auth">
            <Route path="signin" element={<SignIn />} />
            <Route path="forgot-password" element={<ForgotPassword />} />
            <Route path="reset-password/:token" element={<ResetPassword />} />
          </Route>
          
          {/* Protected Routes with Main Layout */}
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <MainLayout>
                  <Navigate to="/dashboard" replace />
                </MainLayout>
              </ProtectedRoute>
            }
          />
          
          <Route
            path="/dashboard"
            element={
              <ProtectedRoute>
                <MainLayout activePath="/dashboard">
                  <Dashboard />
                </MainLayout>
              </ProtectedRoute>
            }
          />
          
          {/* Add more routes as needed */}
        </Routes>
      </BrowserRouter>
    </MantineProvider>
  );
}

export default App;