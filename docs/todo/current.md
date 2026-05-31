# Current TODO

**Phase 1 — Document API complete; compliance review gate open.**

## Done

- [x] Governance bootstrap, product definition (PR #1, #2)
- [x] Coding standards + review checklists (PR #4)
- [x] `docs/terms.md` canonical identifier registry (PR #6)
- [x] Multi-tenancy foundation — Auth / Organization / VaultSettings (PR #8)
- [x] Audit logging — before/after on all mutations (PR #10)
- [x] ja/en locale files (PR #12)
- [x] OpenAPI 3.1 contract — all Phase 1 endpoints (PR #14)
- [x] Document upload + storage + get detail (PR #16)
- [x] Document search — date/amount/counterparty/category (PR #18)
- [x] Metadata edit + void/restore + history (PR #20)
- [x] Version download — SHA-256 verified (PR #22)
- [x] User CRUD endpoints (PR #24)
- [x] Manifest export — CSV (PR #26)
- [x] Tax advisor review package prepared (Issue #27)

## In progress / gating

- [ ] **税理士 review gate** — maintainer-approved for Phase 2 development; licensed
      professional sign-off still required before production use.
      See `docs/compliance-review/signoff-record.md`.

## Phase 2 — Done

- [x] Admin UI scaffold — React + Vite + ja/en locale integration (PR #31, #37)
- [x] Frontend pages — Documents, Detail, Upload, Audit, Users, Settings, Export (PR #39–#48)
- [x] Frontend tests — MSW + unit coverage (PR #51–#56)
- [x] Docker Compose dev environment (PR #57, #58)
- [x] Export ZIP bundling — manifest CSV + document files in single archive (PR #64)

## Phase 3 — Done

- [x] Operator guide (storage, backup, retention, search, export) — `docs/operator/` (PR #66)
- [x] 事務処理規程 template — `docs/operator/jimu-shokirei-template.md` (PR #66)
- [x] Web installer + release ZIP (Tier A shared hosting) — `install.php` + `tools/build-release.sh` (PR #68)

## Phase 4 — In progress

- [x] MCP read tools — `searchVaultDocuments`, `getVaultDocumentById`, `getVaultDocumentHistory`, `listVaultAuditEvents` (PR #70)
- [x] S3-compatible storage adapter — `S3DocumentStorage` + Sig V4, select via `NENE_VAULT_STORAGE_ADAPTER=s3` (PR #72)
- [ ] Optional email inbound (mailbox → auto-upload via IMAP/MIME parser)
- [ ] OCR assist — suggest metadata from PDF/image, human confirm (Phase 4+)

Last updated: 2026-05-31
