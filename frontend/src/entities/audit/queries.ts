import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '@/shared/api/client';
import type { AppError } from '@/shared/api/errors';
import type {
  AuditEventListResponse,
  DocumentHistoryResponse,
  ListAuditEventsParams,
} from './types';

export const auditQueryKeys = {
  all: ['audit'] as const,
  list: (params: ListAuditEventsParams) => ['audit', 'list', params] as const,
  documentHistory: (id: string) => ['audit', 'history', id] as const,
} as const;

function buildAuditPath(params: ListAuditEventsParams): string {
  const q = new URLSearchParams();
  if (params.entity_type !== undefined) q.set('entity_type', params.entity_type);
  if (params.entity_id !== undefined) q.set('entity_id', params.entity_id);
  if (params.action !== undefined) q.set('action', params.action);
  if (params.actor_user_id !== undefined) q.set('actor_user_id', String(params.actor_user_id));
  if (params.limit !== undefined) q.set('limit', String(params.limit));
  if (params.offset !== undefined) q.set('offset', String(params.offset));
  const qs = q.toString();
  return `/admin/audit-events${qs !== '' ? `?${qs}` : ''}`;
}

export function useAuditEvents(
  params: ListAuditEventsParams,
): UseQueryResult<AuditEventListResponse, AppError> {
  return useQuery<AuditEventListResponse, AppError>({
    queryKey: auditQueryKeys.list(params),
    queryFn: ({ signal }) => apiClient.get<AuditEventListResponse>(buildAuditPath(params), signal),
  });
}

export function useDocumentHistory(id: string): UseQueryResult<DocumentHistoryResponse, AppError> {
  return useQuery<DocumentHistoryResponse, AppError>({
    queryKey: auditQueryKeys.documentHistory(id),
    queryFn: ({ signal }) =>
      apiClient.get<DocumentHistoryResponse>(`/admin/vault/documents/${id}/history`, signal),
  });
}
