# ADR 0004: Retention Period Calculation — Anchor on Transaction Date, Default 10 Years

## Status

accepted

> **Engineering interpretation of 電子帳簿保存法 and related tax laws — not
> legal advice.** The calculation described here reflects a conservative, safe
> interpretation. Operators with specific fiscal-year or loss-carryforward
> situations must confirm the applicable period with their 税理士.

## Context

NeNe Vault must enforce a minimum retention period on every stored document.
The Japanese statutory minimum for received electronic records is "7 years from
the filing deadline of the fiscal year to which the transaction belongs" (see
`received-document-compliance.md` §1 and §5 for the full statutory basis).

### The problem with "7 years from transaction date"

A naïve implementation would retain documents for 7 years from
`transaction_date`. This appears correct but is systematically shorter than the
statutory minimum in many cases:

```
Example — calendar-year corporation:

  Transaction date:    2024-01-15  (in fiscal year 2024)
  Fiscal year ends:    2024-12-31
  Tax return due:      2025-02-28  (2 months after FY end — basic case)
  Statutory 7-year period: 2025-02-28 → 2032-02-28

  "7 years from transaction_date" retains until: 2031-01-15
  Shortfall: 13 months  ← non-compliant
```

For a late-December transaction the shortfall is smaller (~3 months), but for
January transactions in a December fiscal year it exceeds 13 months.

The gap exists because Vault does not require operators to configure their fiscal
year or filing deadline — and it should not, as that is accounting-layer
information.

### Why not calculate exactly?

Exact calculation requires:
1. Operator's fiscal year end (配当基準日 / 決算月) — varies widely
2. Tax return filing mode (extended deadline of 3 months for certain entities,
   or up to 6 months with NTA approval)
3. Tax type (法人税, 所得税, 消費税 have the same 7-year rule but may have
   different fiscal reference points for individual operators)
4. Whether a 繰越欠損金 extends to 10 years

Collecting and validating this data to drive retention calculations would couple
Vault to accounting logic it must not own (scope-contract X4). The correct
answer is: use a conservative default that makes the calculation unnecessary.

### Why 10 years as the default

10 years from `transaction_date` covers:

| Obligation | Required duration from transaction | 10-year anchor |
| --- | --- | --- |
| 法人税 7 years from filing deadline | Up to ~9 years from transaction (FY end + 2 months filing + 7 years) | ✅ covered |
| 消費税 7 years from filing deadline | Same calculation | ✅ covered |
| 会社法 10 years from fiscal year end | Up to ~10.5 years from early-year transaction | ⚠️ edge case: Jan transaction in Dec FY — need 10 years 6 months from transaction. Configure 11 years if required. |
| 所得税 7 years from filing deadline | Up to ~9 years from transaction | ✅ covered |
| 繰越欠損金 up to 10 years from filing deadline | Up to ~12 years from early-year transaction | ⚠️ operator must configure 12–13 years manually if applicable |

For the vast majority of SMB operators, a 10-year anchor from `transaction_date`
is safe. For operators with 繰越欠損金 or unusual fiscal years, the
`retention_years` setting can be raised to 12–13 years with 税理士 guidance.

Alternatives considered:

1. **7 years from transaction_date (default)** — rejected; systematically shorter
   than the statutory minimum for early-year transactions (up to 13 months short).
2. **Require fiscal-year configuration, compute exact date** — rejected; introduces
   accounting-domain logic Vault must not own; complex to validate; incorrect
   fiscal-year input silently produces wrong dates.
3. **8 years from transaction_date** — considered; covers most 法人税/消費税 cases
   but still leaves a gap for calendar-year corps with very early transactions
   and 会社法 obligations.
4. **10 years from transaction_date (chosen)** — covers all common cases without
   fiscal-year configuration; aligns with 会社法 requirement; easy for operators
   and advisors to verify.

## Decision

1. **Retention clock anchors on `transaction_date`.**
   If `transaction_date` is null, `uploaded_at` is used as a conservative
   substitute and the document is flagged `date_uncertain = true` (see
   `received-document-compliance.md` §5.3).

2. **Default `retention_years = 10`.**
   `retention_expires_at = transaction_date + retention_years`.
   This replaces the earlier draft requirement of "default 7 years" — that
   calculation is insufficient.

3. **Configurable range: 7–99 years.**
   - Minimum 7: the operator asserts their statutory obligation is covered; system
     shows a warning: "A retention period below 10 years may not cover all
     statutory obligations. Confirm with your 税理士 that the 7-year period
     running from your filing deadline is satisfied."
   - Maximum 99: no artificial ceiling; permanent retention is a valid policy.

4. **`retention_years` is per-organization** (in `vault_settings`).
   Individual documents inherit the organization's setting at upload time and
   store the calculated `retention_expires_at` as a fixed date — changing the
   org setting afterwards does NOT retroactively shorten the retention on
   existing documents. It may only lengthen them (the system recomputes and
   takes the later of the two dates).

5. **Any change to `vault_settings.retention_years` creates a
   `vault_settings.changed` audit event** with before/after values and actor.

## Consequences

**Benefits**

- Eliminates the 13-month shortfall for early-year transactions without requiring
  fiscal-year configuration.
- A 税理士 or 公認会計士 reviewing the system finds a clearly safe anchor that
  requires no additional calculation.
- Retroactive shortening of retention on existing documents is impossible.

**Costs**

- Default retention extends from 7 to 10 years — operators keep documents longer
  than the statutory minimum, which uses more storage. This is intentional and
  the correct trade-off.
- Operators with 繰越欠損金 (up to 10 years from filing deadline) may still need
  to configure 12–13 years manually; system cannot detect this automatically.

**Follow-up**

- Document the calculation and the warning text in the operator guide (Phase 2).
- Consult 税理士 to confirm the 10-year anchor is universally accepted (Phase 0
  review gate).

## Related

- Compliance: [`../explanation/received-document-compliance.md`](../explanation/received-document-compliance.md) §5
- Requirements: [`../explanation/requirements.md`](../explanation/requirements.md) §6
- Domain model: [`../explanation/domain-model.md`](../explanation/domain-model.md)
- Supersedes: informal "default 7 years" wording in early requirements draft
- Superseded by: none
