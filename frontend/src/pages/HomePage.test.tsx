import { screen, within } from '@testing-library/react';
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

// The suite renders in the default locale, which is English — see the note on the vacuous
// Japanese assertions below.
const QUICK_ACCESS = 'Quick access';

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

    // 🔴 Scoped to the quick-access group, not filtered by a styling class. This used to
    // read `classList.contains('qlink')`, which made a class that exists to paint a card
    // load-bearing for "which buttons are the cards" — and it broke the moment that class
    // was drained (#426). The group carries `role="group"` + `aria-labelledby` now, so the
    // question is asked of the accessibility tree, where the answer is also true for a
    // screen reader.
    const cards = within(screen.getByRole('group', { name: QUICK_ACCESS })).getAllByRole('button');
    expect(cards).toHaveLength(1);
    // Admin-only card labels appear nowhere on a viewer's home (rail is gated too).
    // 🔴 In English. These read `'監査ログ'` / `'保管設定'` / `'エクスポート'` until 2026-08-24,
    // and this suite renders in the default locale — which is English. So all three asserted
    // that a string which never appears in any locale is absent: they could not fail, for a
    // viewer or for an admin. Found while fixing the card query for #426.
    expect(screen.queryByText('Audit Log')).toBeNull();
    expect(screen.queryByText('Vault Settings')).toBeNull();
    expect(screen.queryByText('Export')).toBeNull();
  });

  it('shows an admin all four quick-access cards', () => {
    authStore.setSession({ ...baseSession, role: 'admin' });
    renderHome();

    const cards = within(screen.getByRole('group', { name: QUICK_ACCESS })).getAllByRole('button');
    expect(cards).toHaveLength(4);

    // 🔴 The positive control for the three `queryByText(...)` assertions in the test above.
    // Those assert absence, and an assertion of absence passes for free when the string is
    // wrong — which is exactly what had happened (they were in Japanese, this suite renders
    // in English). Asserting the same three strings are *present* here is what makes the
    // absence above mean something.
    //
    // Scoped to the group: for an admin these labels are on the rail as well, so a
    // page-wide `getByText` finds two of each. The absence assertions above are deliberately
    // page-wide — the point there is that a viewer sees them in neither place.
    const group = within(screen.getByRole('group', { name: QUICK_ACCESS }));
    expect(group.getByText('Audit Log')).toBeInTheDocument();
    expect(group.getByText('Vault Settings')).toBeInTheDocument();
    expect(group.getByText('Export')).toBeInTheDocument();
  });
});
