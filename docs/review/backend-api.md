# Backend API Self-Review Checklist

Use for: endpoints, handlers, validation, HTTP behavior, Problem Details errors.

---

## Handler

- [ ] Handler is thin: parses HTTP input, builds DTO, calls UseCase, maps JSON response.
- [ ] No business logic, SQL, or file I/O in the handler.
- [ ] No direct repository calls from the handler.
- [ ] Handler receives UseCase via constructor injection, typed to the interface.

## Input DTO and validation

- [ ] Input is a `final readonly` DTO.
- [ ] Format validation (type, length, required) happens in the handler before calling the UseCase.
- [ ] Business invariants are validated in the UseCase, not the handler.
- [ ] `transaction_date` is validated as a valid ISO 8601 date.
- [ ] `amount_cents` is validated as a nullable integer (never float).
- [ ] MIME type and file size are validated at the handler layer before the UseCase.

## UseCase

- [ ] UseCase implements its interface.
- [ ] Method is always named `execute`.
- [ ] UseCase has no HTTP, `$_SERVER`, PDO, or raw file I/O knowledge.
- [ ] AuditRecorder is called in the same DB transaction as the mutation (if applicable).

## Response

- [ ] Response is JSON with `snake_case` property names.
- [ ] Money amounts are integer cents — never float.
- [ ] Storage paths are not in the response.
- [ ] Timestamps use ISO 8601 format.
- [ ] List responses use `{ items: [...], limit: N, offset: N }` envelope.
- [ ] `status` 200/201/204 is correct for the operation.

## Problem Details errors

- [ ] Error responses use `application/problem+json`.
- [ ] `type` is a stable `https://nene-vault.dev/problems/…` URI.
- [ ] `title` and `detail` are English.
- [ ] No stack traces, SQL, file paths, or storage paths in error responses.
- [ ] Validation failures use `validation-failed` with `errors` array.
- [ ] `errors[].field` uses snake_case path; `errors[].code` uses snake_case.

## Authentication and authorization

- [ ] Mutating routes require JWT Bearer auth.
- [ ] Required capability is enforced by `CapabilityMiddleware`.
- [ ] `organization_id` is resolved from middleware context, not from request body.
- [ ] Superadmin cross-tenant operations are explicitly checked.

## OpenAPI

- [ ] New or changed route has a corresponding entry in `docs/openapi/openapi.yaml`.
- [ ] `operationId` is stable camelCase matching the handler name convention.
- [ ] Success and Problem Details schemas are documented.

---

## Verification

```bash
composer check
composer openapi
```
