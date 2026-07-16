// Vault settings DTOs — re-exported from the OpenAPI-generated schema (A-5).
// docs/openapi/openapi.yaml is the single source of truth; run `npm run codegen`
// after changing it.

import type { components } from '@/shared/api/schema.gen';

export type VaultSettings = components['schemas']['VaultSettingsResponse'];
export type UpdateVaultSettingsInput = components['schemas']['UpdateVaultSettingsRequest'];
