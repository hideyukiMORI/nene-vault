# Requirements

Functional and compliance requirements for **NeNe Vault only** — received-document
archive.

> **Out of scope:** billing issuance → `nene-invoice`; reconciliation → `nene-clear`;
> bank CSV mapping → `nene-profile`. See [ADR 0009](../adr/0009-separate-from-billing-and-reconciliation.md).

See also: [`product-vision.md`](./product-vision.md),
[`received-document-compliance.md`](./received-document-compliance.md),
[`scope-contract.md`](./scope-contract.md).

---

## 1. Tenancy and roles

Multi-tenant — [ADR 0006](../adr/0006-multi-tenancy-and-roles.md).

| Role | Capabilities | Phase |
| --- | --- | --- |
| **superadmin** | Cross-tenant org management | 1 |
| **admin** | Upload, metadata edit, void, export, settings | 1 |
| **member** | Upload, metadata edit (own uploads) | 1 |
| **viewer** | Search and download | 2+ |

JWT for mutating routes. Tenant isolation on all queries.

---

## 2. Core entities (MVP)

All tenant-scoped entities carry **`organization_id`**.

| Entity | Purpose |
| --- | --- |
| **organization** | Tenant |
| **user** | Operator account |
| **vault_settings** | Retention years, storage path, optional sibling link config |
| **vault_document** | Logical document (current metadata pointer) |
| **document_version** | Immutable file blob + version number |
| **document_link** | Optional HTTP reference to Invoice/Clear entity |
| **audit_event** | Upload, metadata change, void, export |

Money fields: **`amount_cents`** nullable integer (JPY). No floats.

---

## 3. Storage requirements

Binding: [ADR 0012](../adr/0012-file-storage-architecture.md),
[`received-document-compliance.md`](./received-document-compliance.md).

- [ ] Files stored outside web root (`storage/vault/{org_id}/…`)
- [ ] SHA-256 hash per version; duplicate warning
- [ ] MIME allowlist: `application/pdf`, `image/jpeg`, `image/png`
- [ ] Max file size configurable (default 20 MB Phase 1)
- [ ] No in-place byte overwrite — new version only

---

## 4. API requirements (Phase 1)

- [ ] `POST /admin/vault/documents` — upload + metadata
- [ ] `GET /admin/vault/documents` — search (date, amount, counterparty, tags)
- [ ] `GET /admin/vault/documents/{id}` — detail + download URL
- [ ] `PATCH /admin/vault/documents/{id}/metadata` — audited metadata change
- [ ] `POST /admin/vault/documents/{id}/void` — void with reason
- [ ] `GET /admin/vault/documents/{id}/history` — versions + audit
- [ ] OpenAPI 3.1, RFC 9457 Problem Details, snake_case JSON
- [ ] `GET /health` unauthenticated

---

## 5. Search requirements (binding)

Must satisfy [`received-document-compliance.md`](./received-document-compliance.md) §3.2:

- [ ] Filter by `transaction_date` range
- [ ] Filter by `amount_cents` range
- [ ] Filter by `counterparty_name` (partial)
- [ ] Combine ≥2 dimensions in one query

---

## 6. Retention requirements

See [ADR 0004](../adr/0004-retention-period-calculation.md) for the statutory
basis and calculation rationale.

- [ ] Default `retention_years = 10` (not 7 — see ADR 0004: "7 years from transaction_date" is systematically shorter than the statutory 7 years from the filing deadline)
- [ ] Minimum configurable: 7 (with warning: "may not cover statutory minimum — confirm with your 税理士")
- [ ] Maximum configurable: 99 (permanent retention is a valid policy)
- [ ] `retention_expires_at` computed at upload time: `transaction_date + retention_years`; if `transaction_date` is null, `uploaded_at + retention_years` with `date_uncertain = true` flag
- [ ] `retention_years` change in vault_settings does NOT retroactively shorten retention on existing documents — only lengthens
- [ ] Documents before `retention_expires_at` cannot be purged; system blocks purge
- [ ] Voided documents remain in index with `voided_at` (excluded from default search filter); same retention enforcement applies

---

## 7. Sibling links (Phase 2)

Optional, non-authoritative:

- [ ] `document_link` to `nene_invoice` entity types: `client`, `invoice` (read IDs only)
- [ ] `document_link` to `nene_clear` entity types: `bank_transaction`, `payment_reconciliation`
- [ ] Link create/delete audited; no sync of amounts from siblings into Vault SSOT

Contract: [`../integrations/sibling-products.md`](../integrations/sibling-products.md).

---

## 8. Security

- Tenant isolation (ADR 0006)
- Virus scan hook point (Phase 3+ ADR) — document interface, optional ClamAV
- No stack traces in production
- Audit log for all mutations

---

## 9. Explicit non-goals

| Item | Owner |
| --- | --- |
| Invoice/quote PDF generation | **NeNe Invoice** |
| Bank CSV import / presets | **NeNe Profile** |
| Match confirm / dunning | **NeNe Clear** |
| Expense approval | Future product |
| OCR as authoritative without confirm | Vault rejects (compliance §5) |

---

## 10. Acceptance tests (MVP)

1. Upload PDF with date, amount, counterparty.
2. Search by date range + counterparty → document returned.
3. Change amount → audit shows old value; file bytes unchanged.
4. Void document → excluded from default search; history retained.
5. Export manifest CSV for date range.

---

## Related

- Compliance: [`received-document-compliance.md`](./received-document-compliance.md)
- Roadmap: [`../roadmap.md`](../roadmap.md)

Last updated: 2026-05-29
