import { http, HttpResponse } from 'msw';

// MSW handlers mirror the OpenAPI contract shapes.
export const handlers = [
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

    return HttpResponse.json(
      {
        type: 'https://nene-vault.dev/problems/invalid-credentials',
        title: 'Invalid Credentials',
        status: 401,
      },
      { status: 401, headers: { 'Content-Type': 'application/problem+json' } },
    );
  }),
];
