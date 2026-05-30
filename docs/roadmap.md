# Roadmap

NeNe Vault — **received-document archive** on NENE2.
See [ADR 0009](./adr/0009-separate-from-billing-and-reconciliation.md).

## North Star

Operators self-host Vault to store and search **received** vendor documents with
電子帳簿保存法-oriented integrity — without bundled storage-only SaaS subscriptions.

## Phase 0: Governance and Foundation

- Governance docs, ADR 0001–0006/0008–0014 ✅
- Product vision, scope contract, compliance doc ✅
- NENE2 scaffold + `GET /health` 🔲 Issue #4+
- 税理士 review gate before Phase 2 UI 🔲

## Phase 1: Document API

- Multi-tenant + JWT + RBAC (ADR 0006)
- Upload, metadata, search, void, version history
- Local filesystem storage (ADR 0012)
- OpenAPI + PHPUnit + PHPStan 8

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

Last updated: 2026-05-29
