import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '@/shared/api/client';
import type { AppError } from '@/shared/api/errors';
import type { DocumentListResponse, SearchDocumentsParams, VaultDocument } from './types';

function buildSearchPath(params: SearchDocumentsParams): string {
  const q = new URLSearchParams();
  if (params.transaction_date_from !== undefined) {
    q.set('transaction_date_from', params.transaction_date_from);
  }
  if (params.transaction_date_to !== undefined) {
    q.set('transaction_date_to', params.transaction_date_to);
  }
  if (params.amount_min_cents !== undefined) {
    q.set('amount_min_cents', String(params.amount_min_cents));
  }
  if (params.amount_max_cents !== undefined) {
    q.set('amount_max_cents', String(params.amount_max_cents));
  }
  if (params.counterparty_name !== undefined && params.counterparty_name !== '') {
    q.set('counterparty_name', params.counterparty_name);
  }
  if (params.category !== undefined) {
    q.set('category', params.category);
  }
  if (params.include_voided === true) {
    q.set('include_voided', 'true');
  }
  if (params.limit !== undefined) {
    q.set('limit', String(params.limit));
  }
  if (params.offset !== undefined) {
    q.set('offset', String(params.offset));
  }
  const qs = q.toString();
  return `/admin/vault/documents${qs !== '' ? `?${qs}` : ''}`;
}

export const documentQueryKeys = {
  all: ['documents'] as const,
  list: (params: SearchDocumentsParams) => ['documents', 'list', params] as const,
  detail: (id: string) => ['documents', 'detail', id] as const,
} as const;

export function useDocuments(
  params: SearchDocumentsParams,
): UseQueryResult<DocumentListResponse, AppError> {
  return useQuery<DocumentListResponse, AppError>({
    queryKey: documentQueryKeys.list(params),
    queryFn: ({ signal }) => apiClient.get<DocumentListResponse>(buildSearchPath(params), signal),
  });
}

export function useDocumentById(id: string): UseQueryResult<VaultDocument, AppError> {
  return useQuery<VaultDocument, AppError>({
    queryKey: documentQueryKeys.detail(id),
    queryFn: ({ signal }) => apiClient.get<VaultDocument>(`/admin/vault/documents/${id}`, signal),
  });
}
