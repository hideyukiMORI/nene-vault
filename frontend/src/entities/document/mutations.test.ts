import { act, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID, mockDocument, mockVoidedDocument, problemDetails } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { useUpdateDocumentMetadata, useVoidDocument, useRestoreDocument } from './mutations';

describe('useUpdateDocumentMetadata', () => {
  it('patches metadata and returns the updated document', async () => {
    const { result } = renderHookWithProviders(() => useUpdateDocumentMetadata());

    act(() => {
      result.current.mutate({ id: DOCUMENT_ID, counterparty_name: 'Updated Inc.' });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.counterparty_name).toBe('Updated Inc.');
  });

  it('is in error state for an unknown document id', async () => {
    const { result } = renderHookWithProviders(() => useUpdateDocumentMetadata());

    act(() => {
      result.current.mutate({ id: 'no-such-doc', counterparty_name: 'X' });
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
  });
});

describe('useVoidDocument', () => {
  it('voids a document and returns voided status', async () => {
    const { result } = renderHookWithProviders(() => useVoidDocument());

    act(() => {
      result.current.mutate({ id: DOCUMENT_ID, void_reason: 'Duplicate entry' });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.status).toBe('voided');
    expect(result.current.data?.void_reason).toBe(mockVoidedDocument.void_reason);
  });

  it('returns error when void_reason is missing', async () => {
    // Override handler: POST without void_reason → 422
    server.use(
      http.post(`/admin/vault/documents/${DOCUMENT_ID}/void`, async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>;
        if (!body['void_reason']) {
          return HttpResponse.json(problemDetails('validation-failed', 422, 'Validation Failed'), {
            status: 422,
            headers: { 'Content-Type': 'application/problem+json' },
          });
        }
        return HttpResponse.json(mockVoidedDocument);
      }),
    );

    const { result } = renderHookWithProviders(() => useVoidDocument());

    act(() => {
      result.current.mutate({ id: DOCUMENT_ID, void_reason: '' });
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
  });
});

describe('useRestoreDocument', () => {
  it('restores a voided document', async () => {
    const { result } = renderHookWithProviders(() => useRestoreDocument());

    act(() => {
      result.current.mutate(DOCUMENT_ID);
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.status).toBe('active');
    expect(result.current.data?.id).toBe(mockDocument.id);
  });

  it('is in error state for an unknown document id', async () => {
    const { result } = renderHookWithProviders(() => useRestoreDocument());

    act(() => {
      result.current.mutate('no-such-doc');
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
  });
});
