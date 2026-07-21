import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { server } from '@tests/msw/server';
import { authStore } from '@/shared/api/auth-session';
import { fetchDocumentBlob } from './file';

describe('fetchDocumentBlob', () => {
  it('requests the version-ULID-keyed path with both auth headers (#179)', async () => {
    authStore.setSession({
      token: 'test-jwt-token',
      userId: 1,
      email: 'admin@example.com',
      role: 'admin',
      orgId: 1,
    });

    let seenVersionId: string | undefined;
    let authHeader: string | null = null;
    let mirrorHeader: string | null = null;
    server.use(
      http.get('/admin/vault/documents/:id/versions/:versionId/download', ({ params, request }) => {
        seenVersionId = params['versionId'] as string;
        authHeader = request.headers.get('Authorization');
        mirrorHeader = request.headers.get('X-Authorization');
        return new HttpResponse('pdf-bytes', {
          status: 200,
          headers: { 'Content-Type': 'application/pdf' },
        });
      }),
    );

    const blob = await fetchDocumentBlob('doc-1', 'ver-01J0000000000000000000001');

    // The route is keyed by the version ULID — not the ordinal version_number.
    expect(seenVersionId).toBe('ver-01J0000000000000000000001');
    // A plain <a href> sends neither header; the shared client must send both
    // (X-Authorization is the shared-hosting proxy mirror, #118).
    expect(authHeader).toBe('Bearer test-jwt-token');
    expect(mirrorHeader).toBe('Bearer test-jwt-token');
    expect(await blob.text()).toBe('pdf-bytes');
  });

  it('rejects with the response status when unauthenticated', async () => {
    server.use(
      http.get('/admin/vault/documents/:id/versions/:versionId/download', () =>
        HttpResponse.json(
          { type: 'about:blank/unauthorized', title: 'Unauthorized', status: 401 },
          { status: 401, headers: { 'Content-Type': 'application/problem+json' } },
        ),
      ),
    );

    await expect(fetchDocumentBlob('doc-1', 'ver-x')).rejects.toMatchObject({ status: 401 });
  });
});
