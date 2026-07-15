// Auth DTOs — re-exported from the OpenAPI-generated schema (A-5). This is the
// aliasing the original hand-written version said it was waiting for.
// docs/openapi/openapi.yaml is the single source of truth; run `npm run codegen`
// after changing it.

import type { components } from '@/shared/api/schema.gen';

export type LoginRequest = components['schemas']['LoginRequest'];
export type LoginResponse = components['schemas']['LoginResponse'];
