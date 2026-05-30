import { act, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { mockDocument, problemDetails } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { useUploadDocument } from '@/entities/document';

import { useDocumentUpload } from './use-document-upload';

function makePdfFile(name = 'invoice.pdf'): File {
  return new File(['%PDF-1.4\nfake content'], name, { type: 'application/pdf' });
}

describe('useDocumentUpload (form hook)', () => {
  it('initialises with empty defaults', () => {
    const { result } = renderHookWithProviders(() => useDocumentUpload(() => undefined));

    expect(result.current.form).toBeDefined();
    expect(result.current.isSubmitting).toBe(false);
    expect(result.current.submitError).toBeNull();
  });
});

describe('useUploadDocument (entity mutation)', () => {
  it('calls onSuccess after a successful upload', async () => {
    let called = false;
    const { result } = renderHookWithProviders(() =>
      useUploadDocument(() => {
        called = true;
      }),
    );

    act(() => {
      result.current.mutate({
        file: makePdfFile(),
        counterparty_name: 'Sample Inc.',
        category: 'invoice_received',
      });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(called).toBe(true);
    expect(result.current.data?.id).toBe(mockDocument.id);
  });

  it('returns the uploaded document with correct shape', async () => {
    const { result } = renderHookWithProviders(() => useUploadDocument());

    act(() => {
      result.current.mutate({
        file: makePdfFile(),
        counterparty_name: 'Sample Inc.',
        category: 'invoice_received',
        transaction_date: '2026-03-31',
        amount_cents: '110000',
      });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.counterparty_name).toBe(mockDocument.counterparty_name);
    expect(result.current.data?.status).toBe('active');
  });

  it('is in error state when the server returns 415', async () => {
    server.use(
      http.post('/admin/vault/documents', () => {
        return HttpResponse.json(
          problemDetails('mime-type-not-allowed', 415, 'MIME Type Not Allowed'),
          { status: 415, headers: { 'Content-Type': 'application/problem+json' } },
        );
      }),
    );

    const { result } = renderHookWithProviders(() => useUploadDocument());

    act(() => {
      result.current.mutate({
        file: makePdfFile('doc.txt'),
        counterparty_name: 'Test',
        category: 'other',
      });
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
  });

  it('is in error state on duplicate file (409)', async () => {
    server.use(
      http.post('/admin/vault/documents', () => {
        return HttpResponse.json(problemDetails('duplicate-file', 409, 'Duplicate File'), {
          status: 409,
          headers: { 'Content-Type': 'application/problem+json' },
        });
      }),
    );

    const { result } = renderHookWithProviders(() => useUploadDocument());

    act(() => {
      result.current.mutate({
        file: makePdfFile(),
        counterparty_name: 'Dup Co',
        category: 'receipt',
      });
    });

    await waitFor(() => {
      expect(result.current.isError).toBe(true);
    });
  });
});
