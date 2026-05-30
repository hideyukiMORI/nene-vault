import { act, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { mockVaultSettings } from '@tests/msw/fixtures';
import { useVaultSettings, useUpdateVaultSettings } from './queries';

describe('useVaultSettings', () => {
  it('returns vault settings with retention_years', async () => {
    const { result } = renderHookWithProviders(() => useVaultSettings());

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.retention_years).toBe(mockVaultSettings.retention_years);
    expect(result.current.data?.organization_id).toBe(1);
  });

  it('is in loading state initially', () => {
    const { result } = renderHookWithProviders(() => useVaultSettings());
    expect(result.current.isLoading).toBe(true);
  });
});

describe('useUpdateVaultSettings', () => {
  it('patches retention_years and returns updated settings', async () => {
    const { result } = renderHookWithProviders(() => useUpdateVaultSettings());

    act(() => {
      result.current.mutate({ retention_years: 15 });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.retention_years).toBe(15);
  });

  it('patches storage_path_override', async () => {
    const { result } = renderHookWithProviders(() => useUpdateVaultSettings());

    act(() => {
      result.current.mutate({ storage_path_override: '/custom/path' });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.storage_path_override).toBe('/custom/path');
  });

  it('calls onSuccess callback after successful mutation', async () => {
    let called = false;
    const { result } = renderHookWithProviders(() =>
      useUpdateVaultSettings(() => {
        called = true;
      }),
    );

    act(() => {
      result.current.mutate({ retention_years: 12 });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(called).toBe(true);
  });
});
