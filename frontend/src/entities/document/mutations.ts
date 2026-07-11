import { useMutation, useQueryClient } from '@tanstack/react-query';
import { auditQueryKeys } from '@/entities/audit';
import { apiClient } from '@/shared/api/client';
import type { AppError } from '@/shared/api/errors';
import type { DocumentCategory, VaultDocument } from './types';
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

export interface UpdateMetadataInput {
  id: string;
  transaction_date?: string | null | undefined;
  amount_cents?: number | null | undefined;
  counterparty_name?: string | undefined;
  category?: DocumentCategory | undefined;
  tags?: string[] | undefined;
}

export function useUpdateDocumentMetadata(onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation<VaultDocument, AppError, UpdateMetadataInput>({
    mutationFn: ({ id, ...body }) =>
      apiClient.patch<VaultDocument>(`/admin/vault/documents/${id}/metadata`, body),
    onSuccess: (_, { id }) => {
      void queryClient.invalidateQueries({ queryKey: documentQueryKeys.detail(id) });
      void queryClient.invalidateQueries({ queryKey: documentQueryKeys.all });
      // The detail page's change-history table reads the audit history query,
      // which is keyed separately; refresh it so new events show without reload.
      void queryClient.invalidateQueries({ queryKey: auditQueryKeys.documentHistory(id) });
      onSuccess?.();
    },
  });
}

export interface VoidDocumentInput {
  id: string;
  void_reason: string;
  void_note?: string | null | undefined;
}

export function useVoidDocument(onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation<VaultDocument, AppError, VoidDocumentInput>({
    mutationFn: ({ id, ...body }) =>
      apiClient.post<VaultDocument>(`/admin/vault/documents/${id}/void`, body),
    onSuccess: (_, { id }) => {
      void queryClient.invalidateQueries({ queryKey: documentQueryKeys.detail(id) });
      void queryClient.invalidateQueries({ queryKey: documentQueryKeys.all });
      // The detail page's change-history table reads the audit history query,
      // which is keyed separately; refresh it so new events show without reload.
      void queryClient.invalidateQueries({ queryKey: auditQueryKeys.documentHistory(id) });
      onSuccess?.();
    },
  });
}

export function useRestoreDocument(onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation<VaultDocument, AppError, string>({
    mutationFn: (id) => apiClient.post<VaultDocument>(`/admin/vault/documents/${id}/restore`, {}),
    onSuccess: (_, id) => {
      void queryClient.invalidateQueries({ queryKey: documentQueryKeys.detail(id) });
      void queryClient.invalidateQueries({ queryKey: documentQueryKeys.all });
      // The detail page's change-history table reads the audit history query,
      // which is keyed separately; refresh it so new events show without reload.
      void queryClient.invalidateQueries({ queryKey: auditQueryKeys.documentHistory(id) });
      onSuccess?.();
    },
  });
}
