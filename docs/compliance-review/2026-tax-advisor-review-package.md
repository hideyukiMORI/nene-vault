# Tax Advisor Review Package — NeNe Vault (2026)

**For:** licensed 税理士 / 公認会計士 reviewing NeNe Vault's received-document
(受取電子取引データ) compliance posture before Phase 2 admin UI.

**Binding source of truth:** [`received-document-compliance.md`](../explanation/received-document-compliance.md).
This package maps each rule there to concrete implementation evidence so the
review is verifiable, not aspirational.

> **Not legal advice.** This is engineering's interpretation of 電子帳簿保存法
> for received electronic documents. We ask you to confirm the posture is
> defensible, identify gaps, and record findings in `signoff-record.md`.

---

## 1. What NeNe Vault is (and is not)

| | |
| --- | --- |
| **Is** | A self-hosted archive that stores, searches, and preserves **received** business documents (vendor invoices/contracts/receipts) for 電子帳簿保存法 visibility |
| **Is not** | Accounting software, invoice issuance, bank reconciliation, journal entries, or tax computation (see [`scope-contract.md`](../explanation/scope-contract.md) DON'T list) |

The review concerns **received electronic transaction data (電子取引データ)** only.

---

## 2. Statutory basis → implementation evidence

Each row: the rule (with article), how it is implemented, and where to verify.

### 2.1 電子取引データの保存義務 — 電帳法 第7条

| Requirement | Implementation | Verify |
| --- | --- | --- |
| Preserve received electronic documents in electronic form | Files stored immutably outside web root; never printed-and-discarded as the system of record | `LocalFilesystemDocumentStorage`, ADR 0012; `POST /admin/vault/documents` |
| Original electronic form retained | File bytes never overwritten; SHA-256 recorded at upload, verified at download | `document_versions.file_sha256`; `UploadDocumentUseCase`; `DownloadDocumentVersionUseCase` (hash check before serving) |

### 2.2 真実性の確保 (Integrity) — 規則 第4条第1項第2号

Method chosen: **訂正削除の履歴が残るシステム方式** (correction-history method), ADR 0011.

| Requirement | Implementation | Verify |
| --- | --- | --- |
| File immutability | New version per correction; prior versions永続的に addressable | `document_versions` (unique doc+version); compliance §3.1 |
| Metadata change history | Every change to 取引年月日 / 取引金額 / 取引先 / category / tags appends an audit event with before & after | `UpdateDocumentMetadataUseCase` → `document.metadata_changed`; `audit_events.before_json/after_json` |
| Deletion = void, not erase | Void records reason + actor + timestamp; document stays in DB; restore is audited | `VoidDocumentUseCase` → `document.voided`; `RestoreDocumentUseCase` → `document.restored` |
| Provenance | file_sha256, original filename, MIME, uploaded_at, uploaded_by, source recorded | `document_versions` columns; compliance §3.4 |
| Duplicate detection | Same SHA-256 in org warns; operator confirms | `UploadDocumentUseCase` (409 unless confirm_duplicate); compliance §3.5 |

### 2.3 可視性の確保 (Visibility) — 規則 第4条第1項第1号・第3号

| Requirement | Implementation | Verify |
| --- | --- | --- |
| Legibility (見読可能性) | Files downloadable in original format via authenticated endpoint | `GET .../versions/{versionId}/download` |
| **Search (検索要件)** — 取引年月日 | Exact + range | `searchDocuments` `transaction_date_from/to` |
| Search — 取引金額 | Exact + range (integer JPY) | `amount_min_cents/max_cents` |
| Search — 取引先 | Partial match | `counterparty_name` |
| **Two-or-more field AND combinations** | Supported in one query | `searchDocuments` combination test |

### 2.4 保存期間 (Retention)

| Requirement | Implementation | Verify |
| --- | --- | --- |
| Minimum 7 years from filing deadline | Default **10 years from transaction_date** — chosen to cover the filing-deadline anchor without fiscal-year configuration | ADR 0004; `vault_documents.retention_expires_at` |
| No silent purge before expiry | No auto-purge; purge requires admin + expiry check + export procedure | compliance §5.2/§5.4 |
| Unknown transaction date | Anchored to uploaded_at + flagged `date_uncertain` | `UploadDocumentUseCase`; compliance §5.3 |

### 2.5 監査証跡 (Audit trail) — ADR 0014

| Requirement | Implementation | Verify |
| --- | --- | --- |
| Who changed what, before/after | Every mutation records an append-only audit event | `audit_events`; `AuditRecorder` |
| Audit records immutable | No UPDATE/DELETE in application; DB role lacks those grants | compliance §7; ADR 0014 |
| No secrets in audit | Snapshots exclude password_hash, tokens, file paths | `UserPresenter`, `VaultDocument` audit snapshot (no file_path) |

### 2.6 税務調査対応 (Tax audit response) — compliance §10

| Requirement | Implementation | Verify |
| --- | --- | --- |
| Produce records for inspection | Manifest CSV export filtered by date/counterparty | `POST /admin/vault/export`; columns: document_id, version, transaction_date, amount_cents, counterparty_name, category, file_sha256, uploaded_at, voided_at |
| Read-only, no data change | Export modifies nothing; records `document.exported` audit per document | `ExportDocumentsUseCase` |

---

## 3. Posture we ask you to confirm

1. Is the **correction-history method** (ADR 0011) an acceptable 真実性 method for
   received 電子取引データ for the target operators (Japan SMB), given we do **not**
   acquire accredited timestamps (認定タイムスタンプ) in MVP?
2. Is the **10-year-from-transaction-date** retention default (ADR 0004) a safe
   anchor for 法人税法 / 消費税法 / 会社法 without requiring operators to configure
   fiscal-year/filing-deadline?
3. Do the **search fields and combinations** (§2.3) satisfy 検索要件 for typical
   SMB received-document patterns?
4. Is the **void-not-delete** + audit posture (§2.2) acceptable as the
   訂正削除の履歴 evidence a reviewer would expect?
5. Are the **manifest CSV columns** (§2.6) sufficient for a 税務調査 handoff?

---

## 4. Known limits / out of scope (do not bless these)

| Item | Status |
| --- | --- |
| スキャナ保存 (scanner preservation) specific requirements (resolution, accredited timestamp, prompt-entry) | **Operator responsibility.** Vault warns on `scan_upload` source; does not certify スキャナ保存. compliance §2.2/§9 |
| 認定タイムスタンプ (accredited TSA) | Not in MVP. Correction-history method used instead. Revisit via ADR if you require TSA. |
| 事務処理規程 (operational procedures document) | Operator maintains separately. Vault provides a template outline in Phase 2+. |
| OCR-as-truth | Not implemented. OCR (Phase 4) is suggest-only with operator confirmation. compliance §8 |
| Invoice issuance / reconciliation / journal entries / tax computation | **Out of product scope.** scope-contract X1/X2/X4/X12 |

---

## 5. Open questions for the advisor

Record answers in `signoff-record.md` or as Issues:

- Are there industry-specific received-document patterns where our search fields
  fall short?
- For operators with 繰越欠損金, should the product surface a stronger prompt to
  raise retention beyond 10 years?
- Any 電帳法 guidance updates since this package's date that affect the posture?

---

## 6. How to verify claims yourself

- Read the binding doc: [`received-document-compliance.md`](../explanation/received-document-compliance.md)
- API contract: [`docs/openapi/openapi.yaml`](../openapi/openapi.yaml)
- Behavior is covered by automated tests (`tests/Document/`, `tests/Export/`,
  `tests/User/`) that assert integrity, search, void/restore, audit, and export.

Last updated: 2026-05-30
