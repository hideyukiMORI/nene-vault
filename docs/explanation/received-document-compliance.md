# Received-Document Compliance (binding)

**Status: binding for NeNe Vault engineering.** A finance or accounting
professional reviewing the system must be able to find **zero deviations** from
the rules below.

These are not guidelines. They are **MUST** requirements. Where a rule here
conflicts with UX, performance, implementation convenience, or any other concern,
**compliance wins — every time, without exception.**

See also: [`scope-contract.md`](./scope-contract.md),
[`domain-model.md`](./domain-model.md),
[ADR 0011](../adr/0011-electronic-records-received-documents.md),
[ADR 0004](../adr/0004-retention-period-calculation.md).

> **Not legal advice.** This document is engineering's binding interpretation of
> applicable rules. When a requirement is unclear, **stop and consult a 税理士 or
> 公認会計士** — do not guess. Record the resolved interpretation here with
> professional attribution and date.

---

## 0. Governing principle

1. **Compliance is non-negotiable.** Correct adherence to the law takes
   precedence over every other product goal.
2. **No silent deviation.** Any departure from the rules in this document —
   even temporary, even for a single operator — requires a new **ADR** with
   **explicit sign-off by a licensed 税理士 or 公認会計士** recorded in that ADR.
   Code may not merge a deviation without it.
3. **Engineering is not the legal authority.** This document is engineering's
   binding interpretation. When a requirement is unclear, **stop and consult a
   税理士** — do not guess. Record the resolved interpretation here.
4. **Evidence store, not ledger.** Vault preserves received documents and their
   metadata; it does not post journal entries, compute tax, or issue documents.
   Any feature that crosses this boundary violates
   [`scope-contract.md`](./scope-contract.md) X4, X12.

---

## 1. Statutory basis

NeNe Vault targets the following Japanese rules for **received electronic
documents**. This table states *what we comply with*; it is not legal advice.

| Area | Rule set |
| --- | --- |
| 電子取引データの保存義務 | 電子帳簿保存法 第7条（平成10年法律第25号、令和3年改正 — 令和4年1月1日施行、猶予期間終了 令和6年1月1日） |
| 保存要件（真実性・可視性・検索） | 電子帳簿保存法施行規則 第4条第1項（財務省令） |
| 保存期間（法人） | 法人税法第75条の3、法人税法施行規則第26条の2 — 申告期限の翌日から7年 |
| 保存期間（個人事業主） | 所得税法施行規則第102条 — 申告期限の翌日から7年 |
| 保存期間（消費税） | 消費税法第30条第9項、消費税法施行規則第15条の3 — 申告期限の翌日から7年 |
| 帳簿保存（商法・会社法） | 会社法第432条第2項 — 会計帳簿等は10年 |
| 繰越欠損金がある法人 | 法人税法第57条 — 最長10年（欠損が発生した事業年度の翌期から） |

When any of these change (statutory amendment, rate changes, new NTA guidance),
treat it as a compliance defect until the product is updated, and open a P0 Issue.

---

## 2. Document scope

### 2.1 Primary scope — 電子取引 (Electronic transactions)

Vault's primary compliance target is **電子取引データ** under 電帳法 第7条:
documents that were **created and transmitted electronically** to the operator.

| Typical case | Example formats |
| --- | --- |
| Vendor invoice PDF received by email attachment | `application/pdf` |
| Receipt downloaded from vendor's web portal | `application/pdf` |
| Electronic delivery note / 請求書 from EDI or API | `application/pdf` |
| Payment receipt from a payment service | `application/pdf`, `image/png` |

For these documents: operators are legally required to preserve the original
electronic form — **printing and discarding the electronic original is
prohibited** under 電帳法 第7条 since January 1, 2024. Vault satisfies this
preservation requirement.

### 2.2 Secondary scope — スキャナ保存 (Scanner preservation, paper → digital)

Vault **accepts** JPEG/PNG uploads that may be scans of paper originals.
However, スキャナ保存 is governed by **different and stricter rules**
(電子帳簿保存法施行規則 第2条) than 電子取引. Vault does **not** manage the
additional スキャナ保存 requirements:

| スキャナ保存 requirement | Vault's role |
| --- | --- |
| Accredited timestamp (認定タイムスタンプ) — unless correction-history method applies | **Operator responsibility.** Vault does not acquire TSA timestamps. |
| Scan quality (200 dpi or higher, color for certain docs) | **Operator responsibility.** Vault stores whatever the operator uploads. |
| Prompt scanning timeline (速やか or 業務処理サイクル内) | **Operator responsibility.** Vault records `uploaded_at` but does not enforce deadline from paper receipt. |
| Manager confirmation for receipts under ¥30,000 | **Not implemented.** Separate approval workflow is out of scope (scope-contract X5). |

When an operator uploads a JPEG/PNG with source `scan_upload`, the system
**MUST** display a warning: "This appears to be a scanned document. スキャナ保存
requirements differ from 電子取引 rules. Confirm compliance with your 税理士."

### 2.3 Out of scope

| Document type | Owner |
| --- | --- |
| 適格請求書 / 請求書 issued by the operator | **NeNe Invoice** (issued billing SSOT) |
| Bank deposit records | **NeNe Clear** / **NeNe Profile** |
| Journal entries / 仕訳 | Accounting software |
| Employee expense receipts requiring approval | Future product (scope-contract X5) |

---

## 3. Integrity (真実性の確保)

Vault adopts the **訂正削除の履歴が残るシステム方式** (correction-history system
method) under 電帳法施行規則 第4条第1項第2号ロ. This is one of the four
recognized methods; the others (タイムスタンプ, 削除不能システム, 事務処理規程)
are explicitly **not** managed by Vault in MVP.

### 3.1 File immutability

- Stored file bytes **MUST NEVER** be overwritten after upload.
- Correction creates a new `document_version` row; prior versions remain
  permanently addressable.
- Soft-delete (void) is the only removal path; hard delete of file bytes is
  **prohibited** in normal operation (scope-contract X7, X8).
- The system **MUST** compute and store `file_sha256` at upload time. On download,
  the serving layer **MUST** verify the hash matches. Hash mismatch is a P0
  defect.

### 3.2 Metadata immutability with audit

Changes to any of the following fields **MUST** create an `audit_event` record
capturing the **old value and new value** before the change is committed:

- `transaction_date`
- `amount_cents`
- `counterparty_name`
- `category`
- `tags`

Prior values remain permanently queryable via the history endpoint.
**In-place overwrite of authoritative metadata without audit is prohibited.**

### 3.3 Void, not delete

- Operators MAY void a document by recording `voided_at`, `voided_by`,
  `void_reason` (mandatory), and optional `void_note`.
- A voided document remains in the database with status `voided`. It is
  **excluded from default search** but must be retrievable by history and audit
  queries.
- Voided documents **still count toward the retention period**.
- Un-voiding (restore) requires admin capability and creates a `document.restored`
  audit event. Mass un-void without audit is prohibited.
- Hard delete of a voided document is **prohibited** during the retention window.
  Post-retention purge requires admin confirmation + audit event.

### 3.4 Provenance

Each uploaded version **MUST** record:

| Field | Meaning |
| --- | --- |
| `file_sha256` | SHA-256 hex digest of uploaded file bytes |
| `original_filename` | Filename as supplied by the upload client |
| `mime_type` | Validated MIME type (allowlist enforced) |
| `version_number` | Monotonic integer starting at 1 per `vault_document` |
| `uploaded_at` | System timestamp at receipt |
| `uploaded_by` | `user.id` of the authenticated operator |
| `source` | `web_upload` \| `email_inbound` \| `api` \| `scan_upload` |

`source = scan_upload` triggers the スキャナ保存 warning (§2.2).

### 3.5 Duplicate detection

When a new upload's `file_sha256` matches an existing version within the same
`organization_id`, the system **MUST** warn the operator before accepting the
upload. Operator confirms intentional duplicate or cancels. Silent auto-accept
of duplicates is prohibited; silent rejection is also prohibited (operator decides).

---

## 4. Visibility (可視性の確保)

### 4.1 Legibility (見読可能性) — 規則第4条第1項第1号

- Stored files **MUST** be viewable and downloadable in their original format
  (PDF, JPEG, PNG) via an authenticated endpoint.
- The admin UI **MUST** render PDF inline where the browser supports it; fallback
  to a download link.
- A print-friendly list view with all required metadata columns (transaction_date,
  amount_cents, counterparty_name, category, file_sha256, status) **MUST** be
  available.

### 4.2 Search requirements (検索要件) — 規則第4条第1項第3号

Vault **MUST** support searching on all of the following fields. These are
**statutory fields** — they are not cosmetic or convenience features. Any
regression in search capability is a compliance defect.

| Statutory field | Vault field | Capability |
| --- | --- | --- |
| 取引年月日 (Transaction date) | `transaction_date` | Exact, range (`from` / `to`) |
| 取引金額 (Transaction amount) | `amount_cents` | Exact, range (`min_cents` / `max_cents`) |
| 取引先 (Counterparty) | `counterparty_name` | Partial text match, normalized |

**Combination queries:** at least two-field AND queries (e.g. date range +
counterparty) **MUST** be supported in a single API call. The three-field AND
combination **MUST** also be supported. This covers the 電帳法 要件 of
検索機能の確保 under 規則第4条第1項第3号.

Search is provided **regardless of whether the operator qualifies for relaxed
requirements** (中小企業の特例) — product strategy targets operators who want
advisor-ready posture by default.

### 4.3 System overview document

An operator guide describing storage location, backup expectation, search
workflow, void/version semantics, and retention policy **MUST** ship with the
product. This is required under 電帳法 for 可視性の確保 (システム概要書). It is
a Phase 2 deliverable and a gate before the product can be offered for production
use.

---

## 5. Retention (保存期間)

Retention rules are governed by ADR 0004. The implementation rules follow.

### 5.1 Retention period

| Operator type | Statutory minimum | Vault default | Configurable up to |
| --- | --- | --- | --- |
| All operators (safe default) | 7 years from 申告期限 | **10 years from `transaction_date`** | 10 years |
| 法人 with 繰越欠損金 | Up to 10 years from filing deadline | 10 years from `transaction_date` | 10 years |

**Why 10 years as default:** The statutory 7-year period runs from the filing
deadline of the fiscal year containing the transaction — not from the
transaction date itself. Depending on the operator's fiscal year and filing
calendar, the filing deadline can be 14–26 months after the transaction. Retaining
7 years from `transaction_date` may be 3–12 months shorter than required (see
ADR 0004 for the full calculation). The 10-year default is always sufficient for
法人税法, 消費税法, and 会社法 requirements without requiring fiscal-year
configuration.

### 5.2 Retention enforcement

- The system **MUST NOT** auto-purge any document before its `retention_expires_at`
  date (computed at upload from `transaction_date + retention_years`).
- Any scheduled or manual purge operation **MUST** verify
  `retention_expires_at <= now()` and **MUST** require admin capability
  (`manage_vault_settings`) plus explicit confirmation.
- A purge attempt on a non-expired document is a P0 defect.
- Voided documents are subject to the same retention enforcement as active
  documents — void does not reduce the retention obligation.

### 5.3 Unknown transaction date

When `transaction_date` is null or unknown at upload:

- `retention_expires_at` is anchored to `uploaded_at + retention_years` as a
  conservative placeholder.
- The document is **flagged** with `date_uncertain = true` and appears in
  compliance warnings.
- The operator is prompted to supply `transaction_date`; upon confirmation,
  `retention_expires_at` is recalculated and the recalculation is audited.

### 5.4 End-of-retention-period procedure

1. Admin exports a manifest for the expiring cohort.
2. Admin confirms export was received.
3. Admin triggers purge for confirmed documents.
4. System records a `document.purged` audit event per document.
5. File bytes are deleted; metadata and audit history are retained for a further
   3-year administrative grace period before permanent removal.

---

## 6. Amount and date handling

- Amounts are stored as **integer minimum currency units** (`amount_cents`; for
  JPY, 1 cent = ¥1). **Float and DECIMAL for money are prohibited** in DB, API
  JSON, and tests.
- `amount_cents` is **nullable** — some received documents (delivery notes,
  contracts with no stated amount) carry no monetary value; Vault **MUST NOT**
  require an amount to store a document.
- `transaction_date` (取引年月日) is the date on the received document.
  `uploaded_at` is the system receipt timestamp. **These are distinct fields
  and must never be conflated.**
- If a received document bears no legible date, operator may leave
  `transaction_date` null. The document is flagged `date_uncertain = true`
  (§5.3 applies).
- Phase 1–3 currency is **JPY only**. Multi-currency requires an ADR with
  professional review.

---

## 7. Audit trail

Audit trail is governed by ADR 0014. Summary rules:

- **Every mutating operation on a vault document or vault settings** records an
  `audit_event` row.
- Reads are not audited; writes are.
- `audit_event` records **MUST NOT** be updated or deleted after creation.
  Audit records are subject to the same retention rules as the documents they
  describe.
- The `actor_user_id` of every audit event must be the authenticated operator
  who performed the action. System-generated events (e.g. retention expiry
  computed) record `actor_user_id = NULL` with `source = system`.

Required event types (minimum set for MVP):

| Event | Trigger |
| --- | --- |
| `document.uploaded` | New vault_document + document_version created |
| `document.metadata_changed` | Any change to transaction_date, amount_cents, counterparty_name, category, tags |
| `document.voided` | Vault document set to `voided` |
| `document.restored` | Voided document returned to `active` |
| `document.version_added` | Replacement file upload (new document_version) |
| `document.exported` | Manifest CSV or ZIP export covering this document |
| `document.purged` | Document removed after retention expiry (§5.4) |
| `document.link_created` | document_link to Invoice/Clear entity added |
| `document.link_deleted` | document_link removed |
| `vault_settings.changed` | Any change to organization's retention_years, storage_path, or sibling link config |

---

## 8. OCR policy

OCR assistance is a Phase 4 feature. When implemented:

- OCR output **MUST** be stored in `suggested_transaction_date`,
  `suggested_amount_cents`, `suggested_counterparty_name` fields.
- **Operator confirmation is required** before OCR-suggested values are promoted
  to authoritative fields.
- Promotion creates a `document.metadata_changed` audit event (from suggested
  value to confirmed value).
- The system **MUST NOT** auto-promote OCR suggestions to authoritative fields
  without an explicit confirmation action (scope-contract X6).
- Confidence scores and raw OCR source may be stored but are not compliance
  artifacts; only the operator-confirmed values are authoritative.

---

## 9. スキャナ保存 advisory (summary)

Vault does not certify スキャナ保存 compliance. For scanned documents:

| Rule | Vault's position |
| --- | --- |
| Timestamp requirement (認定タイムスタンプ or correction-history) | Correction-history method is satisfied by Vault if the operator's internal procedures document (事務処理規程) is maintained; TSA is operator's responsibility |
| Resolution / color requirements | Operator responsibility at scan time; Vault stores uploaded bytes as-is |
| Promptness of scanning / entry | Operator responsibility; Vault records `uploaded_at` only |
| 事務処理規程 (operational procedures document) | Operator must maintain this separately; Vault does not generate or enforce it |

A Phase 2+ operator guide will include a template 事務処理規程 outline that
operators can adapt. Until then, operators relying on スキャナ保存 **MUST**
consult their 税理士.

---

## 10. Tax audit response (税務調査対応)

When a tax inspector (税務調査官) requests records:

1. Admin uses the export feature to produce a manifest CSV + ZIP of requested
   documents, filtered by date range and optionally by counterparty/amount.
2. The manifest includes: `document_id`, `version`, `transaction_date`,
   `amount_cents`, `counterparty_name`, `category`, `file_sha256`,
   `uploaded_at`, `voided_at` (if applicable).
3. Document history (all versions, all metadata changes, audit events) is
   accessible via the history endpoint for any specific document.
4. The system **MUST** be able to produce this without affecting ongoing
   operations or modifying any stored data (export is read-only).
5. Standard practice: 税務調査 notice-to-response is typically 14 days. The
   export function **MUST** support filtering on all statutory search fields
   (§4.2).

---

## 11. What Vault does NOT do (compliance scope boundary)

| What a 税理士 might expect of accounting software | Vault's position |
| --- | --- |
| Post journal entries (仕訳) | **Prohibited.** Scope-contract X4. |
| Classify tax account codes (勘定科目) | **Prohibited.** Scope-contract X12. |
| Compute consumption tax on received invoices | **Prohibited.** Scope-contract X12. Not a USE of information; that belongs to accounting software. |
| Certify インボイス registration numbers on received documents | **Not implemented.** Vault displays the vendor's stated number; operator verifies via NTA portal. |
| Issue qualified invoices (適格請求書) | **Prohibited.** Scope-contract X1. |
| Acquire accredited timestamps (認定タイムスタンプ) | **Not in MVP.** Operator responsibility if TSA method is required. |
| Generate 事務処理規程 with legal force | **Not in MVP.** Vault provides a template outline in Phase 2+; the legally binding document is the operator's responsibility. |
| Certify spanner-preservation (スキャナ保存) compliance | **Not implemented.** Vault supports the correction-history method for integrity; スキャナ保存 specific requirements are operator responsibility. |
| Reconcile bank payments against received invoices | **Prohibited.** Scope-contract X2. NeNe Clear's domain. |

---

## 12. How this applies to every change

Any change that touches **document storage, file serving, metadata editing,
search, audit logging, retention, void/restore, or export** MUST:

1. Be reviewed against this document and the self-review compliance checklist
   (`docs/review/compliance.md` when written).
2. State compliance impact in the PR.
3. If it deviates from any rule here: carry a new ADR with professional sign-off
   (§0.2). No exceptions.
4. If the change is in the grey area: **assume compliance impact and run the
   review** — do not assume it is safe.

---

## Related

- Scope contract: [`scope-contract.md`](./scope-contract.md)
- Domain model: [`domain-model.md`](./domain-model.md)
- ADR 0004: Retention period calculation
- ADR 0011: Electronic-records integrity method choice
- ADR 0012: File storage architecture
- ADR 0014: Audit event schema

Last updated: 2026-05-30
