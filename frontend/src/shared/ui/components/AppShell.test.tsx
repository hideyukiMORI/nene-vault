import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { AppShell } from './AppShell';

// jsdom resolves to the 'en' catalog (navigator.language = 'en-US').
// Nav visibility mirrors roleHasCapability: admin sees every route; a viewer
// only sees Home + Received Documents (ViewDocuments), never the admin routes.

function LocationProbe() {
  const { pathname } = useLocation();
  return <div>{`path:${pathname}`}</div>;
}

function renderShell(props: {
  role?: string;
  email?: string;
  onLogout?: () => void;
  initialPath?: string;
}) {
  return renderWithProviders(
    <MemoryRouter initialEntries={[props.initialPath ?? '/']}>
      <AppShell
        onLogout={props.onLogout ?? (() => undefined)}
        userEmail={props.email}
        userRole={props.role}
      >
        <LocationProbe />
      </AppShell>
    </MemoryRouter>,
  );
}

describe('AppShell navigation gating', () => {
  it('shows every route to an admin', () => {
    renderShell({ role: 'admin', email: 'admin@example.com' });

    for (const name of [
      'Home',
      'Received Documents',
      'Audit Log',
      'Vault Settings',
      'Users',
      'Export',
    ]) {
      expect(screen.getByRole('button', { name })).toBeInTheDocument();
    }
  });

  it('hides admin-only routes from a viewer (capability gating)', () => {
    renderShell({ role: 'viewer', email: 'viewer@example.com' });

    // A viewer keeps Home and Received Documents (ViewDocuments)…
    expect(screen.getByRole('button', { name: 'Home' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Received Documents' })).toBeInTheDocument();
    // …but the admin routes are gone (never dead-end a viewer on Forbidden, #174).
    for (const name of ['Audit Log', 'Vault Settings', 'Users', 'Export']) {
      expect(screen.queryByRole('button', { name })).not.toBeInTheDocument();
    }
  });

  it('treats an unknown role as having no admin capabilities (fail-closed)', () => {
    renderShell({ role: 'nonsense', email: 'x@example.com' });

    expect(screen.getByRole('button', { name: 'Home' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Users' })).not.toBeInTheDocument();
  });
});

describe('AppShell interactions', () => {
  it('navigates when a rail link is activated', async () => {
    renderShell({ role: 'admin', email: 'admin@example.com' });
    expect(screen.getByText('path:/')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Users' }));

    expect(screen.getByText('path:/users')).toBeInTheDocument();
  });

  it('calls onLogout when the log-out control is activated', async () => {
    const onLogout = vi.fn();
    renderShell({ role: 'admin', email: 'admin@example.com', onLogout });

    await userEvent.click(screen.getByRole('button', { name: 'Log Out' }));

    expect(onLogout).toHaveBeenCalledTimes(1);
  });

  it('shows the signed-in user’s email in the rail footer', () => {
    renderShell({ role: 'admin', email: 'admin@example.com' });

    expect(screen.getByText('admin@example.com')).toBeInTheDocument();
  });
});
