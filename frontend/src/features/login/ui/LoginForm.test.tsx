import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { authStore } from '@/entities/auth';
import { LoginForm } from './LoginForm';

/**
 * Regression test for the Input forwardRef bug: typing into the rendered form
 * fields must reach React Hook Form so the submitted credentials are non-empty.
 * Before the fix, Input dropped RHF's ref → values were never captured → submit
 * fired with empty fields (or Zod blocked it) and no request was made.
 */
describe('LoginForm (rendered)', () => {
  it('captures typed credentials and logs in', async () => {
    const onLoggedIn = vi.fn();
    renderWithProviders(<LoginForm onLoggedIn={onLoggedIn} />);

    const inputs = screen.getAllByRole('textbox');
    // email is the only role=textbox; password is type=password (not a textbox role)
    const emailInput = inputs[0];
    expect(emailInput).toBeDefined();

    await userEvent.type(emailInput as HTMLElement, 'admin@example.com');

    // Password field — query by placeholder since type=password has no textbox role
    const passwordInput = document.querySelector('input[type="password"]');
    expect(passwordInput).not.toBeNull();
    await userEvent.type(passwordInput as HTMLElement, 'secret');

    const submit = screen.getByRole('button', { name: /log|ログイン/i });
    await userEvent.click(submit);

    await waitFor(() => {
      expect(onLoggedIn).toHaveBeenCalledTimes(1);
    });
    expect(authStore.getToken()).toBe('test-jwt-token');
  });

  it('shows an error and does not log in with wrong credentials', async () => {
    const onLoggedIn = vi.fn();
    renderWithProviders(<LoginForm onLoggedIn={onLoggedIn} />);

    await userEvent.type(screen.getAllByRole('textbox')[0] as HTMLElement, 'admin@example.com');
    await userEvent.type(
      document.querySelector('input[type="password"]') as HTMLElement,
      'wrong-password',
    );
    await userEvent.click(screen.getByRole('button', { name: /log|ログイン/i }));

    await waitFor(() => {
      expect(screen.getByText(/正しくありません|incorrect|invalid/i)).toBeInTheDocument();
    });
    expect(onLoggedIn).not.toHaveBeenCalled();
    expect(authStore.getToken()).toBeNull();
  });
});
