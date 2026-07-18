import { act, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { server } from '@tests/msw/server';
import { useOcrSuggest } from './ocr';

const OCR_PATH = '/admin/vault/documents/:id/ocr-suggest';
const DOC_ID = 'doc-123';

describe('useOcrSuggest', () => {
  it('maps the suggestion response to a prefill, dropping non-prefill fields', async () => {
    server.use(
      http.get(OCR_PATH, () =>
        HttpResponse.json({
          document_id: DOC_ID,
          transaction_date: '2026-07-01',
          amount_cents: 12345,
          counterparty_name: 'ACME Corp',
          has_suggestion: true,
        }),
      ),
    );

    const { result } = renderHookWithProviders(() => useOcrSuggest());

    let prefill: unknown;
    await act(async () => {
      prefill = await result.current.suggest(DOC_ID);
    });

    // document_id and has_suggestion are intentionally not part of OcrPrefill.
    expect(prefill).toEqual({
      transaction_date: '2026-07-01',
      amount_cents: 12345,
      counterparty_name: 'ACME Corp',
    });
  });

  it('passes null fields through when the server has no suggestion', async () => {
    server.use(
      http.get(OCR_PATH, () =>
        HttpResponse.json({
          document_id: DOC_ID,
          transaction_date: null,
          amount_cents: null,
          counterparty_name: null,
          has_suggestion: false,
        }),
      ),
    );

    const { result } = renderHookWithProviders(() => useOcrSuggest());

    let prefill: unknown;
    await act(async () => {
      prefill = await result.current.suggest(DOC_ID);
    });

    expect(prefill).toEqual({
      transaction_date: null,
      amount_cents: null,
      counterparty_name: null,
    });
  });

  it('returns null when the request fails', async () => {
    server.use(http.get(OCR_PATH, () => HttpResponse.json({}, { status: 500 })));

    const { result } = renderHookWithProviders(() => useOcrSuggest());

    let prefill: unknown = 'unset';
    await act(async () => {
      prefill = await result.current.suggest(DOC_ID);
    });

    expect(prefill).toBeNull();
  });

  it('is not loading once a request settles', async () => {
    server.use(
      http.get(OCR_PATH, () =>
        HttpResponse.json({
          document_id: DOC_ID,
          transaction_date: null,
          amount_cents: null,
          counterparty_name: null,
          has_suggestion: false,
        }),
      ),
    );

    const { result } = renderHookWithProviders(() => useOcrSuggest());
    expect(result.current.isLoading).toBe(false);

    await act(async () => {
      await result.current.suggest(DOC_ID);
    });

    await waitFor(() => {
      expect(result.current.isLoading).toBe(false);
    });
  });
});
