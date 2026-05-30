# Sibling Product Integration

NeNe Vault integrates with sibling products **via HTTP only** (ADR 0002).
Vault has **no required upstream** — it operates standalone.

## Dependency direction

```
NeNe Vault  ──optional──►  NeNe Invoice (reference links)
NeNe Vault  ──optional──►  NeNe Clear (reference links)
NeNe Vault  ✗            NeNe Profile (no integration in MVP)
```

Never embed Vault in sibling repos. Never share databases.

## NeNe Invoice — optional links

Vault may store **reference IDs** to Invoice entities for operator convenience:

| Link type | Purpose | Phase |
| --- | --- | --- |
| `client` | Same vendor as Invoice client master | 2 |
| `invoice` | Copy of received doc related to payable | 2 |

Vault **must not** fetch Invoice PDFs as SSOT or sync amounts from Invoice into
authoritative metadata without operator action.

Env (planned): `NENE_INVOICE_API_BASE_URL`, `NENE_INVOICE_BEARER_TOKEN` — for
link validation UI only.

## NeNe Clear — optional links

| Link type | Purpose | Phase |
| --- | --- | --- |
| `bank_transaction` | Supporting doc for a deposit line | 3 |
| `payment_reconciliation` | Evidence for match audit | 3 |

Clear **must not** store received PDFs as SSOT — Vault owns file bytes.

## NeNe Profile

No default integration. Bank CSV is unrelated to received document storage.

## Implementation rules

- Link client in `src/Integration/SiblingLink/` (interfaces + HTTP validators).
- UseCases must not depend on siblings for core upload/search.
- Sibling API failure: links disabled; core Vault continues.

## Bug routing

| Symptom | Open Issue in |
| --- | --- |
| Invoice API for link validation | **nene-invoice** |
| Clear wants document link field | **nene-clear** |
| NENE2 middleware | **NENE2** |

## Related

- [`../explanation/scope-boundary.md`](../explanation/scope-boundary.md)
- ADR 0009

Last updated: 2026-05-29
