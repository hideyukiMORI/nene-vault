# Agent / AI Guide

Entry point for AI agents working on **NeNe Vault** (public repo `nene-vault`).

## Domain (read first)

| Product | Repository | Domain |
| --- | --- | --- |
| **NeNe Invoice** | `nene-invoice` | Quote, invoice, payment management |
| **NeNe Clear** | `nene-clear` | Payment reconciliation & dunning |
| **NeNe Profile** | `nene-profile` | Bank CSV normalization |
| **NeNe Vault** | `nene-vault` (this) | Received-document archive |

See [ADR 0009](docs/adr/0009-separate-from-billing-and-reconciliation.md).

## Read First

- **Scope contract (binding):** `docs/explanation/scope-contract.md`
- **Compliance (binding):** `docs/explanation/received-document-compliance.md`
- **No competitor names (binding):** `docs/adr/0013-no-third-party-product-names.md`
- **Portfolio strategy:** [publication-strategy `docs/products/nene-vault.md`](https://github.com/hideyukiMORI/publication-strategy/blob/main/docs/products/nene-vault.md)
- **Product vision:** `docs/explanation/product-vision.md`
- **Requirements:** `docs/explanation/requirements.md`
- **Domain model:** `docs/explanation/domain-model.md`
- **Terminology (binding spellings):** `docs/explanation/terminology.md`
- **Glossary:** `docs/explanation/glossary.md`
- **Retention calculation (ADR 0004):** `docs/adr/0004-retention-period-calculation.md`
- **Audit event schema (ADR 0014):** `docs/adr/0014-audit-event-schema.md`
- **Scope boundary:** `docs/explanation/scope-boundary.md`
- **NENE2 conventions:** `docs/development/nene2-compliance.md`
- **Sibling integration:** `docs/integrations/sibling-products.md`
- **Current work:** `docs/todo/current.md`
- **Roadmap:** `docs/roadmap.md`

## Operating Rules

- Issue-driven; no direct commits to `main`
- Do **not** add quote/invoice issuance — **`nene-invoice`**
- Do **not** add bank reconciliation/dunning — **`nene-clear`**
- Do **not** add bank CSV mapping engine — **`nene-profile`**
- Do **not** add expense approval workflows or journal entries
- **Follow NENE2 conventions** — `docs/development/nene2-compliance.md`
- Namespace: `NeneVault\`; money: integer cents; document metadata only
- **Repository docs: English only** (ADR 0008)

## Framework

[NENE2](https://github.com/hideyukiMORI/NENE2) via Composer when runtime lands.
