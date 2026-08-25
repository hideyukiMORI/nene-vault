import { screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { authStore } from '@/shared/api/auth-session';
import { DocumentsPage } from './DocumentsPage';

function signInAs(role: string) {
  authStore.setSession({
    token: 'test-jwt-token',
    userId: 1,
    email: 'someone@example.com',
    role,
    orgId: 1,
  });
}

function renderPage() {
  return renderWithProviders(
    <MemoryRouter>
      <DocumentsPage />
    </MemoryRouter>,
  );
}

/**
 * #451 — measured on production 2026-08-25: a viewer saw "Upload Document", clicked it, and
 * landed on the Forbidden page because the API answers 403. The button now follows the same
 * capability table the rail and HomePage use.
 */
describe('DocumentsPage upload gate (#451)', () => {
  it('shows the Upload button to a role that may upload', async () => {
    signInAs('admin');
    renderPage();
    expect(await screen.findByRole('button', { name: 'Upload Document' })).toBeInTheDocument();
  });

  it('hides the Upload button from a viewer', async () => {
    signInAs('viewer');
    renderPage();
    await screen.findByRole('heading', { name: 'Received Documents' });
    expect(screen.queryByRole('button', { name: 'Upload Document' })).not.toBeInTheDocument();
  });
});
