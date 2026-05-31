# Roadmap

NeNe Vault — **received-document archive** on NENE2.
See [ADR 0009](./adr/0009-separate-from-billing-and-reconciliation.md).

## North Star

Operators self-host Vault to store and search **received** vendor documents with
電子帳簿保存法-oriented integrity — without bundled storage-only SaaS subscriptions.

## Phase 0: Governance and Foundation

- Governance docs, ADR 0001–0006/0008–0014 ✅
- Product vision, scope contract, compliance doc ✅
- NENE2 scaffold + `GET /health` ✅ (PR #8)
- 税理士 review gate before Phase 2 UI 🔲

## Phase 1: Document API

- Multi-tenant + JWT + RBAC (ADR 0006) ✅ (PR #8)
- Audit logging — before/after on all mutations (ADR 0014) ✅ (PR #10)
- ja/en locale files (ADR 0005) ✅ (PR #12)
- OpenAPI 3.1 contract — all Phase 1 endpoints ✅ (PR #14)
- Document upload + storage foundation + get detail ✅ (PR #16)
- Document search — date/amount/counterparty/category ✅ (PR #18)
- Metadata edit + void/restore + history (audited) ✅ (PR #20)
- Version download — authenticated, SHA-256 verified ✅ (PR #22)
- Local filesystem storage (ADR 0012) ✅ (PR #16)
- PHPUnit + PHPStan 8 ✅ (gate established)

**Phase 1 Document API complete.** Remaining: user CRUD endpoints, manifest export
(Phase 2), 税理士 review gate before Phase 2 UI.

## Phase 2: Admin UI + Export

- Document list, search UI, upload wizard
- Metadata edit with audit preview
- Manifest CSV + ZIP export
- ja + en UI (ADR 0005)
- Optional Invoice/Clear link UI

## Phase 3: Tier A Shared Hosting

- Web installer + release ZIP
- Operator guide (backup, retention, search)
- Beside Invoice/Clear/Profile on same server

## Phase 4: Ecosystem

- MCP read tools (`searchVaultDocuments`, `getVaultDocument`)
- Optional email inbound
- OCR assist (suggest-only, human confirm)
- Optional S3 storage adapter

## Not on this roadmap

- Expense reimbursement workflows
- Bank CSV / Profile presets
- Reconciliation / dunning
- Issued invoice generation

See [`docs/explanation/scope-boundary.md`](./explanation/scope-boundary.md).

Last updated: 2026-05-31
