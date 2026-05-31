# Backend Standards

NeNe Vault backend policy for PHP API code. Adapted from NeNe Invoice backend standards for a **received-document archive** on NENE2.

**Framework baseline:** NENE2 `docs/development/` — deviate only via local ADR.

**Naming and terms:** [`naming-conventions.md`](./naming-conventions.md), [`glossary.md`](../explanation/glossary.md).

**Compliance (binding):** [`../explanation/received-document-compliance.md`](../explanation/received-document-compliance.md) — run [`../review/compliance.md`](../review/compliance.md) for any change in this area.

---

## 1. Project shape

NeNe Vault is a **NENE2 consumer project**:

```
vendor/hideyukimori/nene2/   ← framework (do not edit)
src/                         ← product code (NeneVault\)
tests/                       ← mirrors src/
docs/openapi/openapi.yaml    ← public contract
public_html/index.php        ← front controller
```

Namespace: `NeneVault\`

---

## 2. Module layout (domain-grouped)

Organize by **domain concept**, not technical layer:

```
src/
  ApplicationServiceProvider.php
  Http/                      # PSR-7/15 helpers (thin wrappers if needed)
  Organization/              # tenants + per-request resolution (Organization/Resolution/)
  Auth/                      # JWT login, Role / Capability, CapabilityMiddleware
  User/                      # operator accounts within an organization
  VaultSettings/             # retention_years, storage_path, sibling link config
  Document/                  # vault_document CRUD, upload, void/restore, search
  DocumentVersion/           # file storage, version creation, SHA-256 hash verification
  DocumentLink/              # optional HTTP reference links to Invoice/Clear
  Audit/                     # AuditRecorder, audit_events write; history read
  Export/                    # manifest CSV + ZIP export
  Integration/
    SiblingLink/             # optional HTTP clients for Invoice/Clear link validation
```

**Zero-tolerance placement:** handlers live in their domain folder (`Document/UploadDocumentHandler.php`), not `src/Handlers/`.

---

## 3. Layering rules

```
Handler → UseCase → RepositoryInterface → PdoRepository
```

| Layer | May | Must not |
| --- | --- | --- |
| **Handler** | Parse HTTP request, build DTO, call UseCase, map JSON response | SQL, business rules, file I/O, direct audit writes |
| **UseCase** | Business rules, file hash computation, orchestration, call AuditRecorder | `$_SERVER`, PDO, raw HTTP, direct file storage |
| **Repository** | SQL / persistence; file metadata lookups | HTTP, file I/O (delegate to `DocumentStorageInterface`) |
| **DocumentStorageInterface** | File I/O, path computation, hash verification | Business rules, audit |
| **AuditRecorder** | Write `audit_events` in same DB transaction as mutation | Business rules |

Use `final readonly` classes and `declare(strict_types=1);` in every PHP file.

---

## 4. HTTP and OpenAPI

- Every public route appears in `docs/openapi/openapi.yaml` with a stable `operationId`.
- Success and Problem Details error shapes documented.
- RFC 9457 Problem Details for errors; base URL `https://nene-vault.dev/problems/`.
- Admin routes require JWT Bearer auth (Phase 1+).
- Authenticated file download endpoint — never direct public URL to storage path.

---

## 5. Files, storage, and integrity

> **Compliance is binding (non-negotiable).** All file storage, hash verification,
> void/version, and retention behavior **MUST** comply with
> [`../explanation/received-document-compliance.md`](../explanation/received-document-compliance.md).
> Where compliance conflicts with convenience, compliance wins. Deviations
> require an ADR with professional sign-off. Run
> [`../review/compliance.md`](../review/compliance.md) for any change in this area.

- Store files via `DocumentStorageInterface`. The local filesystem adapter is Phase 1–3; S3 adapter is Phase 4+ (ADR 0012).
- Storage path: `{NENE_VAULT_STORAGE_PATH}/vault/{organization_id}/{document_id}/v{version_number}/{sanitized_filename}` (ADR 0012). Path **MUST NOT** appear in API responses.
- Compute SHA-256 at upload. Verify SHA-256 on every download. Hash mismatch = P0 defect.
- MIME allowlist: `application/pdf`, `image/jpeg`, `image/png`. Reject others with `415 Unsupported Media Type`.
- Max file size: configurable per org via `vault_settings`; default 20 MB.
- **No byte overwrites** — replacement creates a new `document_version`.
- Duplicate detection: same `file_sha256` within `organization_id` → warn operator before accepting.

---

## 6. Money and date

- `amount_cents`: **nullable integer** in JPY. `null` = document carries no monetary value. Float/DECIMAL prohibited.
- `transaction_date` (DATE) is distinct from `uploaded_at` (TIMESTAMP). Do not conflate.
- `retention_expires_at` = `transaction_date + retention_years` (computed at upload; see ADR 0004).
- Default `retention_years = 10` (not 7 — see ADR 0004).

---

## 7. Audit trail

> See ADR 0014 for schema and full rules.

- Every mutating UseCase **MUST** call `Audit\AuditRecorder` in the **same DB transaction** as the mutation.
- Capture "before" state before the mutation; "after" state after.
- `audit_events` rows: INSERT only — no UPDATE or DELETE in application code.
- DB user has INSERT on `audit_events`, NOT UPDATE or DELETE.

---

## 8. Multi-tenancy

- Every repository query on a tenant-scoped table **MUST** include `organization_id`.
- `OrgResolverMiddleware` runs before authorization; resolved org id is in request-scoped context.
- Cross-tenant reads are prohibited — only superadmin (`organization_id = NULL`) operates cross-tenant.
- Missing `organization_id` in a query is a P0 security defect.

---

## 9. Database

- Phinx migrations under `database/migrations/`.
- Schema snapshots under `database/schema/`.
- Soft delete: `status = 'voided'` + `voided_at` (not `is_deleted`) for `vault_documents`. Full hard delete is prohibited during retention window.
- `audit_events`: append-only, no soft delete column.

---

## 10. Testing

- UseCase tests: no DB — inject repository fakes or in-memory implementations.
- Repository tests: real SQL against test database (PDO adapter with SQLite in-memory for speed; MySQL for integration).
- HTTP/contract tests: assert against OpenAPI shapes.
- Compliance-critical tests (retention, audit, hash): treat failures as P0 — must never be skipped.

---

## 11. Verification

```bash
composer check
composer openapi
```

Self-review checklists: [`docs/review/compliance.md`](../review/compliance.md), [`docs/review/backend-api.md`](../review/backend-api.md), [`docs/review/database.md`](../review/database.md).
