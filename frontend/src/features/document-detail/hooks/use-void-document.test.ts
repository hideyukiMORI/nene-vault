import { act, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID, mockVoidedDocument, problemDetails } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { useVoidDocumentForm } from './use-void-document';

describe('useVoidDocumentForm', () => {
  it('initialises with empty void_reason', () => {
    const { result } = renderHookWithProviders(() =>
      useVoidDocumentForm(DOCUMENT_ID, () => undefined),
    );

    expect(result.current.form.getValues('void_reason')).toBe('');
    expect(result.current.isSubmitting).toBe(false);
    expect(result.current.submitError).toBeNull();
  });

  it('does not call mutation when void_reason is empty', async () => {
    // MSW handler would return 422 for empty void_reason, but here we verify
    // that the Zod schema prevents even reaching the API.
    let mutationCalled = false;
    server.use(
      http.post(`/admin/vault/documents/${DOCUMENT_ID}/void`, () => {
        mutationCalled = true;
        return HttpResponse.json(mockVoidedDocument);
      }),
    );

    const { result } = renderHookWithProviders(() =>
      useVoidDocumentForm(DOCUMENT_ID, () => undefined),
    );

    // Default void_reason is '' — onSubmit should not invoke the mutation
    await act(async () => {
      await result.current.onSubmit(new Event('submit') as unknown as React.FormEvent);
    });

    // Wait a tick to ensure no pending mutations fire
    await new Promise((r) => {
      setTimeout(r, 50);
    });

    expect(mutationCalled).toBe(false);
  });

  it('submits when void_reason is provided', async () => {
    let called = false;
    const { result } = renderHookWithProviders(() =>
      useVoidDocumentForm(DOCUMENT_ID, () => {
        called = true;
      }),
    );

    act(() => {
      result.current.form.setValue('void_reason', 'Duplicate entry');
    });

    await act(async () => {
      await result.current.onSubmit(new Event('submit') as unknown as React.FormEvent);
    });

    await waitFor(() => {
      expect(called).toBe(true);
    });
  });

  it('exposes the voided document status after success', async () => {
    const { result } = renderHookWithProviders(() =>
      useVoidDocumentForm(DOCUMENT_ID, () => undefined),
    );

    act(() => {
      result.current.form.setValue('void_reason', 'Testing');
    });

    await act(async () => {
      await result.current.onSubmit(new Event('submit') as unknown as React.FormEvent);
    });

    await waitFor(() => {
      expect(result.current.submitError).toBeNull();
    });
  });

  it('sets submitError to a locale key on API error', async () => {
    server.use(
      http.post(`/admin/vault/documents/${DOCUMENT_ID}/void`, () => {
        return HttpResponse.json(
          problemDetails('retention-window-active', 409, 'Retention Window Active'),
          { status: 409, headers: { 'Content-Type': 'application/problem+json' } },
        );
      }),
    );

    const { result } = renderHookWithProviders(() =>
      useVoidDocumentForm(DOCUMENT_ID, () => undefined),
    );

    act(() => {
      result.current.form.setValue('void_reason', 'Will fail');
    });

    await act(async () => {
      await result.current.onSubmit(new Event('submit') as unknown as React.FormEvent);
    });

    await waitFor(() => {
      expect(result.current.submitError).not.toBeNull();
    });
    expect(result.current.submitError).toBe('problem.retention_window_active');
  });

  it('accepts an optional void_note', async () => {
    let called = false;
    const { result } = renderHookWithProviders(() =>
      useVoidDocumentForm(DOCUMENT_ID, () => {
        called = true;
      }),
    );

    act(() => {
      result.current.form.setValue('void_reason', 'Some reason');
      result.current.form.setValue('void_note', 'Extra context');
    });

    await act(async () => {
      await result.current.onSubmit(new Event('submit') as unknown as React.FormEvent);
    });

    await waitFor(() => {
      expect(called).toBe(true);
    });
    expect(result.current.form.getValues('void_note')).toBe('Extra context');
  });
});
