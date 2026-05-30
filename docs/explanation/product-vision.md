# Product Vision

> **Product name:** **NeNe Vault** — see [`philosophy.md`](./philosophy.md),
> [ADR 0007](../adr/0007-product-identity-nene-vault.md), [ADR 0013](../adr/0013-no-third-party-product-names.md).

NeNe Vault is a self-hosted **received-document archive** on
[NENE2](https://github.com/hideyukiMORI/NENE2). It exists so Japan SMB operators
can **stop paying for storage-only SaaS add-ons** while keeping **電子帳簿保存法**-ready
search and retention — without adopting full accounting software.

## Origin

Operators already use **NeNe Invoice** for issued documents and may use **NeNe Clear**
for bank matching. A third pain remains:

- vendor PDFs scattered in email, shared folders, and scanner directories
- paying **¥数千/月** for "compliance storage" in a bundled cloud suite
- no unified search by **date + amount + vendor** when the tax advisor asks

That is **evidence management**, not billing, reconciliation, or CSV parsing.

## North Star

Operators and AI agents can:

- upload or ingest **received** PDFs/images with metadata
- **search** by transaction date, amount, counterparty (and combinations)
- rely on **immutable storage + correction history** for integrity
- **retain** 7–10 years without silent deletion
- optionally **link** entries to Invoice/Clear records via HTTP reference
- export a **manifest** for tax advisor review

Vault is **not** accounting software, expense workflow, or document issuance.

## What we explicitly do not build

| Capability | Owner |
| --- | --- |
| Quote / invoice issuance | **NeNe Invoice** |
| Bank reconciliation / dunning | **NeNe Clear** |
| Bank CSV column mapping | **NeNe Profile** |
| Expense approval workflows | Future separate product |
| General ledger / 仕訳 | Accounting software |

## Target operators

**Primary — Japan SMB office manager** paying a bundled cloud suite primarily for
**document storage and search**. Uses Invoice for billing; wants inbound vendor
documents in one self-hosted place.

**Secondary — Tier B developer** running Vault beside Invoice/Clear on one VPS.

## Primary persona

> A **10-person trading company** receives 50+ vendor PDF invoices per month.
> They pay monthly for a **document-storage module** in a cloud accounting bundle
> they barely use beyond fear of 電帳法. They want **upload + search + retention**
> on the same server as NeNe Invoice — **without** learning accounting features
> they do not need.

## Primary use case

1. Operator uploads vendor invoice PDF.
2. Enters transaction date, amount, vendor name (or confirms OCR suggestion).
3. Later, advisor asks "all invoices from Vendor X in Q1" → search returns list.
4. Optional: link `vault_document_id` to Clear reconciliation note or Invoice
   client reference (HTTP IDs only).

## Dual deployment (planned)

| Tier | Path |
| --- | --- |
| **Tier A** | Release ZIP + web installer + MySQL |
| **Tier B** | Docker Compose beside Invoice/Clear |

## Success criteria (MVP complete)

- Upload document, search by date+vendor, void with audit trail
- Retention policy enforced (no auto-purge)
- `composer check` green; OpenAPI documents Vault operations only
- Compliance doc + scope contract unchanged by implementation

## Related

- Requirements: [`requirements.md`](./requirements.md)
- Scope contract: [`scope-contract.md`](./scope-contract.md)
- Roadmap: [`../roadmap.md`](../roadmap.md)

Last updated: 2026-05-30
