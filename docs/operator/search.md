# Search Guide

## 電帳法 search requirements

The 電子帳簿保存法 (電帳法) requires that received electronic records be
**immediately searchable** by:

1. **取引年月日** — transaction date
2. **取引金額** — transaction amount
3. **取引先名** — counterparty name

NeNe Vault satisfies these requirements through the document search API and the
admin UI.

---

## Using the admin UI

### Basic search

On the **Documents** page:

| Field | Description | 電帳法 requirement |
|---|---|---|
| **取引年月日（開始）** | Transaction date from (YYYY-MM-DD) | ✅ Date range |
| **取引年月日（終了）** | Transaction date to (YYYY-MM-DD) | ✅ Date range |
| **取引先名** | Counterparty name (partial match) | ✅ Counterparty |
| **取引金額（最小）** | Minimum amount in yen (integer) | ✅ Amount range |
| **取引金額（最大）** | Maximum amount in yen (integer) | ✅ Amount range |
| **書類種別** | Category filter | — |
| **無効化済みを含む** | Include voided documents | — |

### Example: tax audit search

To produce all documents for a given fiscal year (e.g., FY 2025):

1. Set **取引年月日（開始）** = `2025-01-01`
2. Set **取引年月日（終了）** = `2025-12-31`
3. Click **検索**
4. Click **エクスポート** → choose **ZIP** to download the full archive

This produces a ZIP containing `manifest.csv` plus every document file —
suitable for handing to a tax auditor.

### Quick single-amount lookup

To find a specific invoice by amount (e.g., ¥110,000):

1. Set **取引金額（最小）** = `110000`
2. Set **取引金額（最大）** = `110000`
3. Optionally add a counterparty name fragment
4. Click **検索**

---

## Search behavior

- **Date fields**: exact match on `transaction_date` (ISO 8601 DATE). Documents
  with `date_uncertain = true` should be reviewed and updated.
- **Counterparty name**: case-insensitive partial (LIKE `%name%`).
- **Amount**: range query on `amount_cents` (integer yen × 100 is NOT used —
  `amount_cents` stores yen directly as integers).
  - Example: ¥110,000 → `amount_cents = 110000`
- **Category**: exact match on the category slug (e.g. `invoice_received`,
  `contract`, `receipt`, `other`).
- **Voided documents**: excluded by default; pass `include_voided=true` to include.

---

## API search (REST)

```http
GET /admin/vault/documents?transaction_date_from=2025-01-01&transaction_date_to=2025-12-31&counterparty_name=株式会社&limit=50
Authorization: Bearer <token>
```

See `docs/openapi/openapi.yaml` → `listDocuments` for the full parameter list.

---

## Date-uncertain documents

Documents with `date_uncertain = true` appear in all date-range searches but
are flagged in the UI and in the export manifest. They must be resolved by
editing the metadata (`transaction_date`) before a tax audit.

To find all date-uncertain documents, use the API directly:

```http
GET /admin/vault/documents?date_uncertain=true
```

(UI filter for `date_uncertain` is planned for a future release.)
