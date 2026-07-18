# Review 3 Package — Pre-Production Final Verification (NeNe Vault, 2026)

**For:** the licensed 税理士 / 公認会計士 who recorded Review 2
(辻村 拓也 / 辻村総合会計事務所, 2026-05-31) — or an equivalent licensed
professional — to complete the **pre-production go-live gate**.

**Why this package exists:** Review 2 **approved** development and test-environment
implementation, but attached a condition (`signoff-record.md` → Review 2 §Conditions):

> 本番環境への移行（プロダクションリリース）承認の前提として、テスト環境での
> 計算結果および出力帳票サンプルの最終検証を別途実施すること。→ 本番リリース前に
> Review 3 を追加すること。

This package supplies that test-environment evidence so Review 3 can be recorded.
Review 1/2 already confirmed the **posture**; Review 3 verifies the **actual
outputs** a tax inspection would see.

> **Not legal advice.** Engineering's interpretation of 電子帳簿保存法 for received
> 電子取引データ. We ask you to confirm the produced samples are defensible for
> a 税務調査 handoff and record the decision in `signoff-record.md` (Review 3 block).

**Binding source of truth:** [`received-document-compliance.md`](../explanation/received-document-compliance.md).
**Posture mapping (already reviewed):** [`2026-tax-advisor-review-package.md`](./2026-tax-advisor-review-package.md).

---

## 1. What changed since Review 2

Nothing in the compliance posture. Review 2 reviewed the design; since then the
ecosystem features shipped (MCP read/write tools, S3 storage adapter, email
inbound, OCR assist — all Phase 4) **without altering** integrity, search,
retention, audit, or export behavior. OCR remains suggest-only with human
confirmation (compliance §8). This Review 3 is therefore an **output verification**,
not a re-review of design.

State of the build at package date: `composer check` and
`npm run check --prefix frontend` both green (287 backend tests, full frontend
suite, PHPStan 0 errors, locale + OpenAPI + MCP catalogs valid).

---

## 2. Condition 1 — Test-environment output samples to verify

Generate these from a seeded test environment and attach them (or regenerate
live) when recording Review 3. Commands assume `docker compose up` (API :8600).

### 2.1 Manifest CSV (`POST /admin/vault/export`, `format=csv`)

Column order is fixed in code (`ExportDocumentsUseCase::MANIFEST_HEADER`):

```
document_id,version,transaction_date,amount_cents,counterparty_name,category,file_sha256,uploaded_at,voided_at
```

| Column | Meaning | Verify against |
| --- | --- | --- |
| `document_id` | Stable document identifier | `vault_documents.id` |
| `version` | Version number of the served file | `document_versions.version_number` |
| `transaction_date` | 取引年月日 (ISO date) | metadata |
| `amount_cents` | 取引金額 in integer JPY (no decimals; JPY has none) | metadata |
| `counterparty_name` | 取引先名 | metadata |
| `category` | Document category | metadata |
| `file_sha256` | Integrity hash of the served version | `document_versions.file_sha256` |
| `uploaded_at` | Provenance timestamp | `document_versions` |
| `voided_at` | Non-empty ⇒ logically deleted (void), not erased | `vault_documents.voided_at` |

**To confirm:** that these columns are sufficient for a 税務調査 handoff
(this was Review 2 Q5; Review 3 confirms it against a real exported file, not the
spec). No storage path or secret appears in any column (hard rule).

### 2.2 Export ZIP (`format=zip`)

Structure produced by `ExportDocumentsUseCase::buildZip`:

```
manifest.csv
files/{document_id}/v{version_number}/{stored_filename}
```

The same `manifest.csv` as §2.1, plus each document's original-format bytes.
**To confirm:** opening the ZIP, the manifest rows map 1:1 to the files under
`files/`, and each file opens in its original format (見読可能性 / legibility).

### 2.3 Retention dates (ADR 0004 — the "計算結果" in Condition 1)

The only date Vault *computes* is `retention_expires_at`. Verify the anchor and
default against ADR 0004 with at least these cases:

| Case | transaction_date | Expected `retention_expires_at` | Note |
| --- | --- | --- | --- |
| Calendar-year, mid-Jan | 2024-01-15 | 2034-01-15 | 10y from transaction date — covers the 7y-from-filing-deadline minimum |
| Late-December | 2024-12-28 | 2034-12-28 | safe-side margin smaller but still ≥ statutory |
| Unknown date | (none) | 10y from `uploaded_at`, flagged `date_uncertain` | compliance §5.3 |

**To confirm:** the 10-year-from-transaction-date default (Review 2 Q2, "妥当"
in test environment) holds in the produced data, and `date_uncertain` is flagged
where the transaction date is unknown.

### 2.4 Integrity round-trip (SHA-256)

Download a version via `GET …/versions/{versionId}/download` and confirm the
served bytes hash to the `file_sha256` shown in the manifest. The download path
verifies the hash **before** serving (`DownloadDocumentVersionUseCase`); a
mismatch must fail closed.

### 2.5 Correction-history evidence (void + metadata change)

For one sample document, produce: an edit of 取引金額/取引先, then a void, then a
restore. Export the audit trail (`listVaultAuditEvents` / audit UI) and confirm
each mutation carries before/after and actor/timestamp, and that the document is
never hard-deleted within retention (訂正削除の履歴, 規則 第4条第1項第2号).

---

## 3. Condition 2 — Master-setting flexibility for law changes

Review 2 condition 2: keep logic changeable via master settings so 電帳法 / 国税庁
告示 changes can be absorbed without code surgery, and add a re-review block on
any such change.

**To confirm:** the retention anchor/period (ADR 0004) and the gate process
(`signoff-record.md` "Process notes": any 電帳法 amendment is treated as a **P0**
re-review) are documented and operable. Note for the reviewer: the retention
default is currently a code-level constant per ADR 0004 — if you require it to be
an operator-editable master setting before go-live, record that as a Review 3
condition and we will open an Issue + ADR.

---

## 4. How to record the decision

Append a **Review 3** block to
[`signoff-record.md`](./signoff-record.md) using the template there:

- Decision: **Approved** ⇒ go-live gate closes; operators may run in production.
- **Approved with conditions** ⇒ list each as a checkbox + open an Issue; any
  deviation from the compliance doc needs an ADR with this sign-off (compliance §0.2).
- **Changes required** ⇒ production stays blocked.

Then update the "Review status" table in `signoff-record.md` and check off the
**税理士 Review 3** item in the private `nene-origin/internal-docs/vault/todo/current.md`.

---

## 5. Open questions for Review 3

- Are the manifest columns (§2.1) and ZIP layout (§2.2) what you would hand an
  inspector unchanged, or do you want additional columns (e.g. document source,
  `date_uncertain` flag) surfaced in the CSV?
- Should `retention_expires_at` become an operator-editable master setting before
  go-live (§3), or is the ADR 0004 constant acceptable for the first release?
- Any 電帳法 guidance updates since 2026-05-31 that change the posture?
- **見読可能性 and in-browser display (proposed scope item D11, Issue #225).**
  Today an operator reads a stored document by downloading it and opening it
  locally; the Admin UI does not render the bytes. We have a candidate feature
  that would display image/PDF bytes inline, re-computing SHA-256 in the browser
  and **refusing to render on mismatch**. Three questions, in order:
  1. Is the current download-and-open flow sufficient for 見読可能性の確保, or
     would inline display materially help at an actual 税務調査?
  2. Would adding it **widen the scope you approved at Review 2** — i.e. does it
     need your re-confirmation rather than being an implementation detail of the
     already-approved D9 (Admin UI)?
  3. If it is in scope, should Review 3 verify it (a sample to inspect), or is it
     out of Review 3's remit?

  We have **not** implemented it and have **not** amended
  [`scope-contract.md`](../explanation/scope-contract.md); both are deliberately
  held until this question is answered.

---

## 6. Verify claims yourself

- Export behavior: `src/Export/ExportDocumentsUseCase.php`; tests
  `tests/Export/ExportApiTest.php`, `tests/Export/ExportBoundaryTest.php`
- Retention: [ADR 0004](../adr/0004-retention-period-calculation.md)
- Integrity/download, void/restore, audit: `tests/Document/`
- API contract: [`docs/openapi/openapi.yaml`](../openapi/openapi.yaml)

Last updated: 2026-07-16 (§5: added the D11 / 見読可能性 question, #229)
