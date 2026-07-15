// Document DTOs — re-exported from the OpenAPI-generated schema (A-5).
// docs/openapi/openapi.yaml is the single source of truth; run `npm run codegen`
// after changing it.

import type { components, operations } from '@/shared/api/schema.gen';

export type DocumentStatus = components['schemas']['DocumentStatus'];
export type DocumentCategory = components['schemas']['DocumentCategory'];
export type DocumentSource = components['schemas']['DocumentSource'];

export type VaultDocument = components['schemas']['VaultDocumentResponse'];
export type DocumentListResponse = components['schemas']['VaultDocumentListResponse'];

export type SearchDocumentsParams = NonNullable<
  operations['searchDocuments']['parameters']['query']
>;
