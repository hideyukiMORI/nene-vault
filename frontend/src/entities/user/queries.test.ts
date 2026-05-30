import { act, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { mockUser, mockUserList } from '@tests/msw/fixtures';
import { useUsers, useCreateUser, useDeleteUser } from './queries';

describe('useUsers', () => {
  it('returns paginated user list', async () => {
    const { result } = renderHookWithProviders(() => useUsers(20, 0));

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.items).toHaveLength(1);
    expect(result.current.data?.total).toBe(mockUserList.total);
    expect(result.current.data?.items[0]?.email).toBe(mockUser.email);
  });

  it('returns correct user shape without password_hash', async () => {
    const { result } = renderHookWithProviders(() => useUsers(20, 0));

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    const user = result.current.data?.items[0];
    expect(user?.role).toBe('member');
    expect(user?.status).toBe('active');
    expect(user).not.toHaveProperty('password_hash');
  });
});

describe('useCreateUser', () => {
  it('creates a user and returns the new user', async () => {
    const { result } = renderHookWithProviders(() => useCreateUser());

    act(() => {
      result.current.mutate({
        email: 'new@example.com',
        password: 'changeme123',
        role: 'member',
      });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.email).toBe('new@example.com');
    expect(result.current.data?.role).toBe('member');
  });

  it('calls onSuccess callback after creation', async () => {
    let called = false;
    const { result } = renderHookWithProviders(() =>
      useCreateUser(() => {
        called = true;
      }),
    );

    act(() => {
      result.current.mutate({
        email: 'cb@example.com',
        password: 'changeme123',
        role: 'viewer',
      });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(called).toBe(true);
  });
});

describe('useDeleteUser', () => {
  it('deletes a user (204 response)', async () => {
    const { result } = renderHookWithProviders(() => useDeleteUser());

    act(() => {
      result.current.mutate(42);
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });
  });

  it('calls onSuccess callback after deletion', async () => {
    let called = false;
    const { result } = renderHookWithProviders(() =>
      useDeleteUser(() => {
        called = true;
      }),
    );

    act(() => {
      result.current.mutate(42);
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(called).toBe(true);
  });
});
