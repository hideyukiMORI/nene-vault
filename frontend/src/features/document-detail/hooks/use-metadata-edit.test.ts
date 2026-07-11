import { act, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { DOCUMENT_ID, mockDocument } from '@tests/msw/fixtures';
import { server } from '@tests/msw/server';
import { useMetadataEditForm } from './use-metadata-edit';

describe('useMetadataEditForm', () => {
  it('pre-populates form with document values', () => {
    const { result } = renderHookWithProviders(() =>
      useMetadataEditForm(mockDocument, () => undefined),
    );

    const values = result.current.form.getValues();
    expect(values.counterparty_name).toBe(mockDocument.counterparty_name);
    expect(values.category).toBe(mockDocument.category);
    expect(values.transaction_date).toBe(mockDocument.transaction_date);
    expect(values.amount_cents).toBe(String(mockDocument.amount_cents));
  });

  it('serialises tags array to comma-separated string', () => {
    const doc = { ...mockDocument, tags: ['q1', 'important', 'finance'] };
    const { result } = renderHookWithProviders(() => useMetadataEditForm(doc, () => undefined));

    expect(result.current.form.getValues('tags')).toBe('q1, important, finance');
  });

  it('uses empty string for tags when array is empty', () => {
    const doc = { ...mockDocument, tags: [] };
    const { result } = renderHookWithProviders(() => useMetadataEditForm(doc, () => undefined));

    expect(result.current.form.getValues('tags')).toBe('');
  });

  it('uses empty string for amount when null', () => {
    const doc = { ...mockDocument, amount_cents: null };
    const { result } = renderHookWithProviders(() => useMetadataEditForm(doc, () => undefined));

    expect(result.current.form.getValues('amount_cents')).toBe('');
  });

  it('uses empty string for transaction_date when null', () => {
    const doc = { ...mockDocument, transaction_date: null };
    const { result } = renderHookWithProviders(() => useMetadataEditForm(doc, () => undefined));

    expect(result.current.form.getValues('transaction_date')).toBe('');
  });

  it('does not call mutation when counterparty_name is empty', async () => {
    let mutationCalled = false;
    server.use(
      http.patch(`/admin/vault/documents/${DOCUMENT_ID}/metadata`, () => {
        mutationCalled = true;
        return HttpResponse.json(mockDocument);
      }),
    );

    const { result } = renderHookWithProviders(() =>
      useMetadataEditForm(mockDocument, () => undefined),
    );

    act(() => {
      result.current.form.setValue('counterparty_name', '');
    });

    await act(async () => {
      await result.current.onSubmit(new Event('submit') as unknown as React.BaseSyntheticEvent);
    });

    await new Promise((r) => {
      setTimeout(r, 50);
    });

    expect(mutationCalled).toBe(false);
  });

  it('submits successfully with valid values and calls onSuccess', async () => {
    let called = false;
    const { result } = renderHookWithProviders(() =>
      useMetadataEditForm(mockDocument, () => {
        called = true;
      }),
    );

    await act(async () => {
      await result.current.onSubmit(new Event('submit') as unknown as React.BaseSyntheticEvent);
    });

    await waitFor(() => {
      expect(called).toBe(true);
    });
  });

  it('initialises isSubmitting as false', () => {
    const { result } = renderHookWithProviders(() =>
      useMetadataEditForm(mockDocument, () => undefined),
    );

    expect(result.current.isSubmitting).toBe(false);
  });

  it('initialises submitError as null', () => {
    const { result } = renderHookWithProviders(() =>
      useMetadataEditForm(mockDocument, () => undefined),
    );

    expect(result.current.submitError).toBeNull();
  });
});
