# Glossary

Canonical terms for NeNe Vault public docs, OpenAPI descriptions, and code
comments.

> This file defines **meaning and context**. The authoritative **spelling** of
> every identifier (entity names, field names, status values) lives in
> [`terminology.md`](./terminology.md) — spellings here MUST conform to it.

| Term | Definition | Avoid |
| --- | --- | --- |
| **organization** | The tenant — an independent operator (or agency client) with its own users, vault settings, and documents. One install may run as one organization (`single` mode) or multiple (agencies) | "tenant" / "account" / "company" in code identifiers |
| **superadmin** | Cross-tenant platform role; manages organizations (`manage_organizations`). `organization_id` is NULL | "root", "owner", "platform admin" in code |
| **admin** | Organization-scoped role; manages the org's users, vault settings, and all document operations | "manager", "org admin" in code identifiers |
| **member** | Organization-scoped operator; uploads documents and edits own metadata | "user", "staff" in code identifiers |
| **viewer** | Organization-scoped read-only role (Phase 2+); search and download only | — |
| **received document** | A PDF or image **received from a third party** (vendor, landlord, service provider) and stored for compliance. The logical container for one business document identity across all its versions | "uploaded file" as the primary concept; "inbound document" |
| **document version** | An immutable file blob — one physical file upload. The first upload is version 1; each replacement creates a new version with a higher number. All versions remain permanently accessible | "revision" (implies editorial intent); treating version as the primary domain object |
| **vault document** | The logical identity record pointing to the current version. The stable URL and metadata container | "file" as the primary concept |
| **void** | Logical deactivation of a document with a mandatory reason, recorded in the audit log. Does not delete file bytes. The statutory-correct alternative to hard delete | "delete", "archive", "remove" in user-facing copy (for the same action) |
| **transaction date** | 取引年月日 — the date stated on the received document. May differ from the upload date | conflating with `uploaded_at`; using "document date" without clarifying it refers to the transaction |
| **counterparty** | The vendor, landlord, or service provider named on the received document (`counterparty_name`) | "vendor", "supplier" in API field names; "seller" in statutory search contexts |
| **retention window** | The period during which a document must be kept. Computed as `transaction_date + retention_years` (ADR 0004). Default 10 years from transaction date | "expiry", "TTL" — implies purge is automatic without confirmation |
| **audit event** | An append-only record of one mutating operation: who changed what entity, with the before and after state (ADR 0014) | "log entry", "event log" when speaking specifically about compliance trail |
| **cents** | Integer amount in the smallest currency unit. For JPY (Phase 1–3 only), 1 cent = ¥1, so `amount_cents = 1000` means ¥1,000. The `_cents` suffix is a fixed internal convention | float or DECIMAL money; reading `_cents` as 1/100 yen; using "yen" as the field name |
| **電子取引** | The legal category of documents received electronically (email PDF, portal download). Subject to mandatory electronic preservation under 電帳法 第7条 since January 1, 2024 | treating paper-printed copies of electronic originals as sufficient preservation |
| **スキャナ保存** | Scanner preservation — paper documents digitized after receipt. Subject to **different and stricter** requirements than 電子取引; Vault accepts the files but does not manage the additional compliance requirements | treating スキャナ保存 as identical to 電子取引; presenting Vault as certifying スキャナ保存 compliance |
| **訂正削除の履歴方式** | Correction-history method — Vault's chosen method for 真実性の確保. File bytes are immutable, metadata changes are audited with before/after, and void replaces hard delete | "audit trail method" without specifying this is the statutory method being used |
| **事務処理規程** | An internal written procedures document that operators must maintain independently if they rely on it as their 真実性 method. Vault does not generate this document; a template outline will ship in Phase 2+ | implying Vault generates or certifies this document |
| **search requirements (検索要件)** | The statutory requirement to search by 取引年月日, 取引金額, and 取引先 — each individually and in at least two-field combinations (電帳法施行規則 第4条第1項第3号) | treating these as optional convenience features; omitting combination search |
| **retention default** | 10 years from transaction date (ADR 0004). Covers 法人税法, 消費税法, and 会社法 obligations without requiring fiscal-year configuration | "7 years" as the default — this is less than the statutory minimum for many fiscal calendars |
| **source** | The upload channel: `web_upload`, `email_inbound`, `api`, or `scan_upload`. `scan_upload` triggers the スキャナ保存 advisory warning | — |
| **Tier A** | Shared hosting deployment — release ZIP + web installer + MySQL | "rental server tier" in code identifiers |
| **Tier B** | Docker / VPS deployment — Compose + mounted volume | "cloud tier" |
| **handler** | HTTP entry point class | "controller" |
| **use case** | Business logic class with `execute()` | "service" (in the UseCase sense) |
| **UI locale** | Admin UI language. Bound to **ja (primary) + en (secondary) only** (ADR 0005). Distinct from the English-only repository-docs policy | adding locales beyond ja/en; conflating UI locale with compliance document language |

When adding terms, update this file and [`terminology.md`](./terminology.md)
in the same PR.
