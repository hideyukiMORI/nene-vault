// Audit DTOs — re-exported from the OpenAPI-generated schema (A-5).
// docs/openapi/openapi.yaml is the single source of truth; run `npm run codegen`
// after changing it.

import type { components, operations } from '@/shared/api/schema.gen';

export type AuditEvent = components['schemas']['AuditEventResponse'];
export type AuditEventListResponse = components['schemas']['AuditEventListResponse'];

export type DocumentVersion = components['schemas']['DocumentVersionResponse'];
export type DocumentHistoryResponse = components['schemas']['DocumentHistoryResponse'];

export type ListAuditEventsParams = NonNullable<
  operations['listAuditEvents']['parameters']['query']
>;
