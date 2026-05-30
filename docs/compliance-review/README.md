# Compliance Review Gate

This directory holds the materials a licensed **税理士 (tax accountant)** or
**公認会計士 (CPA)** needs to review NeNe Vault's received-document compliance
posture, and the record of their sign-off.

> This is a **gate**, not a guarantee. Engineering prepares the package; the
> professional reviews it and records findings. Phase 2 admin UI **must not** ship
> to production operators until §9 of
> [`received-document-compliance.md`](../explanation/received-document-compliance.md)
> is satisfied and the sign-off record below is completed.

## Why this gate exists

- [`received-document-compliance.md` §9](../explanation/received-document-compliance.md) — professional review gate before Phase 2 admin UI
- [`scope-contract.md`](../explanation/scope-contract.md) — definition of done requires 税理士 review of received-document retention/search posture
- [ADR 0011](../adr/0011-electronic-records-received-documents.md) — correction-history method choice (not legal advice; needs professional confirmation)

## Files

| File | Purpose |
| --- | --- |
| `2026-tax-advisor-review-package.md` | The review package: statutory basis → implementation evidence, posture to confirm, known limits, open questions |
| `signoff-record.md` | Sign-off record the professional completes (name, credential, date, findings) |

## How to use

1. Engineering keeps `2026-tax-advisor-review-package.md` current with the
   implemented behavior (it links to the binding compliance doc, not a restatement).
2. The maintainer sends the package to a licensed professional.
3. The professional reviews the posture, raises any findings, and completes
   `signoff-record.md`.
4. If the professional requires changes, open Issues; any change that deviates
   from the compliance doc needs an ADR with sign-off (compliance §0.2).
5. Only when the sign-off record shows **approved** does the Phase 2 UI gate open.

## Scope of the review

In scope: received electronic documents (電子取引データ) — integrity, visibility/
search, retention, audit, export posture.

Out of scope for this product (do not ask the advisor to bless these): invoice
issuance, bank reconciliation, journal entries, tax computation — see
[`scope-contract.md`](../explanation/scope-contract.md) DON'T list.
