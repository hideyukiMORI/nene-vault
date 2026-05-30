import { createBrowserRouter } from 'react-router-dom';
import { LoginPage } from '@/pages/LoginPage';
import { HomePage } from '@/pages/HomePage';
import { DocumentsPage } from '@/pages/DocumentsPage';
import { DocumentDetailPage } from '@/pages/DocumentDetailPage';
import { AuditPage } from '@/pages/AuditPage';
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
  {
    path: '/documents',
    element: (
      <AuthGate>
        <DocumentsPage />
      </AuthGate>
    ),
  },
  {
    path: '/documents/:id',
    element: (
      <AuthGate>
        <DocumentDetailPage />
      </AuthGate>
    ),
  },
  {
    path: '/audit',
    element: (
      <AuthGate>
        <AuditPage />
      </AuthGate>
    ),
  },
]);
