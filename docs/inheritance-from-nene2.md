# Inheritance from NENE2

NeNe Vault inherits engineering governance from [NENE2](https://github.com/hideyukiMORI/NENE2). This document is the source of truth for what is inherited, what is adapted, and what is NeNe Vault–specific.

## Relationship

| Layer | Repository | Role |
| --- | --- | --- |
| Framework runtime | [NENE2](https://github.com/hideyukiMORI/NENE2) | HTTP runtime, DI, middleware, Problem Details, OpenAPI/MCP patterns |
| Application platform | **NeNe Vault** (this repo) | Received-document archive — upload, search, retention, audit |
| Sibling products | [nene-invoice](https://github.com/hideyukiMORI/nene-invoice), [nene-clear](https://github.com/hideyukiMORI/nene-clear), [nene-profile](https://github.com/hideyukiMORI/nene-profile) | Optional HTTP reference links (no shared DB) |

NeNe Vault is a **consumer project**, not a fork of NENE2. Framework code stays in NENE2; product code stays here.

## Inherited by policy (same rules, local copies)

These policies are adopted with the same intent as NENE2 and sibling NeNe products.

| Topic | Local document |
| --- | --- |
| Issue-driven workflow | `docs/workflow.md` |
| Conventional Commits | `docs/development/commit-conventions.md` |
| Self-review before PR | `docs/development/self-review.md` |
| ADR operation | `docs/development/adr.md` |
| AI agent workflow | `docs/integrations/ai-tools.md`, `AGENTS.md` |
| Cursor summaries | `.cursor/rules/` |

## Inherited by reference (framework behavior)

When implementing HTTP, middleware, validation, or error responses, follow NENE2 upstream docs unless NeNe Vault records an explicit deviation in an ADR.

| Topic | NENE2 upstream |
| --- | --- |
| HTTP runtime (PSR-7/15/17) | `docs/development/http-runtime.md` |
| Middleware order and security | `docs/development/middleware-security.md` |
| Request validation layers | `docs/development/request-validation.md` |
| Problem Details errors | `docs/development/api-error-responses.md` |
| Authentication boundaries | `docs/development/authentication-boundary.md` |
| JWT middleware (`BearerTokenMiddleware`) | `docs/adr/0008-jwt-authentication.md` |
| OpenAPI conventions | `docs/integrations/openapi.md` |
| MCP tool policy | `docs/integrations/mcp-tools.md` |
| Database adapter boundaries | `docs/development/database-migrations.md` |
| Domain / use case layering | `docs/development/domain-layer.md` |
| DI and wiring | `docs/development/dependency-injection.md` |
| Configuration policy | `docs/development/configuration.md` |
| Observability / logging | `docs/development/observability.md` |
| Quality tools (PHPStan, PHP-CS-Fixer) | `docs/development/quality-tools.md` |

Install NENE2 as a Composer dependency and treat `vendor/hideyukimori/nene2/docs/` as the framework reference during development.

## Adapted for NeNe Vault

| Topic | NeNe Vault choice |
| --- | --- |
| Product goal | Received-document archive only — not billing, reconciliation, or CSV |
| Namespace | `NeneVault\` |
| Public Problem Details base URL | `https://nene-vault.dev/problems/` |
| Compliance binding | `docs/explanation/received-document-compliance.md` (電帳法 for received docs) |
| Monetary values | Integer **cents** nullable (`amount_cents`); null when document carries no amount |
| File integrity | SHA-256 hash verified on every download; no byte overwrites |
| Audit trail | UseCase-layer `AuditRecorder` with before/after; same DB transaction as mutation (ADR 0014) |
| Retention | 10-year default from `transaction_date`; no auto-purge (ADR 0004) |
| Coding standards | `docs/development/coding-standards.md` — NENE2 baseline + vault additions |
| Backend standards | `docs/development/backend-standards.md` — PHP/API strict policy |
| Naming conventions | `docs/development/naming-conventions.md` — vault-domain identifiers |
| Language policy | English for public docs, OpenAPI, API error metadata; Japanese allowed in Issues, PRs, commits, `.cursor/rules/` |
| Review checklists | `docs/review/` — vault-specific lists |

## NeNe Vault–specific (not inherited)

Record these in ADRs or product docs when they stabilize:

- Received-document domain model (vault_document, document_version, document_link, audit_event)
- 電子帳簿保存法 compliance posture for received documents (not issued)
- File storage architecture (local filesystem; S3 adapter Phase 4+)
- Admin frontend vs public download boundaries
- MCP read tools (`searchVaultDocuments`, `getVaultDocument`)
- Optional email inbound (Phase 3+)
- OCR assist — suggest-only, human confirm (Phase 4+)

## When upstream and local docs conflict

1. Update the **local source-of-truth doc** in this repository first.
2. If the conflict is about **framework behavior**, prefer NENE2 upstream unless an ADR documents a deliberate deviation.
3. If the conflict touches compliance behavior, treat it as a P0 issue — do not resolve by guessing.
4. Keep `.cursor/rules/` as a short summary; do not duplicate full policy text there.

## Verification commands (once runtime is scaffolded)

```bash
composer check
composer openapi
composer mcp
```

When `frontend/` exists (Phase 2+):

```bash
npm run check --prefix frontend
```
