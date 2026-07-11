import { screen } from '@testing-library/react';
import { act } from 'react';
import { afterEach, describe, expect, it } from 'vitest';
import { authStore } from '@/entities/auth';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { AuthGate } from './auth-gate';

const SESSION = {
  token: 'valid-jwt',
  userId: 1,
  email: 'admin@example.com',
  role: 'admin',
  orgId: 1,
};

afterEach(() => {
  sessionStorage.clear();
});

describe('AuthGate', () => {
  it('renders children when a session exists', () => {
    authStore.setSession(SESSION);

    renderWithProviders(
      <AuthGate>
        <div>Protected content</div>
      </AuthGate>,
    );

    expect(screen.getByText('Protected content')).toBeInTheDocument();
  });

  it('shows the login form in place when no session exists (#168)', () => {
    renderWithProviders(
      <AuthGate>
        <div>Protected content</div>
      </AuthGate>,
    );

    expect(screen.queryByText('Protected content')).toBeNull();
    // The login form renders IN PLACE — no navigation away from the route.
    expect(screen.getByRole('button', { name: 'Log In' })).toBeInTheDocument();
  });

  it('swaps to the login form when the session is cleared reactively', () => {
    authStore.setSession(SESSION);

    renderWithProviders(
      <AuthGate>
        <div>Protected content</div>
      </AuthGate>,
    );
    expect(screen.getByText('Protected content')).toBeInTheDocument();

    // A 401 in the API client clears the session; the gate must react.
    act(() => {
      authStore.clearSession();
    });

    expect(screen.queryByText('Protected content')).toBeNull();
    expect(screen.getByRole('button', { name: 'Log In' })).toBeInTheDocument();
  });

  it('swaps back to children when a session appears (login in place)', () => {
    renderWithProviders(
      <AuthGate>
        <div>Protected content</div>
      </AuthGate>,
    );
    expect(screen.queryByText('Protected content')).toBeNull();

    act(() => {
      authStore.setSession(SESSION);
    });

    expect(screen.getByText('Protected content')).toBeInTheDocument();
  });
});
