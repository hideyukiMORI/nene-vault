import { createBrowserRouter } from 'react-router-dom';
import { LoginPage } from '@/pages/LoginPage';
import { HomePage } from '@/pages/HomePage';
import { ForbiddenPage } from '@/pages/ForbiddenPage';
import { AuthGate } from './auth-gate';

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/forbidden', element: <ForbiddenPage /> },
  {
    path: '/',
    element: (
      <AuthGate>
        <HomePage />
      </AuthGate>
    ),
  },
]);
