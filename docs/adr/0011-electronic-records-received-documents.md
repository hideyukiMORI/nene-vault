# ADR 0011: Electronic-Records Method for Received Documents

## Status

accepted

> Engineering interpretation of 電子帳簿保存法 — **not legal advice**.

## Context

Received PDFs/images are 電子取引データ. Vault must choose integrity and search
methods at design time.

## Decision

Adopt the same **correction-history method** as NeNe Clear bank data (Clear ADR 0012),
adapted for document files:

1. Immutable file versions; metadata changes audited; void instead of hard delete.
2. Search on transaction date, amount, counterparty (combinations).
3. Retention 7–10 years; no silent purge.
4. Operator system-overview guide shipped with product.

Vault **does not** timestamp via external TSA in MVP — correction history is the
chosen 真実性 path. Revisit via ADR if advisor requires TSA.

## Consequences

- Storage and audit schema designed upfront (not retrofit).
- Aligns advisor review with Clear posture where domains overlap (evidence integrity).

## Related

- [`../explanation/received-document-compliance.md`](../explanation/received-document-compliance.md)
