# ADR 0005: UI Language Scope — Japanese and English Only

## Status

accepted

## Context

NeNe Vault targets Japan SMB operators subject to 電子帳簿保存法. The product's
domain — received-document search requirements, retention periods, statutory
vocabulary, and audit posture — is grounded in Japanese law and accounting
practice. There is no generalised international equivalent.

As cross-border business activity in Japan grows, some operators and staff use
English as their primary working language. However, extending the UI to additional
locales (Chinese, Korean, etc.) would ship locale strings and operator guides
disconnected from Japanese statutory context, creating false confidence in
regulatory compliance for non-Japan operators without a real operator base for
those locales.

Repository documentation is English-only per ADR 0008. This ADR governs the
**product UI and operator-facing materials** only.

Alternatives considered:

1. **Japanese only** — rejected; excludes English-dominant staff at foreign-owned
   SMBs operating under Japanese law, who are a natural secondary user base.
2. **Full i18n (any locale)** — rejected; Japanese accounting rules do not
   generalise across jurisdictions; shipping locale files for non-Japanese
   jurisdictions implies compliance postures Vault cannot support and would
   mislead operators.
3. **Japanese + English only** (chosen) — covers the actual operator base
   (Japan-registered SMBs including foreign-owned), keeps maintenance scope
   narrow, and explicitly prevents a non-Japan operator from mistaking Vault for
   a product that meets their jurisdiction's requirements.

## Decision

NeNe Vault admin UI and operator-facing materials support
**Japanese (primary) and English (secondary) only**.

- **Admin UI locale catalogs:** `ja` (primary) and `en` (secondary).
- **Operator install guide and system-overview doc:** ja + en.
- **OpenAPI descriptions** and **Problem Details** error metadata: English only
  (ADR 0008 — these are engineering-layer docs, not operator UI).
- **No other locale files** will be merged into this repository. A PR adding a
  third locale will be rejected at review with a pointer to this ADR.
- **Language selector:** toggle ja / en in admin UI; default resolves from
  browser `Accept-Language` header with `ja` as fallback.
- **Statutory field labels** (e.g. 取引年月日, 取引金額, 取引先) appear in
  Japanese in both locales where legally traceable terminology is required.

This restriction applies to the upstream repository only. The MIT licence permits
operators or forks to add additional locales; the upstream project will not
maintain them.

## Consequences

**Benefits**

- Covers the real operator base: domestic SMBs and foreign-owned businesses
  operating under Japanese law.
- Prevents misleading operators in other jurisdictions (Taiwan, Korea, etc.)
  into using a Japan-specific compliance product.
- Keeps locale maintenance narrow, auditable, and honest about product scope.
- Consistent with the narrow-scope philosophy: one noun, one jurisdiction.

**Costs**

- An English-dominant operator must accept a Japanese-primary UI structure and
  statutory labels.
- Future jurisdiction expansion (e.g. a Taiwan edition) would require a fork
  or a separate product, not an i18n flag in this repository.

## Related

- ADR 0008: English-only repository documentation
- [`../explanation/received-document-compliance.md`](../explanation/received-document-compliance.md) — compliance posture is Japan-specific
- [`../explanation/product-vision.md`](../explanation/product-vision.md) — target operators
- Roadmap Phase 2: Admin UI implementation
- Supersedes: none
- Superseded by: none
