# Received-Document Compliance (binding)

**Status: binding for NeNe Vault engineering.** This document defines how Vault
meets **電子帳簿保存法** expectations for **received electronic documents**
(受取電子取引データ).

> **Not legal advice.** Engineering interpretation for implementers and tax-advisor
> review. Confirm small-business relaxations and industry-specific rules with a
> licensed 税理士 before production deployment.

See also: [`scope-contract.md`](./scope-contract.md), [ADR 0011](../adr/0011-electronic-records-received-documents.md).

---

## 1. Scope of this document

| In scope | Out of scope |
| --- | --- |
| Received PDFs/images from vendors (請求書, 契約書, 領収書 等) | Issued qualified invoices → **NeNe Invoice** |
| Metadata: date, amount, counterparty, tags | Bank deposit lines → **NeNe Clear** / **NeNe Profile** |
| Search, retention, correction history | Journal entries, tax returns |
| Export for advisor review | Expense approval workflows |

---

## 2. Integrity (真実性の確保)

Vault adopts the **correction-history method** (訂正削除の履歴が残るシステム):

1. **File immutability.** Stored file bytes are never overwritten. A correction
   creates a new `document_version` row; prior version remains addressable.
2. **Metadata immutability with audit.** Changes to `transaction_date`, `amount_cents`,
   `counterparty_name`, or `category` append an `audit_event` — prior values
   remain queryable in history.
3. **Void, not delete.** Operator may **void** a document (logical delete) with
   reason and actor; void is reversible only via ADR-gated admin recovery — never
   silent hard delete in normal operation.
4. **Provenance.** Each upload records: `file_sha256`, original filename, MIME,
   `uploaded_at`, `uploaded_by`, optional `source` (`web_upload`, `email_inbound`, `api`).

Duplicate detection: same `file_sha256` within tenant **warns**; operator confirms
intentional duplicate vs re-upload error.

---

## 3. Visibility (可視性の確保)

### 3.1 Legibility (見読可能性)

- Stored files viewable/downloadable in original format (PDF/image).
- Admin UI renders PDF inline where browser supports; fallback download link.
- Print-friendly list view with metadata columns.

### 3.2 Search (検索要件)

Vault **MUST** support search on:

| Field | Capability |
| --- | --- |
| **Transaction date (取引年月日)** | Exact, range |
| **Amount (取引金額)** | Exact, range (integer cents) |
| **Counterparty (取引先)** | Partial match, normalized |

**Combinations:** at least two-field AND queries (e.g. date range + counterparty).

Search is provided **by default** even if operator might qualify for relaxed
requirements — product strategy targets SMBs who want advisor-ready posture.

### 3.3 System overview document

Ship operator guide describing: storage location, backup expectation, search
workflow, void/version semantics, retention policy.

---

## 4. Retention

| Rule | Implementation |
| --- | --- |
| Minimum **7 years** from transaction date (or upload date if date unknown — flagged) | Configurable `retention_years` default 7 |
| **10 years** where operator configures (法人等) | Max cap 10 in Phase 1–3 |
| No automatic purge before retention expires | Cron job **blocks** purge; manual export only |
| Post-retention purge | Requires admin confirmation + audit event + ADR if law changes |

---

## 5. Amount and date handling

- Amounts: **integer cents** in JPY; nullable when document has no amount field.
- Dates: `transaction_date` (取引日) separate from `uploaded_at` (system receipt).
- When OCR suggests date/amount, store in `suggested_*` fields; **operator confirms**
  before promoting to authoritative metadata (aligns with "human confirms" philosophy).

---

## 6. Categories and tags

- Free-form tags + optional preset categories (`invoice_received`, `contract`,
  `receipt`, `other`) — **not** tax account codes.
- Vault **must not** map categories to 勘定科目 automatically (X12 in scope contract).

---

## 7. Export for advisors

Phase 2+ export bundle:

- ZIP of files (or manifest with signed URLs if large)
- `manifest.csv`: document_id, version, transaction_date, amount_cents, counterparty,
  category, file_sha256, uploaded_at, voided_at
- Optional filter by date range

---

## 8. Email inbound (Phase 3+)

If implemented:

- Dedicated inbound address per tenant; attachments become upload candidates.
- Same provenance and immutability rules as web upload.
- SPF/DKIM verification recommended; document in security ADR when added.

---

## 9. Professional review gate

Before **Phase 2 admin UI** ships to operators:

- [ ] 税理士 sign-off on §2–§4 posture for received documents
- [ ] Confirm search fields meet operator's industry pattern
- [ ] Void/version UX reviewed for audit acceptability

---

## Related

- Scope contract: [`scope-contract.md`](./scope-contract.md)
- ADR 0011: Electronic-records method
- ADR 0012: Storage architecture
- Clear bank-data posture (separate domain): `nene-clear` ADR 0012

Last updated: 2026-05-29
