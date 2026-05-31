# Export Guide

## Purpose

The export feature produces:

- A **manifest CSV** listing all matching documents with their metadata and SHA-256
  hashes (電帳法 §10 tax audit response).
- Optionally, a **ZIP archive** containing the manifest CSV plus every matched
  document file — ready to hand to a tax auditor.

---

## Formats

| Format | Contents | Use case |
|---|---|---|
| **ZIP** (default) | `manifest.csv` + `files/{doc_id}/v{n}/{filename}` | Tax audit, archive, data migration |
| **CSV** | `manifest.csv` only | Quick metadata review, spreadsheet import |

---

## Using the admin UI

1. Navigate to **エクスポート** in the sidebar.
2. Set date range, counterparty filter, and/or voided inclusion as needed.
3. Choose **出力形式**:
   - **ZIP（書類ファイル＋マニフェスト）** — full archive
   - **CSVのみ（マニフェスト）** — metadata only
4. Click **エクスポート開始**.
5. The file downloads automatically.

---

## Manifest CSV columns

| Column | Description |
|---|---|
| `document_id` | ULID of the document |
| `version` | Version number of the file included |
| `transaction_date` | 取引年月日 (ISO 8601 date) |
| `amount_cents` | 取引金額（円・整数） |
| `counterparty_name` | 取引先名 |
| `category` | 書類種別 |
| `file_sha256` | SHA-256 hex of the document file |
| `uploaded_at` | Upload timestamp (ISO 8601) |
| `voided_at` | Void timestamp, blank if not voided |

---

## ZIP archive structure

```
vault-export-20260531-120000.zip
  manifest.csv
  files/
    01JXXXXXXXXXXXXXXXXXXXXXXX/      ← document_id
      v1/
        invoice_2026_03.pdf
    01JYYY.../
      v1/
        receipt.jpg
```

---

## API export

```http
POST /admin/vault/export
Authorization: Bearer <token>
Content-Type: application/json

{
  "format": "zip",
  "transaction_date_from": "2025-01-01",
  "transaction_date_to": "2025-12-31",
  "counterparty_name": "株式会社サンプル",
  "include_voided": false
}
```

Response:
- `format: csv` → `Content-Type: text/csv; charset=utf-8`
- `format: zip` → `Content-Type: application/zip`

Both return `Content-Disposition: attachment; filename="vault-export-YYYYMMDD-HHmmss.{ext}"`.

---

## Audit events

Every exported document generates an `document.exported` audit event with:

- `actor_user_id` — who triggered the export
- `metadata_json.export_filter` — the filter criteria used

This ensures that all export activity is traceable for 電帳法 compliance.

---

## Tax audit response workflow

1. Receive a request from the NTA (国税庁) or a tax audit.
2. Note the fiscal year / period in question.
3. Open **エクスポート** → set the date range for the fiscal year → select **ZIP**.
4. Download and verify the ZIP:
   - Open `manifest.csv` and confirm all expected documents are present.
   - Check that `voided_at` is blank for all active documents (or intentionally
     voided with a void reason).
5. Provide the ZIP to the auditor. The `file_sha256` column allows the auditor
   to verify file integrity independently.
