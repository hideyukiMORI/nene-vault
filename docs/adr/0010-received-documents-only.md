# ADR 0010: Received-Documents-Only Posture

## Status

accepted

## Context

Vault could drift toward expense reimbursement (employee receipts) or issued-document
archive. Both blur SSOT boundaries.

## Decision

1. **Primary corpus:** documents **received from third parties** (vendors, landlords,
   service providers).
2. **Issued document copies** MAY be stored for convenience but **NeNe Invoice
   remains SSOT** for issued billing data.
3. **Employee expense receipts** are **out of MVP** — track as future separate
   product (NeNe Petty or similar), not Vault Phase 1–3.
4. Categories are operational tags — **not** 勘定科目 or tax codes.

## Consequences

- MVP UX focuses on vendor invoice PDFs, not mobile receipt snap workflows.
- Expense product can integrate with Vault later via HTTP if needed.

## Related

- [`../explanation/scope-contract.md`](../explanation/scope-contract.md) X5, X12
