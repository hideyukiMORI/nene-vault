import { screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it } from 'vitest';
import { authStore } from '@/entities/auth';
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
    const { container } = renderHome();

    // Quick-access cards use the `.qlink` class (distinct from `.rail-link`).
    expect(container.querySelectorAll('.qlink')).toHaveLength(1);
    // Admin-only card labels appear nowhere on a viewer's home (rail is gated too).
    expect(screen.queryByText('監査ログ')).toBeNull();
    expect(screen.queryByText('保管設定')).toBeNull();
    expect(screen.queryByText('エクスポート')).toBeNull();
  });

  it('shows an admin all four quick-access cards', () => {
    authStore.setSession({ ...baseSession, role: 'admin' });
    const { container } = renderHome();

    expect(container.querySelectorAll('.qlink')).toHaveLength(4);
  });
});
