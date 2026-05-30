# Terminology (binding)

Canonical English and Japanese terms for NeNe Vault. Spellings here govern
all code identifiers, OpenAPI fields, API responses, and documentation.

> This file defines the **binding spelling** of every term. Meaning and context
> live in [`glossary.md`](./glossary.md). Spellings here take precedence; update
> both files in the same PR when adding a term.

---

## Core domain terms

| Term | Canonical spelling | Japanese label | Definition |
| --- | --- | --- | --- |
| **received document** | `vault_document` (entity) | 受取書類 | A PDF or image received from a third party and stored in Vault |
| **document version** | `document_version` (entity) | 文書バージョン | An immutable file blob generation; corrections create a new version |
| **document link** | `document_link` (entity) | 関連書類リンク | Optional non-authoritative HTTP reference to a sibling-product entity |
| **audit event** | `audit_event` (entity) | 監査ログ | Append-only record of one mutating operation |
| **void** | `voided` (status) | 無効化 | Logical deactivation with audit trail; not hard delete |
| **transaction date** | `transaction_date` (field) | 取引年月日 | Date stated on the received document; distinct from `uploaded_at` |
| **counterparty** | `counterparty_name` (field) | 取引先名 | Name of the vendor, landlord, or service provider on the document |
| **retention window** | `retention_years` (field), `retention_expires_at` (computed) | 保存期間 | Configured years to retain; see ADR 0004 |
| **organization** | `organization` (entity) | 組織（テナント） | The tenant; one independent operator or agency client |
| **vault settings** | `vault_settings` (entity) | 保管設定 | Per-organization retention and sibling link configuration |

---

## Document status values

| Status | Code value | Meaning |
| --- | --- | --- |
| Active | `active` | Document is uploaded and accessible |
| Voided | `voided` | Logically deactivated with reason and audit; excluded from default search |

---

## Document source values

| Source | Code value | Meaning |
| --- | --- | --- |
| Web upload | `web_upload` | Uploaded via admin UI |
| Email inbound | `email_inbound` | Received via inbound email attachment (Phase 3+) |
| API | `api` | Uploaded via REST or MCP API |
| Scan upload | `scan_upload` | Uploaded file is identified as a scanned paper document; スキャナ保存 warning applies |

---

## Category values

| Category | Code value | Japanese | Typical document type |
| --- | --- | --- | --- |
| Invoice received | `invoice_received` | 受取請求書 | Vendor invoice / 請求書 |
| Contract | `contract` | 契約書 | Contract or agreement |
| Receipt | `receipt` | 領収書 | Receipt for payment |
| Delivery note | `delivery_note` | 納品書 | Delivery confirmation |
| Other | `other` | その他 | Any other received document |

---

## Role values

| Role | Code value | Scope | Capabilities |
| --- | --- | --- | --- |
| Superadmin | `superadmin` | Cross-tenant | All, including `manage_organizations` |
| Admin | `admin` | Single organization | All except `manage_organizations` |
| Member | `member` | Single organization | `upload_document`, `edit_metadata` (own uploads) |
| Viewer | `viewer` | Single organization | `view_documents` (Phase 2+) |

---

## Capability values

| Capability | Code value | Granted to |
| --- | --- | --- |
| Manage organizations | `manage_organizations` | superadmin |
| Manage users | `manage_users` | superadmin, admin |
| Manage vault settings | `manage_vault_settings` | superadmin, admin |
| Upload document | `upload_document` | superadmin, admin, member |
| Edit metadata | `edit_metadata` | superadmin, admin; member for own uploads |
| Void document | `void_document` | superadmin, admin |
| View documents | `view_documents` | superadmin, admin, member, viewer |
| Export documents | `export_documents` | superadmin, admin |

---

## Audit event action values

Canonical form: `{entity}.{verb}`. See [ADR 0014](../adr/0014-audit-event-schema.md).

| Action | entity_type |
| --- | --- |
| `document.uploaded` | `vault_document` |
| `document.metadata_changed` | `vault_document` |
| `document.voided` | `vault_document` |
| `document.restored` | `vault_document` |
| `document.version_added` | `document_version` |
| `document.exported` | `vault_document` |
| `document.purged` | `vault_document` |
| `document.link_created` | `document_link` |
| `document.link_deleted` | `document_link` |
| `vault_settings.changed` | `vault_settings` |

---

## Legal terms (appear in docs and UI labels)

| Term | English rendering | Japanese | Usage |
| --- | --- | --- | --- |
| Electronic Books and Records Preservation Act | 電子帳簿保存法 (abbreviated 電帳法) | 電子帳簿保存法 | Statutory basis; use full name at first mention, abbreviated thereafter |
| Electronic transaction | 電子取引 | 電子取引 | Category of received electronic documents under 電帳法 第7条 |
| Scanner preservation | スキャナ保存 | スキャナ保存 | Scanned-paper preservation; different requirements from 電子取引 |
| Integrity assurance | 真実性の確保 | 真実性の確保 | Legal requirement; Vault uses correction-history method |
| Visibility assurance | 可視性の確保 | 可視性の確保 | Legibility + search requirements under 電帳法 規則第4条 |
| Correction-history method | 訂正削除の履歴方式 | 訂正削除の履歴が残るシステム | Chosen integrity method for Vault (ADR 0011) |
| Search requirements | 検索要件 | 検索要件 | Date, amount, counterparty — individually and in combination |
| Retention period | 保存期間 | 保存期間 | Minimum years to retain a document; see ADR 0004 |
| Filing deadline | 申告期限 | 申告期限 | Tax return due date; the statutory anchor for the 7-year retention period |
| Operational procedures document | 事務処理規程 | 事務処理規程 | Written internal procedures required for some integrity methods; operator responsibility |
| Tax audit | 税務調査 | 税務調査 | National Tax Agency inspection; Vault must support export for this purpose |

Last updated: 2026-05-30
