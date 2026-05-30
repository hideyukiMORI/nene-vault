import { createBrowserRouter } from 'react-router-dom';
import { LoginPage } from '@/pages/LoginPage';
import { HomePage } from '@/pages/HomePage';
import { DocumentsPage } from '@/pages/DocumentsPage';
import { DocumentDetailPage } from '@/pages/DocumentDetailPage';
import { AuditPage } from '@/pages/AuditPage';
import { SettingsPage } from '@/pages/SettingsPage';
import { UsersPage } from '@/pages/UsersPage';
import { ExportPage } from '@/pages/ExportPage';
import { ForbiddenPage } from '@/pages/ForbiddenPage';
import { AuthGate } from './auth-gate';

function guarded(element: React.ReactElement) {
  return <AuthGate>{element}</AuthGate>;
}

export const router = createBrowserRouter(
  [
    { path: '/login', element: <LoginPage /> },
    { path: '/forbidden', element: <ForbiddenPage /> },
    { path: '/', element: guarded(<HomePage />) },
    { path: '/documents', element: guarded(<DocumentsPage />) },
    { path: '/documents/:id', element: guarded(<DocumentDetailPage />) },
    { path: '/audit', element: guarded(<AuditPage />) },
    { path: '/settings', element: guarded(<SettingsPage />) },
    { path: '/users', element: guarded(<UsersPage />) },
    { path: '/export', element: guarded(<ExportPage />) },
  ],
  {
    future: {
      v7_relativeSplatPath: true,
    },
  },
);
