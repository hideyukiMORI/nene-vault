import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { authStore } from '@/entities/auth';
import { DocumentDetailPage } from './DocumentDetailPage';

// jsdom does not implement object URLs; the download handler needs them.
// Add just the two methods — replacing the URL global would break fetch/MSW.
URL.createObjectURL = vi.fn(() => 'blob:mock');
URL.revokeObjectURL = vi.fn();

describe('DocumentDetailPage download', () => {
  it('downloads through the authenticated client using the version ULID (#179)', async () => {
    authStore.setSession({
      token: 'test-jwt-token',
      userId: 1,
      email: 'admin@example.com',
      role: 'admin',
      orgId: 1,
    });

    let seenVersionId: string | undefined;
    let mirrorHeader: string | null = null;
    server.use(
      http.get('/admin/vault/documents/:id/versions/:versionId/download', ({ params, request }) => {
        seenVersionId = params['versionId'] as string;
        mirrorHeader = request.headers.get('X-Authorization');
        return new HttpResponse('pdf-bytes', {
          status: 200,
          headers: { 'Content-Type': 'application/pdf' },
        });
      }),
    );

    renderWithProviders(
      <MemoryRouter initialEntries={[`/documents/${DOCUMENT_ID}`]}>
        <Routes>
          <Route path="/documents/:id" element={<DocumentDetailPage />} />
        </Routes>
      </MemoryRouter>,
    );

    // The button stays disabled until the history response resolves the
    // current version's ULID (the detail payload only has the ordinal number).
    const button = await screen.findByRole('button', { name: 'Download' });
    await waitFor(() => {
      expect(button).toBeEnabled();
    });

    await userEvent.click(button);

    await waitFor(() => {
      // Version ULID from the history response — not the ordinal '1' the old
      // <a href> used (which 404'd even with credentials).
      expect(seenVersionId).toBe('ver-01J0000000000000000000001');
    });
    // The request went through the shared client (a plain link sends no
    // headers): the shared-hosting proxy mirror (#118) must be present.
    expect(mirrorHeader).toBe('Bearer test-jwt-token');
  });
});
