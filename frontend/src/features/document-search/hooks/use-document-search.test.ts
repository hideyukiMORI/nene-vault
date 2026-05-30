import { act, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { mockDocumentList } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { useDocumentSearch } from './use-document-search';

describe('useDocumentSearch', () => {
  it('fetches documents on mount with default params', async () => {
    const { result } = renderHookWithProviders(() => useDocumentSearch());

    await waitFor(() => {
      expect(result.current.result.isSuccess).toBe(true);
    });

    expect(result.current.result.data?.items).toHaveLength(1);
    expect(result.current.pagination.total).toBe(1);
  });

  it('exposes search form with register and onSubmit', () => {
    const { result } = renderHookWithProviders(() => useDocumentSearch());

    expect(result.current.form).toBeDefined();
    expect(result.current.onSubmit).toBeTypeOf('function');
    expect(result.current.onReset).toBeTypeOf('function');
  });

  it('pagination reflects total count', async () => {
    const { result } = renderHookWithProviders(() => useDocumentSearch());

    await waitFor(() => {
      expect(result.current.result.isSuccess).toBe(true);
    });

    expect(result.current.pagination.canPrev).toBe(false);
    expect(result.current.pagination.canNext).toBe(false);
    expect(result.current.pagination.totalPages).toBe(1);
  });

  it('pagination goNext advances offset when more pages exist', async () => {
    // Return 25 items so canNext is true (page size is 20)
    server.use(
      http.get('/admin/vault/documents', () => {
        return HttpResponse.json({ ...mockDocumentList, total: 25 });
      }),
    );

    const { result } = renderHookWithProviders(() => useDocumentSearch());

    await waitFor(() => {
      expect(result.current.result.isSuccess).toBe(true);
    });

    expect(result.current.pagination.canNext).toBe(true);

    act(() => {
      result.current.pagination.goNext();
    });

    expect(result.current.pagination.offset).toBe(20);
  });

  it('onReset clears the offset back to 0', async () => {
    server.use(
      http.get('/admin/vault/documents', () => {
        return HttpResponse.json({ ...mockDocumentList, total: 25 });
      }),
    );

    const { result } = renderHookWithProviders(() => useDocumentSearch());

    await waitFor(() => {
      expect(result.current.result.isSuccess).toBe(true);
    });

    act(() => {
      result.current.pagination.goNext();
    });
    expect(result.current.pagination.offset).toBe(20);

    act(() => {
      result.current.onReset();
    });
    expect(result.current.pagination.offset).toBe(0);
  });

  it('is in error state when the API returns an error', async () => {
    server.use(
      http.get('/admin/vault/documents', () => {
        return new HttpResponse(null, { status: 500 });
      }),
    );

    const { result } = renderHookWithProviders(() => useDocumentSearch());

    await waitFor(() => {
      expect(result.current.result.isError).toBe(true);
    });
  });
});
