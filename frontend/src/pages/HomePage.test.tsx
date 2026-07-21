import { screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it } from 'vitest';
import { authStore } from '@/shared/api/auth-session';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { HomePage } from './HomePage';

const baseSession = {
  token: 'test-jwt',
  userId: 1,
  email: 'user@example.com',
  orgId: 1,
};

afterEach(() => {
  sessionStorage.clear();
});

function renderHome() {
  return renderWithProviders(
    <MemoryRouter initialEntries={['/']}>
      <HomePage />
    </MemoryRouter>,
  );
}

describe('HomePage quick-access cards (#182 — role-gated like the rail)', () => {
  it('shows a viewer only the documents card, not admin-only actions', () => {
    authStore.setSession({ ...baseSession, role: 'viewer' });
    renderHome();

    // Quick-access cards are buttons carrying the `.qlink` class (distinct from
    // the rail's `.rail-link`); count them among the rendered buttons.
    const cards = screen.getAllByRole('button').filter((b) => b.classList.contains('qlink'));
    expect(cards).toHaveLength(1);
    // Admin-only card labels appear nowhere on a viewer's home (rail is gated too).
    expect(screen.queryByText('監査ログ')).toBeNull();
    expect(screen.queryByText('保管設定')).toBeNull();
    expect(screen.queryByText('エクスポート')).toBeNull();
  });

  it('shows an admin all four quick-access cards', () => {
    authStore.setSession({ ...baseSession, role: 'admin' });
    renderHome();

    const cards = screen.getAllByRole('button').filter((b) => b.classList.contains('qlink'));
    expect(cards).toHaveLength(4);
  });
});
