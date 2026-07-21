import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { server } from '@tests/msw/server';
import { authStore } from '@/shared/api/auth-session';
import { UsersPage } from './UsersPage';

// jsdom resolves to the 'en' catalog (navigator.language = 'en-US').
// The seeded list has one user: id 42, member@example.com.

function signInAs(userId: number) {
  authStore.setSession({
    token: 'test-jwt-token',
    userId,
    email: 'admin@example.com',
    role: 'admin',
    orgId: 1,
  });
}

function renderPage() {
  return renderWithProviders(
    <MemoryRouter>
      <UsersPage />
    </MemoryRouter>,
  );
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('UsersPage', () => {
  it('renders the user list', async () => {
    signInAs(1);
    renderPage();

    expect(await screen.findByRole('heading', { level: 1, name: 'Users' })).toBeInTheDocument();
    expect(await screen.findByText('member@example.com')).toBeInTheDocument();
    expect(screen.getByRole('table')).toBeInTheDocument();
  });

  it('hides the delete action on the signed-in user’s own row (self-delete guard)', async () => {
    signInAs(42); // same id as the seeded user
    renderPage();

    await screen.findByText('member@example.com');
    expect(screen.queryByRole('button', { name: 'Delete' })).not.toBeInTheDocument();
  });

  it('deletes another user after confirmation', async () => {
    signInAs(1); // different id → the row is deletable
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    let deletedId: string | undefined;
    server.use(
      http.delete('/admin/users/:id', ({ params }) => {
        deletedId = params['id'] as string;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderPage();
    await screen.findByText('member@example.com');

    await userEvent.click(screen.getByRole('button', { name: 'Delete' }));

    await waitFor(() => {
      expect(deletedId).toBe('42');
    });
  });

  it('does not delete when the confirmation is dismissed', async () => {
    signInAs(1);
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    let deleteCalled = false;
    server.use(
      http.delete('/admin/users/:id', () => {
        deleteCalled = true;
        return new HttpResponse(null, { status: 204 });
      }),
    );

    renderPage();
    await screen.findByText('member@example.com');

    await userEvent.click(screen.getByRole('button', { name: 'Delete' }));

    expect(deleteCalled).toBe(false);
  });

  it('opens the invite modal', async () => {
    signInAs(1);
    renderPage();

    await screen.findByText('member@example.com');
    await userEvent.click(screen.getByRole('button', { name: 'Invite User' }));

    const dialog = await screen.findByRole('dialog');
    // The submit button ('Invite') distinguishes the modal from the page button.
    expect(within(dialog).getByRole('button', { name: 'Invite' })).toBeInTheDocument();
  });

  it('shows an error message when the list request fails', async () => {
    signInAs(1);
    server.use(http.get('/admin/users', () => HttpResponse.json({}, { status: 500 })));

    renderPage();

    expect(await screen.findByText('An error occurred')).toBeInTheDocument();
  });
});
