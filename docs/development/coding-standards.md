# Coding Standards

NeNe Vault inherits the NENE2 coding standards. This document is the local override and extension list for received-document archive specifics.

**Framework baseline:** [NENE2 `docs/development/coding-standards.md`](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/coding-standards.md). Follow all NENE2 rules unless this document says otherwise.

---

## PHP Baseline (inherited from NENE2)

- Target PHP `>=8.4.1 <9.0`.
- Every PHP file: `declare(strict_types=1);`
- Follow PSR-12 unless a narrower project rule says otherwise.
- No large file-level copyright or project banners.
- Prefer immutable value objects and `readonly` properties where they clarify intent.
- Use native types, enums, and small DTOs instead of unstructured arrays at boundaries.
- Avoid framework magic that hides control flow from tests, static analysis, or AI tools.

## Architecture (inherited from NENE2)

```
Handler → UseCase → RepositoryInterface → PdoRepository
```

- Use cases are independent from HTTP, database, templates, and frontend.
- Depend on interfaces at infrastructure boundaries.
- Constructor injection for all required dependencies.
- Typed config objects at runtime — never pass raw arrays through the application.
- Readonly DTOs or command objects for use case input boundaries.
- `getenv()`, `$_ENV`, `$_SERVER` only inside the config loading boundary (`src/Config/`).
- PSR-11 as the container boundary; PSR-3 as the logging boundary.
- Explicit factories and service providers over autowiring.
- No container as a service locator inside domain or use-case code.
- Handlers stay thin: parse input → call use case → return response.
- Persistence details stay inside repositories.

## HTTP Runtime (inherited from NENE2)

- PSR-7 for request/response, PSR-15 for middleware, PSR-17 for factories.
- Routing is explicit and readable.
- Middleware order is explicit and documented.
- Front controller: `public_html/index.php`.
- CORS, security headers, request id, and request size limits are API baseline middleware.

## Error Handling (inherited from NENE2)

- RFC 9457 Problem Details for public JSON API errors (`application/problem+json`).
- Problem Details base URL: `https://nene-vault.dev/problems/`
- Validation failures: `validation-failed` Problem Details with structured `errors` array.
- No stack traces, SQL, file paths, secrets, or private identifiers in public error responses.
- Named domain exceptions for business invariant violations.
- Map domain exceptions to Problem Details at the HTTP error boundary — not inside use cases.

## NeNe Vault–specific rules

### Compliance is non-negotiable

Any change that touches **document storage, file serving, search, metadata editing, audit logging, retention, void/restore, or export** MUST be reviewed against `docs/explanation/received-document-compliance.md` and `docs/review/compliance.md`. Deviations require an ADR with professional sign-off.

### File integrity

- File bytes **MUST NOT** be overwritten or deleted without the retention-expiry procedure.
- SHA-256 hash **MUST** be computed at upload and verified at every download.
- If hash verification fails on download, it is a P0 defect — return `500`, log, alert.

### Money

- `amount_cents`: **nullable integer** in JPY. `null` means the document carries no monetary value.
- **Float and DECIMAL for money are prohibited** in DB, API JSON, and tests.
- Do not require `amount_cents` for document creation.

### Audit trail

- Every mutating use case **MUST** call `AuditRecorder` in the **same DB transaction** as the mutation.
- Before-state **MUST** be captured before the mutation; after-state after.
- `audit_events` rows are never updated or deleted by application code.

### Organization scoping

- Every repository query on a tenant-scoped table **MUST** include `organization_id` in the WHERE clause.
- Cross-tenant reads are prohibited — only superadmin operates cross-tenant.
- Forgetting `organization_id` in a query is a P0 security defect.

### File serving

- File bytes are served via an authenticated download endpoint — never via a direct public URL to the storage path.
- The storage path layout (`{org_id}/{document_id}/v{version}/…`) **MUST NOT** be exposed in API responses.

## Testing

- Use case tests: no database — inject repository fakes or in-memory implementations.
- Repository tests: real SQL against test database (PDO SQLite in-memory for unit speed; MySQL for integration).
- HTTP tests: contract tests against OpenAPI shapes.
- File integrity tests: verify hash computation, duplicate detection, and hash mismatch detection.
- Retention tests: verify purge is blocked before `retention_expires_at`.
- Audit tests: verify every mutating use case produces the expected `audit_event`.

## Static Analysis and Formatting

```bash
composer check          # test + analyse + cs
composer test
composer analyse        # PHPStan level 8
composer cs             # PHP-CS-Fixer dry run
composer cs:fix         # PHP-CS-Fixer apply
```

PHPStan level: **8** minimum.

## AI Readability

- Name files and classes after their role.
- Keep functions short enough to inspect without jumping through many layers.
- Explicit return types and simple data shapes.
- Record architecture decisions in `docs/adr/` when they affect future implementation.
