// User DTOs — re-exported from the OpenAPI-generated schema (A-5).
// docs/openapi/openapi.yaml is the single source of truth; run `npm run codegen`
// after changing it.

import type { components } from '@/shared/api/schema.gen';

export type UserRole = components['schemas']['Role'];
export type UserStatus = components['schemas']['UserResponse']['status'];

export type User = components['schemas']['UserResponse'];
export type UserListResponse = components['schemas']['UserListResponse'];
export type CreateUserInput = components['schemas']['CreateUserRequest'];
export type UpdateUserInput = components['schemas']['UpdateUserRequest'];
