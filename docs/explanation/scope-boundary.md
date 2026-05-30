# Scope Boundary — NeNe Vault vs Siblings

**Binding.** Defines what Vault owns vs other NeNe back-office products.

## Product map

| | **NeNe Invoice** | **NeNe Clear** | **NeNe Profile** | **NeNe Vault** |
| --- | --- | --- | --- | --- |
| **Repository** | `nene-invoice` | `nene-clear` | `nene-profile` | `nene-vault` (this) |
| **Domain (JA)** | 見積・請求・入金 | 入金消込・督促 | 銀行CSV正規化 | 受取書類保存 |
| **Domain (EN)** | Billing documents | Reconciliation & dunning | CSV normalization | Received-document archive |
| **SSOT for** | Issued invoices | Bank match & dunning | Column mapping presets | Received file evidence |

## Integration (HTTP only)

```
NeNe Vault  ──optional link──►  NeNe Invoice (issued doc reference)
NeNe Vault  ──optional link──►  NeNe Clear (reconciliation context)
NeNe Vault  ✗                  NeNe Profile (no dependency)
```

No shared databases. Vault never reads Invoice/Clear tables directly.

## What Vault is NOT

| Misconception | Reality |
| --- | --- |
| "Full-suite accounting substitute" | Vault replaces **storage + search** subscription slice only |
| "Also do expense精算" | Expense workflow is a **separate product** (not Vault MVP) |
| "Store bank CSV" | Bank lines → **Profile** then **Clear** |
| "Issue invoices from Vault" | Issuance → **Invoice** |

## Vault roadmap only

See [`roadmap.md`](../roadmap.md):

1. Document upload + metadata + search API
2. Admin UI + void/version history
3. Export manifest + Tier A installer
4. Optional email inbound, MCP read tools

Post-MVP Vault improvements (same domain): OCR assist, bulk import, Records
client enrichment — each via Issue + ADR.

## Related

- ADR 0009: Domain split from billing/reconciliation
- [`scope-contract.md`](./scope-contract.md)
- [`../integrations/sibling-products.md`](../integrations/sibling-products.md)

Last updated: 2026-05-29
