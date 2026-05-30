import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it } from 'vitest';
import { authStore } from '@/entities/auth';
import { AuthGate } from './auth-gate';

afterEach(() => {
  localStorage.clear();
});

describe('AuthGate', () => {
  it('renders children when a session exists', () => {
    authStore.setSession({
      token: 'valid-jwt',
      userId: 1,
      email: 'admin@example.com',
      role: 'admin',
      orgId: 1,
    });

    render(
      <MemoryRouter>
        <AuthGate>
          <div>Protected content</div>
        </AuthGate>
      </MemoryRouter>,
    );

    expect(screen.getByText('Protected content')).toBeInTheDocument();
  });

  it('redirects to /login when no session exists', () => {
    render(
      <MemoryRouter
        initialEntries={['/dashboard']}
        future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
      >
        <AuthGate>
          <div>Protected content</div>
        </AuthGate>
      </MemoryRouter>,
    );

    // Protected content should NOT be rendered
    expect(screen.queryByText('Protected content')).toBeNull();
  });

  it('does not render children when session was cleared', () => {
    authStore.setSession({
      token: 'valid-jwt',
      userId: 1,
      email: 'a@b.com',
      role: 'admin',
      orgId: 1,
    });
    authStore.clearSession();

    render(
      <MemoryRouter>
        <AuthGate>
          <div>Protected content</div>
        </AuthGate>
      </MemoryRouter>,
    );

    expect(screen.queryByText('Protected content')).toBeNull();
  });
});
