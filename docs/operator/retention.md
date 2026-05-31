# Retention Policy

## Default: 10 years from transaction date

Every document uploaded to NeNe Vault receives a `retention_expires_at` date
calculated as:

```
retention_expires_at = transaction_date + retention_years
```

The **default `retention_years` is 10**. This conservative default covers all
common statutory obligations without requiring fiscal-year configuration (see
[ADR 0004](../adr/0004-retention-period-calculation.md) for the full rationale).

---

## Why not 7 years (the statutory minimum)?

The 電帳法 / 法人税法 statutory minimum is "7 years from the filing deadline of
the relevant fiscal year." For a document dated January 15, 2024:

```
Fiscal year ends:        2024-12-31
Tax return due:          2025-02-28
7-year obligation ends:  2032-02-28   ← statutory minimum

"7 years from transaction_date" expires: 2031-01-15  ← 13 months too short
```

NeNe Vault anchors on `transaction_date` (not the filing deadline) to avoid
coupling to accounting-layer data it must not own. A 10-year anchor from
`transaction_date` covers all common cases without fiscal-year configuration.

---

## Setting retention years per organization

1. Log in as **admin**.
2. Navigate to **Settings** (vault settings).
3. Change **保存年数** to the desired value (7–99).
4. Save. The change creates an audit event with before/after values.

**Warning threshold:** If you set fewer than 10 years, the UI displays:
> 保存年数が10年未満です。法令上の保存義務（申告期限から7年）が満たされることを税理士に確認してください。

This warning does NOT block saving — it is a reminder to confirm with your 税理士.

---

## Date-uncertain documents

If `transaction_date` is not known at upload, the system uses `uploaded_at` as the
anchor and sets `date_uncertain = true`. The operator should update the metadata
(`transaction_date`) as soon as the correct date is known. The retention date is
recalculated automatically when metadata is saved.

---

## After retention_expires_at

NeNe Vault **never automatically deletes documents**. After `retention_expires_at`,
a document is **eligible** for deletion, but deletion requires an explicit operator
action (not yet implemented — Phase 4+). Until then, all documents are retained
indefinitely.

To find documents past their retention date:

- Use the search filters to narrow by date range.
- The export manifest CSV includes `retention_expires_at` for each document.

---

## 繰越欠損金 (loss carryforward) — extended obligation

If your organization carries net operating losses, the 法人税法 extends the
retention obligation to **10 years from the filing deadline**. This means documents
from early in the fiscal year may require up to ~12 years from `transaction_date`.

In this case, raise `retention_years` to **12 or 13** and confirm the exact
number with your 税理士.
