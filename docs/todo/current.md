# Current TODO

**All roadmap phases (0–4) complete; compliance gate approved. Remaining work is the
pre-production go-live gate (税理士 Review 3) and Tier A live testing.**

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

## Compliance gate — Resolved ✅

- [x] **税理士 review gate** — licensed professional sign-off recorded 2026-05-31
      (辻村 拓也 / 辻村総合会計事務所 / 公認会計士・税理士). Gate status: 🟢 Approved.
      See `docs/compliance-review/signoff-record.md`.
      **Condition:** pre-production Review 3 required before operators go live.

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

## Phase 4 — Done

- [x] MCP read tools — `searchVaultDocuments`, `getVaultDocumentById`, `getVaultDocumentHistory`, `listVaultAuditEvents` (PR #70)
- [x] MCP write tools + OCR/export integration guide (PR #80)
- [x] S3-compatible storage adapter — `S3DocumentStorage` + Sig V4, select via `NENE_VAULT_STORAGE_ADAPTER=s3` (PR #72)
- [x] Optional email inbound (mailbox → auto-upload via IMAP/MIME parser) — `src/Email/` + `tools/email-inbound.php` (PR #74)
- [x] OCR assist — suggest metadata from PDF/image, human confirm (PR #76)

## Go-live gate — Open 🔲

These are the only items between the current (complete, all-green) codebase and
production use by operators. Both come from Review 2's recorded conditions
(`docs/compliance-review/signoff-record.md`).

- [ ] **税理士 Review 3** — pre-production final verification of test-environment
      output samples (manifest CSV, export ZIP, retention dates) before any operator
      goes live. Engineering package: `docs/compliance-review/2026-review-3-preprod-package.md`.
- [ ] **Tier A live testing** — verify `install.php` + release ZIP on real shared
      hosting alongside sibling products (roadmap Phase 0/3 "pending Tier A testing").
- [ ] **Standing P0 watch** — on any 電帳法 amendment / 国税庁 guidance, open a P0
      Issue and add a new review block to `signoff-record.md` (Review 2 condition 2).

Last updated: 2026-06-01
