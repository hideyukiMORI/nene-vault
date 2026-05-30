import { waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID, mockDocument } from '@tests/msw/fixtures';
import { useDocuments, useDocumentById } from './queries';

describe('useDocuments', () => {
  it('returns a paginated document list', async () => {
    const { result } = renderHookWithProviders(() => useDocuments({}));

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.items).toHaveLength(1);
    expect(result.current.data?.total).toBe(1);
    expect(result.current.data?.items[0]?.id).toBe(DOCUMENT_ID);
  });

  it('is in loading state initially', () => {
    const { result } = renderHookWithProviders(() => useDocuments({}));
    expect(result.current.isLoading).toBe(true);
  });

  it('passes query params in the request URL', async () => {
    const { result } = renderHookWithProviders(() =>
      useDocuments({ counterparty_name: 'Sample', category: 'invoice_received' }),
    );

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });
    expect(result.current.data?.items[0]?.counterparty_name).toBe('Sample Inc.');
  });
});

describe('useDocumentById', () => {
  it('fetches a single document by id', async () => {
    const { result } = renderHookWithProviders(() => useDocumentById(DOCUMENT_ID));

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.id).toBe(DOCUMENT_ID);
    expect(result.current.data?.counterparty_name).toBe(mockDocument.counterparty_name);
    expect(result.current.data?.amount_cents).toBe(110000);
  });

  it('is in error state for an unknown id', async () => {
    const { result } = renderHookWithProviders(() => useDocumentById('unknown-id'));

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
  });
});
