import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { server } from '@tests/msw/server';
import { problemDetails } from '@tests/msw/fixtures';
import { authStore } from '@/entities/auth';
import { apiClient } from './client';

function signIn(): void {
  authStore.setSession({
    token: 'test-jwt-token',
    userId: 1,
    email: 'admin@example.com',
    role: 'admin',
    orgId: 1,
  });
}

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
