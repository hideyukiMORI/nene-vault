# OpenAPI Contract Self-Review Checklist

Use for: OpenAPI schema changes, new endpoints, examples, contract tests.

---

## Structure

- [ ] `docs/openapi/openapi.yaml` is valid OpenAPI 3.1.
- [ ] Every public route has a corresponding operation with a stable `operationId`.
- [ ] `operationId` is camelCase and matches the handler naming convention.
- [ ] Tags are PascalCase singular group names (`System`, `Admin`, `Document`, `Audit`, `Export`).

## Schemas

- [ ] Request schemas: required fields marked, types correct, `amount_cents` is nullable integer.
- [ ] Response schemas: snake_case properties, no storage paths.
- [ ] List responses use `{ items: [...], limit: integer, offset: integer }` envelope.
- [ ] `file_sha256` is `type: string, pattern: ^[0-9a-f]{64}$`.
- [ ] Status enum values match `terminology.md`.
- [ ] Money amounts are `type: integer, nullable: true` — never `number`.

## Problem Details

- [ ] Non-2xx responses reference shared `ProblemDetails` schema (once created).
- [ ] `type` URIs use `https://nene-vault.dev/problems/` base.
- [ ] `401`, `403`, `404`, `413`, `415`, `422`, `500` are documented for applicable routes.
- [ ] Validation errors reference `ValidationProblemDetails` schema (once created).

## Examples

- [ ] `200` example is structurally valid against the response schema.
- [ ] Examples do not contain real secrets, real user data, or real storage paths.
- [ ] `file_sha256` in examples is a valid 64-char hex string.

## Security

- [ ] Mutating routes declare `security: [{ bearerAuth: [] }]`.
- [ ] `GET /health` is explicitly listed as unauthenticated.

## Contract tests

- [ ] `composer openapi` passes (OpenAPI document is valid).
- [ ] Runtime contract tests pass for documented `200` examples.

---

## Verification

```bash
composer openapi
composer check
```
