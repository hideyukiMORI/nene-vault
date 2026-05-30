# Naming Conventions

Authoritative naming rules for NeNe Vault code, API contracts, database objects, tests, and documentation.

> **Absolute adherence — non-negotiable.** These rules are **MUST**, not
> suggestions. A name that violates a rule here, or a spelling variant of a
> registered term, is a defect and **blocks merge**. There is no "close enough."
> When in doubt, match the registry exactly.
>
> The canonical spelling of every term and identifier lives in the **single
> source of truth**: [`../explanation/terminology.md`](../explanation/terminology.md).
> This document defines the *patterns*; the registry defines the *exact strings*.
> Introducing or renaming any identifier **MUST** update the registry in the same PR.

**Terminology registry:** [`docs/explanation/terminology.md`](../explanation/terminology.md)
**Glossary (meanings):** [`docs/explanation/glossary.md`](../explanation/glossary.md)
**Framework baseline:** NENE2 [`domain-layer.md`](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/domain-layer.md) and [`database-migrations.md`](https://github.com/hideyukiMORI/NENE2/blob/main/docs/development/database-migrations.md).

---

## 1. PHP

### Files and namespaces

| Item | Rule | Example |
| --- | --- | --- |
| Namespace root | `NeneVault\` | `NeneVault\Document\UploadDocumentHandler` |
| Domain folder | PascalCase singular domain name | `src/Document/`, `src/Audit/` |
| File name | Match the primary class | `UploadDocumentHandler.php` |
| One public class per file | Required | — |

### Classes and interfaces

| Role | Pattern | Example |
| --- | --- | --- |
| HTTP handler | `{Verb}{Noun}Handler` | `UploadDocumentHandler`, `SearchDocumentsHandler` |
| Use case interface | `{Verb}{Noun}UseCaseInterface` | `UploadDocumentUseCaseInterface` |
| Use case impl | `{Verb}{Noun}UseCase` | `UploadDocumentUseCase` |
| Use case method | Always `execute` | `execute(UploadDocumentInput $input): UploadDocumentOutput` |
| Input DTO | `{Verb}{Noun}Input` | `UploadDocumentInput` |
| Output DTO | `{Verb}{Noun}Output` | `UploadDocumentOutput` |
| Domain entity | Singular noun, no suffix | `VaultDocument`, `DocumentVersion`, `AuditEvent` |
| Repository interface | `{Entity}RepositoryInterface` | `VaultDocumentRepositoryInterface` |
| PDO repository | `Pdo{Entity}Repository` | `PdoVaultDocumentRepository` |
| Storage adapter | `{Backend}{Purpose}Storage` in `DocumentVersion/` | `LocalFilesystemDocumentStorage` |
| Storage interface | `DocumentStorageInterface` | — |
| Audit recorder | `AuditRecorder` in `Audit/` | — |
| Domain exception | `{Entity}{Reason}Exception` | `VaultDocumentNotFoundException`, `RetentionWindowActiveException` |
| Service provider | `{Purpose}ServiceProvider` | `RuntimeServiceProvider`, `DocumentServiceProvider` |

All application classes: `final` and `readonly` where applicable. Every PHP file: `declare(strict_types=1);`.

### Modules (`src/`)

Use only domain-grouped top-level folders. Do not add layer folders (`Handlers/`, `Repositories/`, `UseCases/`).

Planned domains: `Organization/`, `Auth/`, `User/`, `VaultSettings/`, `Document/`, `DocumentVersion/`, `DocumentLink/`, `Audit/`, `Export/`, `Integration/SiblingLink/`, `Http/`.

### Methods and properties

| Item | Rule | Example |
| --- | --- | --- |
| Methods | camelCase | `findById`, `voidDocument`, `computeRetentionExpiry` |
| Properties | camelCase | `$vaultDocumentId`, `$auditRecorder` |
| Constants | UPPER_SNAKE_CASE | `MAX_FILE_SIZE_BYTES`, `SHA256_HEX_LENGTH` |

Repository methods use **domain verbs**: `findById`, `save`, `void`, `search` — not `selectById`, `insertRow`.

---

## 2. HTTP routes and OpenAPI

### URL paths

| Item | Rule | Example |
| --- | --- | --- |
| Path segments | lowercase **kebab-case** | `/admin/vault/documents`, `/admin/vault/settings` |
| Collection paths | plural noun | `/admin/vault/documents` |
| Single resource | `{id}` path param | `/admin/vault/documents/{id}` |
| Sub-resources | nested noun | `/admin/vault/documents/{id}/history`, `/admin/vault/documents/{id}/versions` |
| File download | noun path | `/admin/vault/documents/{id}/versions/{versionId}/download` |
| Health | `GET /health` | unauthenticated |

Admin mutating routes live under `/admin/…`.

### operationId

| Item | Rule | Example |
| --- | --- | --- |
| Case | camelCase | `uploadDocument`, `searchDocuments`, `voidDocument` |
| Shape | `{verb}{Resource}` or `{verb}{Resource}ById` | `listDocuments`, `getDocumentById` |
| Stability | Never rename after release; deprecate instead | — |

Must match between `docs/openapi/openapi.yaml`, route registration, and `docs/mcp/tools.json`.

### OpenAPI schema names

| Item | Rule | Example |
| --- | --- | --- |
| Response schema | `{Resource}Response` | `VaultDocumentResponse` |
| List response | `{Resource}ListResponse` | `VaultDocumentListResponse` |
| Create/upload request | `Upload{Resource}Request` | `UploadDocumentRequest` |
| Patch request | `Update{Resource}MetadataRequest` | `UpdateDocumentMetadataRequest` |
| Tag names | PascalCase singular group | `System`, `Admin`, `Document`, `Audit`, `Export` |

Public OpenAPI summaries, descriptions, and examples: **English only**.

---

## 3. JSON (request and response bodies)

| Item | Rule | Example |
| --- | --- | --- |
| Property names | **snake_case** | `transaction_date`, `amount_cents`, `counterparty_name` |
| Money amounts | nullable integer **cents** | `amount_cents` (null if no amount) |
| Booleans | `is_` / `has_` prefix | `is_metadata_confirmed`, `date_uncertain` |
| Timestamps | `_at` suffix, ISO 8601 string | `uploaded_at`, `voided_at`, `retention_expires_at` |
| Dates | `_date` suffix, ISO 8601 date string | `transaction_date` |
| Foreign keys | `{entity}_id` | `organization_id`, `vault_document_id` |
| Status enum | `status` field | `"active"`, `"voided"` |
| Source enum | `source` field | `"web_upload"`, `"email_inbound"`, `"api"`, `"scan_upload"` |
| List envelope | `items`, `limit`, `offset` | Same as NENE2 list pattern |
| File hash | `file_sha256` | 64-char hex string |

Do not mix camelCase in public JSON. Do not use floats for money. Do not expose storage paths.

---

## 4. Problem Details and validation errors

| Item | Rule | Example |
| --- | --- | --- |
| Base URL | `https://nene-vault.dev/problems/` | — |
| Type slug | kebab-case | `validation-failed`, `document-not-found`, `retention-window-active` |
| Validation `errors[].field` | snake_case path | `body.transaction_date`, `body.amount_cents` |
| Validation `errors[].code` | snake_case | `required`, `invalid_date_format`, `mime_type_not_allowed` |

Problem Details `title` and `detail`: English.

---

## 5. Database

| Item | Rule | Example |
| --- | --- | --- |
| Table names | snake_case, **plural** | `vault_documents`, `document_versions`, `document_links`, `audit_events`, `vault_settings` |
| Column names | snake_case | `transaction_date`, `amount_cents`, `file_sha256` |
| Money columns | `*_cents` suffix, nullable integer | `amount_cents` |
| Hash columns | `*_sha256` suffix, `VARCHAR(64)` | `file_sha256` |
| Primary key | `id` | ULID stored as CHAR(26) or UUID CHAR(36) |
| Foreign key column | `{singular_entity}_id` | `vault_document_id`, `organization_id` |
| Status column | `status` ENUM | `'active'`, `'voided'` |
| Soft void | `voided_at`, `voided_by`, `void_reason` | — |
| Retention | `retention_years INT`, `retention_expires_at DATE` | — |
| Index names | `idx_{table}_{columns}` | `idx_vault_documents_org_date` |
| Unique constraints | `uniq_{table}_{columns}` | `uniq_document_versions_doc_version` |
| Audit table | `audit_events` | Append-only; no soft delete column |

SQL lives only in `Pdo*Repository` and `LocalFilesystemDocumentStorage` classes.

### Migrations

| Item | Rule | Example |
| --- | --- | --- |
| File name | `YYYYMMDDHHMMSS_snake_description.php` | `20260601120000_create_vault_documents_table.php` |
| Snapshot file | `database/schema/{table}.sql` | `database/schema/vault_documents.sql` |

---

## 6. Environment variables

| Item | Rule | Example |
| --- | --- | --- |
| Names | UPPER_SNAKE_CASE | `DB_HOST`, `NENE_VAULT_STORAGE_PATH` |
| Vault prefix | `NENE_VAULT_` for vault-specific | `NENE_VAULT_STORAGE_PATH`, `NENE_VAULT_PORT` |
| Secrets | Never commit; document in `.env.example` only | `NENE_VAULT_JWT_SECRET` |

---

## 7. Tests

| Item | Rule | Example |
| --- | --- | --- |
| Test class | `{ClassUnderTest}Test` | `UploadDocumentUseCaseTest` |
| Test method | `test_{behavior}_when_{condition}` | `test_rejects_upload_when_mime_type_not_allowed` |
| Test namespace | Mirror `src/` under `tests/` | `tests/Document/UploadDocumentUseCaseTest.php` |
| In-memory repo | `InMemory{Entity}Repository` in `tests/` | `InMemoryVaultDocumentRepository` |

Compliance-critical tests (retention block, hash verification, audit write, org scoping) must never be skipped.

---

## 8. MCP tools

| Item | Rule | Example |
| --- | --- | --- |
| Tool `name` | Same as OpenAPI `operationId` | `searchDocuments`, `getDocumentById` |
| Tool `title` | Short English Title Case | `Search Documents`, `Get Document` |
| `safety` | `read` or `write` | `read` for search/get; `write` for upload/void |

Catalog: `docs/mcp/tools.json`. Phase 4+ (read tools first per roadmap).

---

## 9. Frontend (Phase 2+)

| Item | Rule |
| --- | --- |
| Components | PascalCase file and export |
| Hooks | camelCase with `use` prefix |
| API client | Maps snake_case JSON; do not rename API fields in transit |
| Admin SPA | React + TypeScript strict mode |
| Locale keys | snake_case; `ja` primary, `en` secondary (ADR 0005) |

Full frontend standards: `docs/development/frontend-standards.md` (Phase 2).

---

## 10. Documentation and commits

| Surface | Language | Naming |
| --- | --- | --- |
| Public docs, OpenAPI, API errors | English | Use glossary canonical terms |
| Issues, PRs, commit bodies | Japanese allowed | Prefer glossary English term on first mention |
| Commit subject | Conventional Commits + `(#issue)` | See [`commit-conventions.md`](./commit-conventions.md) |
| ADR file | `NNNN-kebab-title.md` | `0004-retention-period-calculation.md` |

When adding or renaming any identifier, update [`terminology.md`](../explanation/terminology.md) in the same PR; if it is a product concept, also update [`glossary.md`](../explanation/glossary.md).

---

## 11. Prohibited patterns

- **Typos or spelling variants of any term registered in `terminology.md`** — blocks merge
- **Unregistered identifiers** — using an entity, status, field, slug, or `operationId` not in `terminology.md` without adding it in the same PR
- Layer-first folders (`src/Handlers/`, `src/Repositories/`, `src/UseCases/`)
- SQL outside `Pdo*Repository` classes
- File I/O outside `DocumentStorageInterface` implementations
- camelCase in public JSON property names
- Float or DECIMAL for money
- Exposing storage paths in API responses
- Renaming shipped `operationId` values
- Embedding file storage logic in repository classes
- Hard deleting documents during the retention window
- Writing audit events outside `AuditRecorder`
- Skipping org scoping on tenant-scoped queries

---

## Verification

```bash
composer check
composer openapi
composer mcp
```

Review checklists: [`docs/review/`](../review/).
