import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/shared/api/client';
import type { AppError } from '@/shared/api/errors';
import type { VaultDocument } from './types';
import { documentQueryKeys } from './queries';

export interface UploadDocumentInput {
  file: File;
  counterparty_name: string;
  category: string;
  transaction_date?: string | undefined;
  amount_cents?: string | undefined;
  tags?: string | undefined;
}

export function useUploadDocument(onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation<VaultDocument, AppError, UploadDocumentInput>({
    mutationFn: (input) => {
      const fd = new FormData();
      fd.append('file', input.file);
      fd.append('counterparty_name', input.counterparty_name);
      fd.append('category', input.category);
      if (input.transaction_date !== undefined && input.transaction_date !== '') {
        fd.append('transaction_date', input.transaction_date);
      }
      if (input.amount_cents !== undefined && input.amount_cents !== '') {
        fd.append('amount_cents', input.amount_cents);
      }
      if (input.tags !== undefined && input.tags !== '') {
        fd.append('tags', input.tags);
      }
      return apiClient.upload<VaultDocument>('/admin/vault/documents', fd);
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: documentQueryKeys.all });
      onSuccess?.();
    },
  });
}
