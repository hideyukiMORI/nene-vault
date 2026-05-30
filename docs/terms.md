# Canonical Terms Registry

**Status: binding — single source of truth for all identifier spellings.**

> **Absolute adherence — non-negotiable.**
> Any identifier in PHP code, database schema, JSON API, OpenAPI, environment
> variables, or documentation that is **not registered here**, or that **deviates
> from the canonical form listed here**, is a defect and **blocks merge**. There
> is no "close enough."
>
> When introducing or renaming **any** identifier, update this file **in the same
> PR**. If the new term is a product concept, also update
> [`docs/explanation/glossary.md`](explanation/glossary.md) in the same PR.

**Meanings and context:** [`docs/explanation/glossary.md`](explanation/glossary.md)
**Legal/compliance terms for 士業 review:** [`docs/explanation/terminology.md`](explanation/terminology.md)
**Naming rules and patterns:** [`docs/development/naming-conventions.md`](development/naming-conventions.md)
**UI display text (ja + en):** [`locales/`](../locales) — keyed by the codes registered here; see [`docs/development/locale-guide.md`](development/locale-guide.md)

---

## How to read this file

| Column | Meaning |
| --- | --- |
| **Canonical form** | The exact string to use — copy it verbatim |
| **Context** | Where it appears (DB table, DB column, JSON field, PHP class, etc.) |
| **Japanese label** | Operator-facing label in ja locale (if applicable) |
| **DO NOT use** | Known wrong variants — using any of these is a defect |

---

## 1. PHP — Namespace and product identity

| Canonical form | Context | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `NeneVault` | PHP namespace root | — | `NeneVault2`, `NeNe_Vault`, `Vault`, `neneVault` |
| `NeneVault\` | Namespace prefix (with backslash) | — | `App\`, `Vault\` |

---

## 2. PHP — Class naming suffixes and patterns

These are patterns, not registered individual class names. Follow them exactly.

| Pattern | Role | Example | DO NOT use |
| --- | --- | --- | --- |
| `{Verb}{Noun}Handler` | HTTP handler | `UploadDocumentHandler` | `Controller`, `Action`, `Endpoint` suffix |
| `{Verb}{Noun}UseCaseInterface` | Use case interface | `UploadDocumentUseCaseInterface` | `ServiceInterface`, `ManagerInterface` |
| `{Verb}{Noun}UseCase` | Use case implementation | `UploadDocumentUseCase` | `Service`, `Manager` suffix without UseCase |
| `{Verb}{Noun}Input` | Use case input DTO | `UploadDocumentInput` | `Request`, `Command`, `DTO` as suffix |
| `{Verb}{Noun}Output` | Use case output DTO | `UploadDocumentOutput` | `Response`, `Result`, `DTO` as suffix |
| `{Entity}RepositoryInterface` | Repository interface | `VaultDocumentRepositoryInterface` | `DaoInterface`, `StoreInterface` |
| `Pdo{Entity}Repository` | PDO repository implementation | `PdoVaultDocumentRepository` | `{Entity}Repository` without `Pdo` prefix |
| `{Entity}{Reason}Exception` | Domain exception | `VaultDocumentNotFoundException` | `{Entity}Error`, `{Entity}Exception` without reason |
| `{Purpose}ServiceProvider` | DI service provider | `DocumentServiceProvider` | `Provider` alone, `Container` |
| `execute` | Use case method (always this name) | `execute(UploadDocumentInput $input)` | `run`, `handle`, `invoke`, `process` |

---

## 3. PHP — Domain entity class names

| Canonical form | DB table | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `VaultDocument` | `vault_documents` | 受取書類 | `Document` alone, `VaultDoc`, `ReceivedDocument` |
| `DocumentVersion` | `document_versions` | 文書バージョン | `DocVersion`, `FileVersion`, `Version` alone |
| `DocumentLink` | `document_links` | 関連書類リンク | `SiblingLink`, `Link` alone, `DocLink` |
| `AuditEvent` | `audit_events` | 監査ログ | `AuditLog`, `Log`, `Event` alone |
| `VaultSettings` | `vault_settings` | 保管設定 | `Settings` alone, `Config`, `VaultConfig` |
| `Organization` | `organizations` | 組織（テナント） | `Tenant`, `Company`, `Org` alone |
| `User` | `users` | ユーザー | `Operator`, `Account`, `Member` as class name |

---

## 4. PHP — Module (src/) folders

| Canonical form | Responsibility | DO NOT use |
| --- | --- | --- |
| `Document/` | vault_document CRUD, upload, search, void/restore | `Documents/`, `VaultDocument/`, `Docs/` |
| `DocumentVersion/` | File storage, version creation, hash verification | `Version/`, `File/`, `Storage/` alone |
| `DocumentLink/` | Optional sibling HTTP reference links | `Link/`, `SiblingLink/` |
| `Audit/` | AuditRecorder, audit_events write + history read | `AuditLog/`, `Log/` |
| `Export/` | Manifest CSV + ZIP export | `Download/`, `Reports/` |
| `VaultSettings/` | Retention + sibling link config per org | `Settings/` alone, `Config/` |
| `Organization/` | Tenant resolution, CRUD | `Tenant/`, `Org/` |
| `Auth/` | JWT, Role, Capability, middleware | `Security/`, `Login/` |
| `User/` | Operator accounts | `Accounts/`, `Members/` |
| `Http/` | PSR-7/15 helpers (thin) | `Controller/`, `Request/` |
| `Integration/SiblingLink/` | HTTP clients for Invoice/Clear link validation | `Integration/Siblings/`, `Upstream/` |

---

## 5. PHP — Infrastructure class names

| Canonical form | Role | DO NOT use |
| --- | --- | --- |
| `DocumentStorageInterface` | File storage abstraction | `FileStorageInterface`, `StorageInterface` alone |
| `LocalFilesystemDocumentStorage` | Local filesystem adapter | `LocalStorage`, `FileSystem`, `DiskStorage` |
| `AuditRecorder` | Records audit_events (UseCase layer) | `AuditLogger`, `AuditWriter`, `Logger` alone |
| `AuditRecorderInterface` | AuditRecorder contract | `AuditInterface`, `LoggerInterface` for this purpose |
| `OrgResolverMiddleware` | Per-request organization resolution | `TenantMiddleware`, `OrganizationMiddleware` |
| `CapabilityMiddleware` | Capability enforcement per route | `AuthMiddleware`, `PermissionMiddleware` |
| `BearerTokenMiddleware` | JWT bearer token verification (NENE2) | `JwtMiddleware`, `AuthenticationMiddleware` |
| `TokenVerifierInterface` | JWT verification contract (NENE2) | `JwtVerifier`, `TokenValidator` |

---

## 6. Database — Table names (plural snake_case)

| Canonical form | PHP entity | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `organizations` | `Organization` | 組織 | `tenants`, `companies`, `orgs` |
| `users` | `User` | ユーザー | `accounts`, `members`, `operators` |
| `vault_documents` | `VaultDocument` | 受取書類 | `documents`, `files`, `vault_doc` |
| `document_versions` | `DocumentVersion` | 文書バージョン | `versions`, `file_versions`, `doc_versions` |
| `document_links` | `DocumentLink` | 関連書類リンク | `links`, `sibling_links`, `doc_links` |
| `audit_events` | `AuditEvent` | 監査ログ | `audit_logs`, `logs`, `events` |
| `vault_settings` | `VaultSettings` | 保管設定 | `settings`, `configs`, `vault_config` |

---

## 7. Database — Column names (snake_case)

### Common to all tenant-scoped tables

| Canonical form | Type | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `id` | ULID / UUID primary key | — | `document_id` as PK, `seq_id` |
| `organization_id` | UUID NOT NULL | 組織ID | `org_id`, `tenant_id`, `company_id` |
| `created_at` | TIMESTAMP | — | `created`, `create_date` |

### vault_documents columns

| Canonical form | Type | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `current_version_id` | UUID FK → document_versions | — | `version_id`, `file_id`, `latest_version_id` |
| `transaction_date` | DATE nullable | 取引年月日 | `document_date`, `tx_date`, `date`, `transaction_at` |
| `amount_cents` | INT nullable | 取引金額（円整数） | `amount`, `price_cents`, `total_cents`, `value_cents` |
| `counterparty_name` | VARCHAR | 取引先名 | `vendor_name`, `supplier_name`, `party_name`, `counterparty` alone |
| `category` | ENUM | 書類種別 | `type`, `document_type`, `doc_category` |
| `tags` | JSON | タグ | `tag`, `labels`, `keywords` |
| `status` | ENUM | 状態 | `state`, `document_status`, `doc_state` |
| `voided_at` | TIMESTAMP nullable | 無効化日時 | `deleted_at`, `archived_at`, `void_date` |
| `voided_by` | UUID nullable FK → users | 無効化者 | `deleted_by`, `void_user_id` |
| `void_reason` | VARCHAR nullable | 無効化理由 | `reason`, `void_note` alone, `delete_reason` |
| `void_note` | TEXT nullable | 無効化補足 | `note`, `comment`, `void_description` |
| `date_uncertain` | BOOLEAN | 日付不確定フラグ | `date_unknown`, `uncertain_date`, `no_date` |
| `is_metadata_confirmed` | BOOLEAN | メタデータ確認済み | `confirmed`, `metadata_confirmed`, `is_confirmed` |
| `retention_years` | INT | 保存年数 | `keep_years`, `store_years`, `retention_period` |
| `retention_expires_at` | DATE | 保存期限 | `expiry_date`, `expires_at`, `retain_until` |
| `suggested_transaction_date` | DATE nullable | OCR提案：取引日 | `ocr_date`, `suggested_date`, `proposed_date` |
| `suggested_amount_cents` | INT nullable | OCR提案：金額 | `ocr_amount`, `suggested_amount` |
| `suggested_counterparty_name` | VARCHAR nullable | OCR提案：取引先 | `ocr_counterparty`, `suggested_counterparty` |
| `uploaded_at` | TIMESTAMP | アップロード日時 | `created_at` (when distinct from row creation), `upload_date` |
| `uploaded_by` | UUID FK → users | アップロード者 | `user_id`, `creator_id`, `upload_user_id` |

### document_versions columns

| Canonical form | Type | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `vault_document_id` | UUID FK | — | `document_id` alone, `doc_id` |
| `version_number` | INT | バージョン番号 | `version`, `ver`, `version_id`, `number` |
| `file_path` | VARCHAR | — (never exposed in API) | `path`, `storage_path`, `file_location` |
| `file_sha256` | VARCHAR(64) | — | `sha256`, `hash`, `file_hash`, `checksum` |
| `mime_type` | VARCHAR | MIMEタイプ | `content_type`, `mimetype`, `file_type` |
| `original_filename` | VARCHAR | 元ファイル名 | `filename`, `file_name`, `name` |
| `file_size_bytes` | INT | ファイルサイズ（バイト） | `size`, `file_size`, `byte_size` |
| `source` | ENUM | アップロード元 | `upload_source`, `origin`, `type` |

### document_links columns

| Canonical form | Type | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `vault_document_id` | UUID FK | — | `document_id` alone |
| `sibling_product` | ENUM | 連携製品 | `product`, `service`, `system` |
| `entity_type` | VARCHAR | エンティティ種別 | `type`, `record_type`, `resource_type` |
| `entity_id` | VARCHAR | エンティティID | `remote_id`, `resource_id`, `external_id` |
| `created_by` | UUID FK → users | 作成者 | `user_id`, `actor_id` |

### audit_events columns

| Canonical form | Type | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `actor_user_id` | UUID nullable FK | 操作者 | `user_id`, `created_by`, `performed_by` |
| `action` | VARCHAR | アクション | `event`, `type`, `event_type`, `operation` |
| `entity_type` | VARCHAR | エンティティ種別 | `resource_type`, `object_type` |
| `entity_id` | UUID | エンティティID | `resource_id`, `object_id` |
| `before_json` | JSON nullable | 変更前 | `old_json`, `previous_json`, `before` alone |
| `after_json` | JSON nullable | 変更後 | `new_json`, `after` alone |
| `source` | VARCHAR | 操作元 | `channel`, `origin` |
| `metadata_json` | JSON nullable | 補足情報 | `extra_json`, `data_json`, `context_json` |

### vault_settings columns

| Canonical form | Type | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `storage_path_override` | VARCHAR nullable | ストレージパス上書き | `storage_path`, `path_override` |
| `invoice_api_base_url` | VARCHAR nullable | Invoice API ベースURL | `invoice_url`, `invoice_api_url` |
| `invoice_api_token` | VARCHAR nullable | Invoice API トークン | `invoice_token`, `invoice_api_key` |
| `clear_api_base_url` | VARCHAR nullable | Clear API ベースURL | `clear_url`, `clear_api_url` |
| `clear_api_token` | VARCHAR nullable | Clear API トークン | `clear_token`, `clear_api_key` |
| `updated_by` | UUID FK → users | 更新者 | `modified_by`, `changed_by` |

---

## 8. Database — Index and constraint names

| Pattern | Example | DO NOT use |
| --- | --- | --- |
| `idx_{table}_{columns}` | `idx_vault_documents_org_date` | `{table}_{columns}_idx`, `ix_…` |
| `uniq_{table}_{columns}` | `uniq_document_versions_doc_ver` | `uq_…`, `unique_…` |
| `fk_{table}_{column}` | `fk_vault_documents_org_id` | `{table}_{column}_fkey` |

---

## 9. Enum values — status

| Canonical form | Japanese label | DO NOT use |
| --- | --- | --- |
| `active` | 有効 | `enabled`, `normal`, `live`, `open`, `published` |
| `voided` | 無効（論理削除） | `deleted`, `archived`, `disabled`, `inactive`, `removed` |

---

## 10. Enum values — category

| Canonical form | Japanese label | DO NOT use |
| --- | --- | --- |
| `invoice_received` | 受取請求書 | `invoice`, `received_invoice`, `bill_received` |
| `contract` | 契約書 | `contracts`, `agreement` |
| `receipt` | 領収書 | `receipts`, `voucher` |
| `delivery_note` | 納品書 | `delivery`, `packing_slip`, `shipping_note` |
| `other` | その他 | `misc`, `unknown`, `general` |

---

## 11. Enum values — source

| Canonical form | Japanese label | DO NOT use |
| --- | --- | --- |
| `web_upload` | Web アップロード | `web`, `upload`, `manual`, `ui_upload` |
| `email_inbound` | メール受信 | `email`, `mail`, `inbound` alone |
| `api` | API | `rest`, `api_upload`, `programmatic` |
| `scan_upload` | スキャンアップロード | `scan`, `scanner`, `scanned`, `paper` |

---

## 12. Enum values — sibling_product (document_links)

| Canonical form | Japanese label | DO NOT use |
| --- | --- | --- |
| `nene_invoice` | NeNe Invoice | `invoice`, `nene-invoice`, `neNe_invoice` |
| `nene_clear` | NeNe Clear | `clear`, `nene-clear`, `neNe_clear` |

---

## 13. Roles and capabilities

### Role values (Role enum)

| Canonical form | Scope | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `superadmin` | Cross-tenant | スーパー管理者 | `super_admin`, `root`, `owner`, `platform_admin` |
| `admin` | Single organization | 管理者 | `administrator`, `manager`, `org_admin` |
| `member` | Single organization | メンバー | `user`, `staff`, `operator` as enum value |
| `viewer` | Single organization | 閲覧者 (Phase 2+) | `reader`, `readonly`, `view_only` |

### Capability values (Capability enum)

| Canonical form | Granted to | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `manage_organizations` | superadmin | 組織管理 | `admin_organizations`, `org_management` |
| `manage_users` | superadmin, admin | ユーザー管理 | `admin_users`, `user_management` |
| `manage_vault_settings` | superadmin, admin | 保管設定管理 | `admin_settings`, `settings_management` |
| `upload_document` | superadmin, admin, member | 書類アップロード | `create_document`, `add_document`, `upload` alone |
| `edit_metadata` | superadmin, admin; member (own) | メタデータ編集 | `update_document`, `modify_metadata`, `edit_document` |
| `void_document` | superadmin, admin | 書類無効化 | `delete_document`, `archive_document`, `remove_document` |
| `view_documents` | all roles | 書類閲覧 | `read_documents`, `list_documents`, `search_documents` as capability |
| `export_documents` | superadmin, admin | 書類エクスポート | `download_documents`, `export` alone |

---

## 14. Audit event actions (dot-notation strings)

| Canonical form | entity_type | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `document.uploaded` | `vault_document` | 書類アップロード | `document.created`, `document.added`, `document.upload` |
| `document.metadata_changed` | `vault_document` | メタデータ変更 | `document.updated`, `document.edited`, `document.modified` |
| `document.voided` | `vault_document` | 書類無効化 | `document.deleted`, `document.removed`, `document.archived` |
| `document.restored` | `vault_document` | 書類復元 | `document.unvoided`, `document.recovered`, `document.activated` |
| `document.version_added` | `document_version` | バージョン追加 | `document.replaced`, `document.updated_file`, `version.created` |
| `document.exported` | `vault_document` | 書類エクスポート | `document.downloaded`, `export.created` |
| `document.purged` | `vault_document` | 書類削除（保存期限後） | `document.deleted`, `document.hard_deleted`, `document.removed` |
| `document.link_created` | `document_link` | リンク作成 | `link.created`, `document.linked` |
| `document.link_deleted` | `document_link` | リンク削除 | `link.deleted`, `document.unlinked` |
| `vault_settings.changed` | `vault_settings` | 保管設定変更 | `settings.updated`, `settings.changed`, `vault_settings.updated` |

---

## 15. HTTP route paths

Admin routes are under `/admin/vault/…` (vault-scoped namespace).

| Canonical form | Method | Description | DO NOT use |
| --- | --- | --- | --- |
| `/health` | GET | Health check (unauthenticated) | `/api/health`, `/ping`, `/status` |
| `/admin/vault/documents` | GET | Search documents | `/admin/documents`, `/vault/documents` |
| `/admin/vault/documents` | POST | Upload document | — |
| `/admin/vault/documents/{id}` | GET | Get document detail | `/admin/vault/document/{id}` (singular) |
| `/admin/vault/documents/{id}/metadata` | PATCH | Update metadata | `/admin/vault/documents/{id}` for metadata-only |
| `/admin/vault/documents/{id}/void` | POST | Void document | `/admin/vault/documents/{id}/delete` |
| `/admin/vault/documents/{id}/restore` | POST | Restore voided document | `/admin/vault/documents/{id}/unvoid` |
| `/admin/vault/documents/{id}/history` | GET | Audit history for document | `/admin/vault/documents/{id}/audit`, `/admin/vault/documents/{id}/log` |
| `/admin/vault/documents/{id}/versions/{versionId}/download` | GET | Download file version | `/admin/vault/documents/{id}/file`, `/admin/vault/documents/{id}/download` |
| `/admin/vault/export` | POST | Trigger manifest export | `/admin/vault/documents/export`, `/admin/export` |
| `/admin/vault/settings` | GET | Get vault settings | `/admin/settings`, `/admin/vault/config` |
| `/admin/vault/settings` | PATCH | Update vault settings | — |
| `/admin/audit-events` | GET | List audit events | `/admin/audit-logs`, `/admin/logs` |
| `/admin/organizations` | GET, POST | Organization CRUD (superadmin) | `/admin/tenants` |
| `/admin/organizations/{id}` | GET, PATCH, DELETE | Single org (superadmin) | `/admin/tenants/{id}` |
| `/admin/users` | GET, POST | User CRUD (admin) | `/admin/accounts` |
| `/admin/users/{id}` | GET, PATCH, DELETE | Single user (admin) | — |

---

## 16. OpenAPI operationId values (camelCase)

| Canonical form | Method + Route | DO NOT use |
| --- | --- | --- |
| `getHealth` | GET /health | `healthCheck`, `ping` |
| `uploadDocument` | POST /admin/vault/documents | `createDocument`, `addDocument` |
| `searchDocuments` | GET /admin/vault/documents | `listDocuments`, `getDocuments` |
| `getDocumentById` | GET /admin/vault/documents/{id} | `getDocument`, `fetchDocument` |
| `updateDocumentMetadata` | PATCH /admin/vault/documents/{id}/metadata | `editDocumentMetadata`, `patchDocument` |
| `voidDocument` | POST /admin/vault/documents/{id}/void | `deleteDocument`, `archiveDocument` |
| `restoreDocument` | POST /admin/vault/documents/{id}/restore | `unvoidDocument`, `activateDocument` |
| `getDocumentHistory` | GET /admin/vault/documents/{id}/history | `getDocumentAudit`, `listDocumentEvents` |
| `downloadDocumentVersion` | GET /admin/vault/documents/{id}/versions/{versionId}/download | `downloadDocument`, `getDocumentFile` |
| `exportDocuments` | POST /admin/vault/export | `createExport`, `generateExport` |
| `getVaultSettings` | GET /admin/vault/settings | `getSettings`, `fetchSettings` |
| `updateVaultSettings` | PATCH /admin/vault/settings | `patchSettings`, `editSettings` |
| `listAuditEvents` | GET /admin/audit-events | `getAuditLogs`, `listLogs` |
| `createOrganization` | POST /admin/organizations | `addOrganization`, `addTenant` |
| `listOrganizations` | GET /admin/organizations | `getOrganizations`, `listTenants` |
| `getOrganizationById` | GET /admin/organizations/{id} | `getOrganization`, `getTenant` |
| `updateOrganization` | PATCH /admin/organizations/{id} | `editOrganization` |
| `deleteOrganization` | DELETE /admin/organizations/{id} | `removeOrganization` |
| `createUser` | POST /admin/users | `addUser`, `inviteUser` |
| `listUsers` | GET /admin/users | `getUsers` |
| `getUserById` | GET /admin/users/{id} | `getUser` |
| `updateUser` | PATCH /admin/users/{id} | `editUser` |
| `deleteUser` | DELETE /admin/users/{id} | `removeUser` |

---

## 17. JSON request / response field names (snake_case)

Standard fields used across multiple responses:

| Canonical form | Type | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `id` | string (ULID) | — | `document_id`, `uuid`, `guid` as top-level ID |
| `organization_id` | string | 組織ID | `org_id`, `tenant_id` |
| `status` | string enum | 状態 | `state`, `document_status` |
| `transaction_date` | string (ISO 8601 date) | 取引年月日 | `date`, `tx_date`, `document_date` |
| `amount_cents` | integer \| null | 取引金額（円整数） | `amount`, `price_cents`, `total` |
| `counterparty_name` | string | 取引先名 | `vendor`, `supplier`, `counterparty` alone |
| `category` | string enum | 書類種別 | `type`, `document_type` |
| `tags` | array of strings | タグ | `labels`, `keywords` |
| `file_sha256` | string (64-char hex) | SHA-256ハッシュ | `sha256`, `hash`, `checksum` |
| `mime_type` | string | MIMEタイプ | `content_type`, `type` alone |
| `original_filename` | string | 元ファイル名 | `filename`, `file_name`, `name` |
| `file_size_bytes` | integer | ファイルサイズ | `size`, `file_size` |
| `source` | string enum | アップロード元 | `origin`, `channel` |
| `version_number` | integer | バージョン番号 | `version`, `ver` |
| `uploaded_at` | string (ISO 8601) | アップロード日時 | `created_at` (when distinct from row creation) |
| `uploaded_by` | string (user id) | アップロード者ID | `user_id`, `creator_id` |
| `voided_at` | string \| null (ISO 8601) | 無効化日時 | `deleted_at`, `archived_at` |
| `voided_by` | string \| null | 無効化者ID | `deleted_by` |
| `void_reason` | string \| null | 無効化理由 | `reason` alone |
| `date_uncertain` | boolean | 日付不確定 | `date_unknown`, `no_date` |
| `is_metadata_confirmed` | boolean | メタデータ確認済み | `confirmed`, `metadata_confirmed` |
| `retention_years` | integer | 保存年数 | `keep_years`, `retention` alone |
| `retention_expires_at` | string (ISO 8601 date) | 保存期限 | `expires_at`, `expiry` |
| `actor_user_id` | string \| null | 操作者ID | `user_id`, `performed_by` |
| `action` | string | アクション | `event`, `type`, `operation` |
| `before_json` | object \| null | 変更前 | `old_value`, `previous` |
| `after_json` | object \| null | 変更後 | `new_value`, `current` |
| `items` | array | アイテム一覧 | `data`, `results`, `records`, `list` |
| `limit` | integer | 取得件数上限 | `page_size`, `per_page`, `count` |
| `offset` | integer | オフセット | `skip`, `start`, `from` |

---

## 18. OpenAPI schema names (PascalCase)

| Canonical form | Use | DO NOT use |
| --- | --- | --- |
| `VaultDocumentResponse` | Single document response | `DocumentResponse`, `VaultDoc` |
| `VaultDocumentListResponse` | List of documents | `DocumentListResponse`, `DocumentsResponse` |
| `UploadDocumentRequest` | Upload request body | `CreateDocumentRequest`, `NewDocumentRequest` |
| `UpdateDocumentMetadataRequest` | Metadata patch body | `EditDocumentRequest`, `PatchDocumentRequest` |
| `VoidDocumentRequest` | Void request body | `DeleteDocumentRequest` |
| `DocumentVersionResponse` | Single version response | `VersionResponse`, `FileVersionResponse` |
| `AuditEventResponse` | Single audit event response | `AuditLogResponse`, `LogEntryResponse` |
| `AuditEventListResponse` | List of audit events | `AuditLogsResponse` |
| `VaultSettingsResponse` | Settings response | `SettingsResponse`, `ConfigResponse` |
| `UpdateVaultSettingsRequest` | Settings patch body | `PatchSettingsRequest`, `EditSettingsRequest` |
| `ExportDocumentsRequest` | Export request body | `CreateExportRequest` |
| `ProblemDetails` | RFC 9457 error response | `ErrorResponse`, `ApiError`, `Problem` |
| `ValidationProblemDetails` | Validation error response (extends ProblemDetails) | `ValidationErrorResponse` |

---

## 19. Problem Details type slugs (kebab-case after base URL)

Base URL: `https://nene-vault.dev/problems/`

| Canonical slug | Full type URI | HTTP status | DO NOT use |
| --- | --- | --- | --- |
| `validation-failed` | `…/validation-failed` | 422 | `invalid-input`, `bad-request`, `validation-error` |
| `document-not-found` | `…/document-not-found` | 404 | `not-found`, `resource-not-found`, `document-missing` |
| `retention-window-active` | `…/retention-window-active` | 409 | `cannot-delete`, `retention-block`, `document-locked` |
| `mime-type-not-allowed` | `…/mime-type-not-allowed` | 415 | `unsupported-media-type`, `invalid-file-type` |
| `file-too-large` | `…/file-too-large` | 413 | `payload-too-large`, `file-size-exceeded` |
| `duplicate-file` | `…/duplicate-file` | 409 | `file-exists`, `duplicate-hash`, `file-duplicate` |
| `organization-not-found` | `…/organization-not-found` | 404 | `tenant-not-found` |
| `unauthorized` | `…/unauthorized` | 401 | `not-authenticated`, `authentication-required` |
| `forbidden` | `…/forbidden` | 403 | `access-denied`, `not-authorized`, `permission-denied` |
| `internal-server-error` | `…/internal-server-error` | 500 | `server-error`, `unexpected-error` |

---

## 20. Environment variables (UPPER_SNAKE_CASE)

| Canonical form | Required / Optional | Description | DO NOT use |
| --- | --- | --- | --- |
| `NENE_VAULT_STORAGE_PATH` | Required | Root directory for file storage | `STORAGE_PATH`, `VAULT_STORAGE_PATH`, `FILE_PATH` |
| `NENE_VAULT_JWT_SECRET` | Required | JWT signing secret | `JWT_SECRET`, `VAULT_JWT_SECRET`, `JWT_KEY` |
| `NENE_VAULT_PORT` | Optional | HTTP port (default 8080) | `PORT`, `VAULT_PORT`, `APP_PORT` |
| `NENE_VAULT_APP_ENV` | Optional | `local` / `test` / `production` | `APP_ENV`, `ENV`, `ENVIRONMENT` |
| `NENE_VAULT_MAX_FILE_SIZE_MB` | Optional | Max upload size in MB (default 20) | `MAX_FILE_SIZE`, `UPLOAD_LIMIT` |
| `DB_HOST` | Required | Database host | `DATABASE_HOST`, `MYSQL_HOST` |
| `DB_PORT` | Optional | Database port | `DATABASE_PORT`, `MYSQL_PORT` |
| `DB_NAME` | Required | Database name | `DATABASE_NAME`, `MYSQL_DATABASE` |
| `DB_USER` | Required | Database user | `DATABASE_USER`, `MYSQL_USER` |
| `DB_PASSWORD` | Required | Database password | `DATABASE_PASSWORD`, `MYSQL_PASSWORD` |
| `DB_CHARSET` | Optional | Database charset (default utf8mb4) | `DATABASE_CHARSET` |

---

## 21. MCP tool names (Phase 4+)

| Canonical form | Same as operationId | Japanese label | DO NOT use |
| --- | --- | --- | --- |
| `searchDocuments` | `searchDocuments` | 書類検索 | `search`, `findDocuments`, `queryDocuments` |
| `getDocumentById` | `getDocumentById` | 書類取得 | `getDocument`, `fetchDocument` |
| `getDocumentHistory` | `getDocumentHistory` | 書類履歴取得 | `getHistory`, `getAuditLog` |

---

## Appendix: Known risky near-miss pairs

These pairs are easy to confuse. Both spellings look plausible but only one is correct.

| CORRECT | WRONG | Why |
| --- | --- | --- |
| `amount_cents` | `amount` | No float money ever |
| `amount_cents` | `total_cents` | Different semantics (`total` implies computed total) |
| `transaction_date` | `transaction_at` | Date only, not timestamp |
| `transaction_date` | `document_date` | Statutory name is 取引年月日 |
| `file_sha256` | `file_hash` | Algorithm is part of the name |
| `file_sha256` | `sha256` | Must include `file_` prefix |
| `original_filename` | `filename` | No abbreviation |
| `original_filename` | `file_name` | No underscore split |
| `version_number` | `version` | Field is the number, not the entity |
| `voided_at` | `deleted_at` | Vault uses void semantics, never delete |
| `voided_at` | `archived_at` | Archive has different meaning |
| `status` | `document_status` | Column is just `status` |
| `vault_documents` | `documents` | Must be prefixed |
| `audit_events` | `audit_logs` | Events, not logs |
| `counterparty_name` | `counterparty` | Always include `_name` suffix |
| `upload_document` | `create_document` | Upload is the domain verb |
| `edit_metadata` | `update_document` | Metadata edit, not document update |
| `void_document` | `delete_document` | Vault never deletes |
| `document.uploaded` | `document.created` | Upload is the domain event |
| `document.metadata_changed` | `document.updated` | Specific to metadata |
| `document.voided` | `document.deleted` | Vault never deletes |
| `nene_invoice` | `nene-invoice` | Underscore in DB/PHP enums |

---

## How to add a new identifier

1. Decide the canonical form following patterns in §2–§20.
2. Add a row to the appropriate section in this file.
3. If it is a product concept with a meaning, add it to `docs/explanation/glossary.md` in the same PR.
4. If it has a Japanese label for the operator UI, add it to the appropriate locale catalog when frontend lands.
5. The PR is **blocked** until this file is updated first.

Last updated: 2026-05-30
