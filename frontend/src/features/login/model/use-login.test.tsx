import { act, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { authStore } from '@/shared/api/auth-session';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { useLoginPage } from './use-login';

describe('useLoginPage', () => {
  it('logs in with valid credentials and persists the session', async () => {
    const { result } = renderHookWithProviders(() => useLoginPage());

    let session: { email: string } | null = null;
    act(() => {
      result.current.submit({ email: 'admin@example.com', password: 'secret' }, (s) => {
        session = s;
      });
    });

    await waitFor(() => {
      expect(session).not.toBeNull();
    });

    expect(authStore.getToken()).toBe('test-jwt-token');
  });

  it('surfaces a problem-details message on invalid credentials', async () => {
    const { result } = renderHookWithProviders(() => useLoginPage());

    act(() => {
      result.current.submit({ email: 'admin@example.com', password: 'wrong' }, () => undefined);
    });

    await waitFor(() => {
      expect(result.current.submitError).not.toBeNull();
    });

    expect(authStore.getToken()).toBeNull();
  });
});
