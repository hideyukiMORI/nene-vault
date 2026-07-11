import { act, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderHookWithProviders } from '@tests/render/render-with-providers';
import { server } from '@tests/msw/server';
import { useExportDocuments } from './export';

describe('useExportDocuments', () => {
  it('posts the export request and returns the downloaded blob + filename', async () => {
    let seenBody: Record<string, unknown> | null = null;
    server.use(
      http.post('/admin/vault/export', async ({ request }) => {
        seenBody = (await request.json()) as Record<string, unknown>;
        return new HttpResponse('bytes', {
          status: 200,
          headers: {
            'Content-Type': 'application/zip',
            'Content-Disposition': 'attachment; filename="vault-export.zip"',
          },
        });
      }),
    );

    const { result } = renderHookWithProviders(() => useExportDocuments());

    act(() => {
      result.current.mutate({
        format: 'zip',
        include_voided: true,
        counterparty_name: 'Acme',
        transaction_date_from: '',
        transaction_date_to: '',
      });
    });

    await waitFor(() => {
      expect(result.current.isSuccess).toBe(true);
    });

    expect(result.current.data?.filename).toBe('vault-export.zip');
    // Empty optional filters are omitted from the request body.
    expect(seenBody).toEqual({ include_voided: true, format: 'zip', counterparty_name: 'Acme' });
  });
});
