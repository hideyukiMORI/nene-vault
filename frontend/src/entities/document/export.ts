import { useMutation, type UseMutationResult } from '@tanstack/react-query';
import { apiClient, type BlobDownload } from '@/shared/api/client';
import type { AppError } from '@/shared/api/errors';

export type ExportFormat = 'zip' | 'csv';

export interface ExportDocumentsInput {
  format: ExportFormat;
  include_voided: boolean;
  transaction_date_from?: string | undefined;
  transaction_date_to?: string | undefined;
  counterparty_name?: string | undefined;
}

/**
 * Requests an archive export (ZIP + manifest CSV, or CSV only). Goes through the
 * shared API client's postBlob so the Authorization + X-Authorization mirror
 * (#118) is sent — a raw fetch drops the mirror and 401s behind the shared-
 * hosting proxy that strips Authorization (#173). The page owns the browser
 * download side-effect from the returned blob.
 */
export function useExportDocuments(): UseMutationResult<
  BlobDownload,
  AppError,
  ExportDocumentsInput
> {
  return useMutation<BlobDownload, AppError, ExportDocumentsInput>({
    mutationFn: (input) => {
      const body: Record<string, unknown> = {
        include_voided: input.include_voided,
        format: input.format,
      };
      if (input.transaction_date_from !== undefined && input.transaction_date_from !== '') {
        body['transaction_date_from'] = input.transaction_date_from;
      }
      if (input.transaction_date_to !== undefined && input.transaction_date_to !== '') {
        body['transaction_date_to'] = input.transaction_date_to;
      }
      if (input.counterparty_name !== undefined && input.counterparty_name !== '') {
        body['counterparty_name'] = input.counterparty_name;
      }
      return apiClient.postBlob('/admin/vault/export', body);
    },
  });
}
