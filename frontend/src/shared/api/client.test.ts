import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { server } from '@tests/msw/server';
import { problemDetails } from '@tests/msw/fixtures';
import { authStore } from '@/entities/auth';
import { apiClient } from './client';
import { AppError } from './errors';

function signIn(): void {
  authStore.setSession({
    token: 'test-jwt-token',
    userId: 1,
    email: 'admin@example.com',
    role: 'admin',
    orgId: 1,
  });
}

describe('apiClient (nene2-client transport adapter)', () => {
  it('mirrors the bearer token onto both Authorization and X-Authorization on GET/POST/PATCH/DELETE/upload', async () => {
    signIn();

    const seen: Record<string, { auth: string | null; xAuth: string | null }> = {};
    function record(name: string) {
      return ({ request }: { request: Request }) => {
        seen[name] = {
          auth: request.headers.get('Authorization'),
          xAuth: request.headers.get('X-Authorization'),
        };
        return HttpResponse.json({ ok: true });
      };
    }

    server.use(
      http.get('/admin/vault/documents', record('get')),
      http.post('/admin/vault/documents', record('post')),
      http.patch('/admin/vault/documents/:id/metadata', record('patch')),
      http.delete('/admin/vault/documents/:id', record('delete')),
      http.post('/admin/vault/documents/upload', record('upload')),
    );

    await apiClient.get('/admin/vault/documents');
    await apiClient.post('/admin/vault/documents', { name: 'x' });
    await apiClient.patch('/admin/vault/documents/doc-1/metadata', { counterparty_name: 'y' });
    await apiClient.delete('/admin/vault/documents/doc-1');
    await apiClient.upload('/admin/vault/documents/upload', new FormData());

    for (const method of ['get', 'post', 'patch', 'delete', 'upload']) {
      expect(seen[method]?.auth, `${method} Authorization`).toBe('Bearer test-jwt-token');
      // The proxy-stripping workaround (#118): the mirror must be present on
      // every verb so requests keep working behind the shared-hosting front
      // proxy that strips the standard Authorization header.
      expect(seen[method]?.xAuth, `${method} X-Authorization`).toBe('Bearer test-jwt-token');
    }
  });

  it('sends no auth headers when signed out', async () => {
    authStore.clearSession();
    let authorization: string | null = null;

    server.use(
      http.get('/admin/vault/documents', ({ request }) => {
        authorization = request.headers.get('Authorization');
        return HttpResponse.json({ items: [], limit: 20, offset: 0, total: 0 });
      }),
    );

    await apiClient.get('/admin/vault/documents');

    expect(authorization).toBeNull();
  });

  it('clears the session store on a 401 from an authenticated request (#168)', async () => {
    signIn();
    server.use(
      http.get('/admin/vault/documents', () =>
        HttpResponse.json(problemDetails('unauthorized', 401, 'Unauthorized'), {
          status: 401,
          headers: { 'Content-Type': 'application/problem+json' },
        }),
      ),
    );

    await expect(apiClient.get('/admin/vault/documents')).rejects.toBeInstanceOf(AppError);

    // The reactive auth gate re-renders the login form in place once the
    // token store reports signed-out — no hard navigation (#168).
    expect(authStore.getToken()).toBeNull();
  });

  it('maps a Problem Details error response to AppError with the same shape as before', async () => {
    signIn();
    server.use(
      http.get('/admin/vault/documents', () =>
        HttpResponse.json(problemDetails('internal-server-error', 500, 'Server Error'), {
          status: 500,
          headers: { 'Content-Type': 'application/problem+json' },
        }),
      ),
    );

    await expect(apiClient.get('/admin/vault/documents')).rejects.toMatchObject({
      status: 500,
      problem: {
        type: 'https://nene-vault.dev/problems/internal-server-error',
        title: 'Server Error',
      },
    });
    await expect(apiClient.get('/admin/vault/documents')).rejects.toBeInstanceOf(AppError);
  });
});

describe('apiClient.postBlob', () => {
  it('sends both Authorization and the X-Authorization mirror (#118)', async () => {
    signIn();

    let authHeader: string | null = null;
    let mirrorHeader: string | null = null;
    server.use(
      http.post('/admin/vault/export', ({ request }) => {
        authHeader = request.headers.get('Authorization');
        mirrorHeader = request.headers.get('X-Authorization');
        return new HttpResponse('col1,col2\n', {
          status: 200,
          headers: {
            'Content-Type': 'text/csv',
            'Content-Disposition': 'attachment; filename="vault-export.csv"',
          },
        });
      }),
    );

    const { blob, filename } = await apiClient.postBlob('/admin/vault/export', { format: 'csv' });

    expect(authHeader).toBe('Bearer test-jwt-token');
    // The proxy-stripping workaround: the mirror must be present so exports keep
    // working behind the shared-hosting front proxy that strips Authorization.
    expect(mirrorHeader).toBe('Bearer test-jwt-token');
    expect(filename).toBe('vault-export.csv');
    expect(await blob.text()).toBe('col1,col2\n');
  });

  it('rejects with an AppError carrying the status on an auth failure', async () => {
    signIn();
    server.use(
      http.post('/admin/vault/export', () =>
        HttpResponse.json(problemDetails('unauthorized', 401, 'Unauthorized'), {
          status: 401,
          headers: { 'Content-Type': 'application/problem+json' },
        }),
      ),
    );

    await expect(apiClient.postBlob('/admin/vault/export', {})).rejects.toMatchObject({
      status: 401,
    });
  });
});
