# Middleware and Security Self-Review Checklist

Use for: auth, JWT, org resolution, CORS, rate limits, logging, security headers.

---

## Authentication

- [ ] Mutating routes (`POST`, `PATCH`, `DELETE`) require `Authorization: Bearer <token>`.
- [ ] `BearerTokenMiddleware` (NENE2) is in the middleware pipeline before routing.
- [ ] JWT verification uses `TokenVerifierInterface` — no raw library call in handlers.
- [ ] `401 Unauthorized` uses `application/problem+json` with `WWW-Authenticate: Bearer`.
- [ ] JWT secret is loaded from `NENE_VAULT_JWT_SECRET` env — never hardcoded.

## Authorization (capabilities)

- [ ] Required capability is enforced by `CapabilityMiddleware` per route.
- [ ] `403 Forbidden` uses `application/problem+json`.
- [ ] `manage_vault_settings` is required for retention changes.
- [ ] `upload_document` is required for uploads.
- [ ] `void_document` is required for void / restore.
- [ ] `export_documents` is required for manifest export.
- [ ] `view_documents` is required for search, download, and history.

## Organization resolution

- [ ] `OrgResolverMiddleware` runs before authorization in the middleware pipeline.
- [ ] `organization_id` is extracted from the resolved org context, not from request body or path param.
- [ ] A request with no resolvable org returns 401 or 404 (not a server error).
- [ ] Superadmin (`organization_id = NULL`) cross-tenant operations are explicitly handled.

## Request scoping

- [ ] Every handler uses the org context from middleware — never the raw JWT claim.
- [ ] Path `{id}` parameters are always cross-checked against the resolved `organization_id`.

## Security headers

- [ ] `X-Content-Type-Options: nosniff` is set.
- [ ] `X-Frame-Options: DENY` is set.
- [ ] No `Server` header revealing implementation details.

## File download security

- [ ] File download endpoint requires authentication.
- [ ] File is served via application-layer streaming — not by redirecting to the storage path.
- [ ] Storage path is not visible in the download URL or response headers.
- [ ] Download validates `organization_id` ownership before serving.

## Logging

- [ ] No secrets, tokens, authorization headers, or file paths are logged.
- [ ] Logs include `request_id` for correlation.
- [ ] Failed auth attempts are logged (credential type, failure reason category — not the credential value).

## CORS

- [ ] CORS allowed origins are configured explicitly — not `*` in production.
- [ ] Pre-flight OPTIONS requests are handled correctly.

---

## Verification

```bash
composer check
```
