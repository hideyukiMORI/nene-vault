import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { authStore } from '@/shared/api/auth-session';
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
    //
    // 🔴 Explicit timeout. The default 1000ms is enough for `vitest run` and not always
    // enough for `vitest run --coverage`, which is what `npm run check` and CI actually
    // execute: measured 2026-08-24, 0 failures in 13 plain runs and 2 in 3 coverage runs.
    // The suite was passing on the machine and failing on the gate — the same test, told
    // apart only by how the gate runs it.
    const button = await screen.findByRole('button', { name: 'Download' }, { timeout: 5000 });
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

/** #451 — a viewer must not be offered actions the API refuses (edit / void / restore). */
describe('DocumentDetailPage action gate (#451)', () => {
  function renderAs(role: string) {
    authStore.setSession({
      token: 'test-jwt-token',
      userId: 1,
      email: 'someone@example.com',
      role,
      orgId: 1,
    });
    return renderWithProviders(
      <MemoryRouter initialEntries={[`/documents/${DOCUMENT_ID}`]}>
        <Routes>
          <Route path="/documents/:id" element={<DocumentDetailPage />} />
        </Routes>
      </MemoryRouter>,
    );
  }

  it('offers Edit and Void to an admin', async () => {
    renderAs('admin');
    await screen.findByRole('button', { name: 'Download' }, { timeout: 5000 });
    expect(screen.getByRole('button', { name: 'Edit' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Void' })).toBeInTheDocument();
  });

  it('hides Edit, OCR Suggest and Void from a viewer, keeps Download', async () => {
    renderAs('viewer');
    await screen.findByRole('button', { name: 'Download' }, { timeout: 5000 });
    expect(screen.queryByRole('button', { name: 'Edit' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /OCR Suggest/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Void' })).not.toBeInTheDocument();
  });
});
