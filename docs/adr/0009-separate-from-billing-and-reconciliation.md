# ADR 0009: Separate Domain from Billing, Reconciliation, and CSV Normalization

## Status

accepted

## Context

The NeNe back-office portfolio has **four distinct domains** operators often
bundle into one mega-SaaS subscription:

| Product | Repository | Domain |
| --- | --- | --- |
| NeNe Invoice | `nene-invoice` | Issued billing documents |
| NeNe Clear | `nene-clear` | Bank reconciliation & dunning |
| NeNe Profile | `nene-profile` | Bank CSV normalization |
| NeNe Vault | `nene-vault` | Received-document archive |

Vault must not absorb features from the other three — that recreates a monolithic accounting suite.

## Decision

### NeNe Vault owns ONLY

- Received document file storage (`vault_document`, `document_version`)
- Metadata: transaction date, amount, counterparty, tags
- Search, retention, void/version audit trail
- Optional HTTP **reference links** to sibling entities (not SSOT sync)
- Compliance rules in `received-document-compliance.md`

### NeNe Vault does NOT own

- Quote/invoice issuance, PDF generation, tax on issued documents → **Invoice**
- Bank import, match confirm, dunning → **Clear**
- Column mapping presets, bank CSV transformers → **Profile**
- Expense approval workflows, journal entries, payroll

### Integration model

```
NeNe Vault API  ──optional HTTP link IDs──►  Invoice / Clear APIs
Separate DB. No shared tables. No embedded sibling code.
```

## Consequences

- Clear product story: cancel storage-only SaaS without scope creep.
- Profile and Clear remain independent; Vault has zero bank CSV logic.
- Tax advisors review received evidence separately from issued invoices.

## Related

- [`../explanation/scope-boundary.md`](../explanation/scope-boundary.md)
- publication-strategy decision 0005
