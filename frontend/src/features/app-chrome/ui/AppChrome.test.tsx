import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { AppChrome } from './AppChrome';

// AppChrome is the app-layer i18n adapter (会議R1②): it resolves every string
// via useTranslation and forwards them to the presentation-only AppShell.
// jsdom resolves to the 'en' catalog (navigator.language = 'en-US'), so the
// resolved labels below are the English catalog values.

function renderChrome(props: { role?: string; email?: string; onLogout?: () => void }) {
  return renderWithProviders(
    <MemoryRouter initialEntries={['/']}>
      <AppChrome
        onLogout={props.onLogout ?? (() => undefined)}
        userEmail={props.email}
        userRole={props.role}
      >
        <div>chrome-content</div>
      </AppChrome>
    </MemoryRouter>,
  );
}

describe('AppChrome', () => {
  it('resolves nav labels and renders the child content through AppShell', () => {
    renderChrome({ role: 'admin', email: 'admin@example.com' });

    // Resolved nav label (t('navigation.home')) reaches the shell.
    expect(screen.getByRole('button', { name: 'Home' })).toBeInTheDocument();
    // Children render inside the shell.
    expect(screen.getByText('chrome-content')).toBeInTheDocument();
    // Resolved role label (t('user.role.admin')) reaches the rail footer.
    expect(screen.getByText('Admin')).toBeInTheDocument();
  });

  it('omits the role label when no role is given (roleLabel = null branch)', () => {
    renderChrome({ email: 'nobody@example.com' });

    expect(screen.getByText('chrome-content')).toBeInTheDocument();
    // With no role, the 'Admin' role label must not appear.
    expect(screen.queryByText('Admin')).not.toBeInTheDocument();
  });

  it('wires the language switcher through to setLocale', async () => {
    const user = userEvent.setup();
    renderChrome({ role: 'admin' });

    // The resolved language label (t('navigation.language')) labels the select.
    const select = screen.getByLabelText('Language');
    expect(select).toHaveValue('en');

    // Switching locale flows through AppChrome's onLocaleChange → setLocale;
    // the catalog swaps to Japanese and the home nav re-resolves.
    await user.selectOptions(select, 'ja');
    expect(await screen.findByRole('button', { name: 'ホーム' })).toBeInTheDocument();
  });

  it('invokes onLogout when the rail logout control is used', async () => {
    const user = userEvent.setup();
    const onLogout = vi.fn();
    renderChrome({ role: 'admin', onLogout });

    await user.click(screen.getByRole('button', { name: 'Log Out' }));
    expect(onLogout).toHaveBeenCalledOnce();
  });
});
