import { act, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { authStore } from './model';
import { useLogin } from './mutations';

const VALID = { email: 'admin@example.com', password: 'secret' };

afterEach(() => {
  sessionStorage.clear();
});

describe('useLogin', () => {
  it('maps the snake_case login response to a camelCase session', async () => {
    const { result } = renderHookWithProviders(() => useLogin());

    act(() => {
      result.current.mutate(VALID);
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    // toSession maps user_id → userId and org_id → orgId.
    expect(result.current.data).toEqual({
      token: 'test-jwt-token',
      userId: 1,
      email: 'admin@example.com',
      role: 'admin',
      orgId: 1,
    });
  });

  it('persists the session so getToken/getSession reflect it', async () => {
    const { result } = renderHookWithProviders(() => useLogin());

    act(() => {
      result.current.mutate(VALID);
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(authStore.getToken()).toBe('test-jwt-token');
    expect(authStore.getSession()?.orgId).toBe(1);
  });

  it('does not persist a session and surfaces an error on invalid credentials', async () => {
    const { result } = renderHookWithProviders(() => useLogin());

    act(() => {
      result.current.mutate({ email: 'admin@example.com', password: 'wrong' });
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });

    expect(result.current.error).not.toBeNull();
    expect(result.current.data).toBeUndefined();
    expect(authStore.getToken()).toBeNull();
  });
});
