import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { renderWithProviders } from '@tests/render/render-with-providers';
import { authStore } from '@/shared/api/auth-session';
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
    const passwordInput = screen.getByPlaceholderText(/password|パスワード/i);
    await userEvent.type(passwordInput, 'secret');

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
    await userEvent.type(screen.getByPlaceholderText(/password|パスワード/i), 'wrong-password');
    await userEvent.click(screen.getByRole('button', { name: /log|ログイン/i }));

    await waitFor(() => {
      expect(screen.getByText(/正しくありません|incorrect|invalid/i)).toBeInTheDocument();
    });
    expect(onLoggedIn).not.toHaveBeenCalled();
    expect(authStore.getToken()).toBeNull();
  });

  /**
   * Acceptance condition for the nene2-ui migration (#387, from #385).
   *
   * 🔴 Before the migration this form set `aria-invalid` on both fields and rendered no
   * message at all: `useLoginPage` returned booleans, so there was nothing to render.
   * Assistive technology was told the field was wrong and never told why — and neither
   * was anyone else. `FormField` links the message; the model now has to supply one.
   */
  it('names which field is wrong, and links the reason to it', async () => {
    renderWithProviders(<LoginForm onLoggedIn={vi.fn()} />);

    const email = screen.getAllByRole('textbox')[0] as HTMLElement;
    await userEvent.type(email, 'not-an-email');
    await userEvent.click(screen.getByRole('button', { name: /log|ログイン/i }));

    // The message exists and is about the format, not merely "required".
    const message = await screen.findByText(/メールアドレス|email address/i, {
      selector: '[role="alert"]',
    });

    // …and the control points at it, which is the half that was missing.
    await waitFor(() => {
      expect(email).toHaveAttribute('aria-invalid', 'true');
    });
    expect(email).toHaveAttribute('aria-describedby', message.id);
    expect(message.id).not.toBe('');
  });

  it('reports the empty password separately from the email', async () => {
    renderWithProviders(<LoginForm onLoggedIn={vi.fn()} />);

    await userEvent.type(screen.getAllByRole('textbox')[0] as HTMLElement, 'admin@example.com');
    await userEvent.click(screen.getByRole('button', { name: /log|ログイン/i }));

    const password = screen.getByPlaceholderText(/password|パスワード/i);
    await waitFor(() => {
      expect(password).toHaveAttribute('aria-invalid', 'true');
    });

    const message = await screen.findByText(/必須|required/i, { selector: '[role="alert"]' });
    expect(password).toHaveAttribute('aria-describedby', message.id);
    expect(message.id).not.toBe('');

    // The email was valid, so it must not be marked.
    expect(screen.getAllByRole('textbox')[0]).not.toHaveAttribute('aria-invalid', 'true');
  });
});
