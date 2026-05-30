import { http, HttpResponse } from 'msw';
import {
  mockDocument,
  mockVoidedDocument,
  mockDocumentList,
  mockAuditEventList,
  mockDocumentHistory,
  mockVaultSettings,
  mockUserList,
  mockUser,
  DOCUMENT_ID,
  problemDetails,
} from './fixtures';

// MSW handlers mirror the OpenAPI contract shapes.
// All paths are relative (no host) because apiBaseUrl is '' in test env.
export const handlers = [
  // ── Auth ────────────────────────────────────────────────────────────────────

  http.post('/admin/auth/login', async ({ request }) => {
    const body = (await request.json()) as { email: string; password: string };

    if (body.email === 'admin@example.com' && body.password === 'secret') {
      return HttpResponse.json({
        token: 'test-jwt-token',
        expires_at: '2026-06-01T00:00:00Z',
        user_id: 1,
        email: 'admin@example.com',
        role: 'admin',
        org_id: 1,
      });
    }

    return HttpResponse.json(problemDetails('invalid-credentials', 401, 'Invalid Credentials'), {
      status: 401,
      headers: { 'Content-Type': 'application/problem+json' },
    });
  }),

  // ── Documents ────────────────────────────────────────────────────────────────

  http.get('/admin/vault/documents', () => {
    return HttpResponse.json(mockDocumentList);
  }),

  http.get('/admin/vault/documents/:id', ({ params }) => {
    const { id } = params;
    if (id === DOCUMENT_ID) {
      return HttpResponse.json(mockDocument);
    }
    return HttpResponse.json(problemDetails('document-not-found', 404, 'Document Not Found'), {
      status: 404,
      headers: { 'Content-Type': 'application/problem+json' },
    });
  }),

  http.post('/admin/vault/documents', () => {
    return HttpResponse.json(mockDocument, { status: 201 });
  }),

  http.patch('/admin/vault/documents/:id/metadata', ({ params }) => {
    const { id } = params;
    if (id === DOCUMENT_ID) {
      return HttpResponse.json({ ...mockDocument, counterparty_name: 'Updated Inc.' });
    }
    return HttpResponse.json(problemDetails('document-not-found', 404, 'Document Not Found'), {
      status: 404,
      headers: { 'Content-Type': 'application/problem+json' },
    });
  }),

  http.post('/admin/vault/documents/:id/void', async ({ params, request }) => {
    const { id } = params;
    const body = (await request.json()) as { void_reason?: string };
    if (!body.void_reason) {
      return HttpResponse.json(problemDetails('validation-failed', 422, 'Validation Failed'), {
        status: 422,
        headers: { 'Content-Type': 'application/problem+json' },
      });
    }
    if (id === DOCUMENT_ID) {
      return HttpResponse.json(mockVoidedDocument);
    }
    return HttpResponse.json(problemDetails('document-not-found', 404, 'Document Not Found'), {
      status: 404,
      headers: { 'Content-Type': 'application/problem+json' },
    });
  }),

  http.post('/admin/vault/documents/:id/restore', ({ params }) => {
    const { id } = params;
    if (id === DOCUMENT_ID) {
      return HttpResponse.json(mockDocument);
    }
    return HttpResponse.json(problemDetails('document-not-found', 404, 'Document Not Found'), {
      status: 404,
      headers: { 'Content-Type': 'application/problem+json' },
    });
  }),

  http.get('/admin/vault/documents/:id/history', ({ params }) => {
    const { id } = params;
    if (id === DOCUMENT_ID) {
      return HttpResponse.json(mockDocumentHistory);
    }
    return HttpResponse.json(problemDetails('document-not-found', 404, 'Document Not Found'), {
      status: 404,
      headers: { 'Content-Type': 'application/problem+json' },
    });
  }),

  // ── Audit events ──────────────────────────────────────────────────────────────

  http.get('/admin/audit-events', () => {
    return HttpResponse.json(mockAuditEventList);
  }),

  // ── Vault settings ────────────────────────────────────────────────────────────

  http.get('/admin/vault/settings', () => {
    return HttpResponse.json(mockVaultSettings);
  }),

  http.patch('/admin/vault/settings', async ({ request }) => {
    const body = (await request.json()) as Record<string, unknown>;
    return HttpResponse.json({ ...mockVaultSettings, ...body });
  }),

  // ── Users ────────────────────────────────────────────────────────────────────

  http.get('/admin/users', () => {
    return HttpResponse.json(mockUserList);
  }),

  http.post('/admin/users', async ({ request }) => {
    const body = (await request.json()) as { email: string; role: string };
    return HttpResponse.json({ ...mockUser, email: body.email, role: body.role }, { status: 201 });
  }),

  http.delete('/admin/users/:id', () => {
    return new HttpResponse(null, { status: 204 });
  }),
];
