# Domain Model

NeNe Vault entities and rules — all tenant-scoped. See also:
[`requirements.md`](./requirements.md), [`terminology.md`](./terminology.md),
[`received-document-compliance.md`](./received-document-compliance.md).

---

## Entity relationship (MVP)

```
organization ──────────────────────────────────────── 1
    │
    ├─── vault_settings (1 per org)
    │
    ├─── users (many)
    │
    └─── vault_documents (many)
             │
             ├─── document_versions (many; 1..n, immutable)
             │         ┌────────────────────────────────────┐
             │         │  version_number monotonic per doc  │
             │         │  file_sha256, mime_type, path      │
             │         └────────────────────────────────────┘
             │
             ├─── document_links (0..many; optional)
             │         ┌────────────────────────────────────┐
             │         │  sibling_product, entity_type,     │
             │         │  entity_id (HTTP reference only)   │
             │         └────────────────────────────────────┘
             │
             └─── audit_events (many; append-only)
                       ┌────────────────────────────────────┐
                       │  action, actor, before_json,       │
                       │  after_json, created_at            │
                       └────────────────────────────────────┘
```

All tenant-scoped tables carry `organization_id`. Cross-tenant reads are
prohibited; see [ADR 0006](../adr/0006-multi-tenancy-and-roles.md).

---

## vault_document

Logical document — the stable identity for one received business document
regardless of how many versions have been uploaded.

| Field | Type | Notes |
| --- | --- | --- |
| `id` | ULID | Primary key |
| `organization_id` | UUID | Tenant scope |
| `current_version_id` | UUID | FK → document_version (latest) |
| `transaction_date` | DATE nullable | 取引年月日 from the received document |
| `amount_cents` | INT nullable | Amount in integer JPY; null if document carries no amount |
| `counterparty_name` | VARCHAR | 取引先名; required for search; free text |
| `category` | ENUM | `invoice_received` \| `contract` \| `receipt` \| `delivery_note` \| `other` |
| `tags` | JSON | Free-form tag array |
| `status` | ENUM | `active` \| `voided` — see state machine below |
| `voided_at` | TIMESTAMP nullable | Set when voided |
| `voided_by` | UUID nullable | FK → user |
| `void_reason` | VARCHAR nullable | Required when voided |
| `void_note` | TEXT nullable | Optional extended context |
| `date_uncertain` | BOOLEAN | True when transaction_date is null or OCR-unconfirmed |
| `is_metadata_confirmed` | BOOLEAN | True when operator has confirmed all key metadata fields |
| `retention_years` | INT | Copied from vault_settings at upload time; immutable after creation |
| `retention_expires_at` | DATE | Computed: transaction_date (or uploaded_at) + retention_years |
| `suggested_transaction_date` | DATE nullable | OCR suggestion (Phase 4+); not authoritative until confirmed |
| `suggested_amount_cents` | INT nullable | OCR suggestion (Phase 4+) |
| `suggested_counterparty_name` | VARCHAR nullable | OCR suggestion (Phase 4+) |
| `uploaded_at` | TIMESTAMP | When the first version was created (system receipt) |
| `uploaded_by` | UUID | FK → user (original uploader) |

### vault_document state machine

```
                         ┌──────────────────┐
                         │   (upload)       │
                         ▼                  │
                    ┌─────────┐             │ version_added
                    │ active  │─────────────┘ (new document_version created;
                    └────┬────┘               current_version_id updated)
                         │
                         │ void (reason required)
                         ▼
                    ┌─────────┐
                    │ voided  │◄────────────────────────────────────────┐
                    └────┬────┘                                         │
                         │                                              │
                         │ restore (admin only, audit event required)   │
                         └─────────────────────────────────────────────┘
```

**Rules:**

- A document enters `active` immediately upon successful upload.
- `active` documents are editable for metadata (transaction_date, amount_cents,
  counterparty_name, category, tags) with an audit trail on every change.
- `active` documents may receive new file versions (`version_added`); each new
  version becomes `current_version_id`.
- Any status → `voided`: requires `void_reason`; records `document.voided` audit
  event. Void is **not** hard delete. The document remains in the DB.
- `voided` → `active`: requires `admin` capability; records `document.restored`.
- There is **no draft state** — an upload IS the document creation. Partial or
  failed uploads must not create a `vault_document` row.
- `voided` documents are excluded from default search results but accessible via
  `include_voided=true` parameter.
- Purge (`document.purged`) is only valid when `status = voided` AND
  `retention_expires_at <= now()` AND admin has completed the export procedure
  (ADR 0004 §5.4).

---

## document_version

Immutable file record. One vault_document has 1..n versions. Prior versions
are permanently accessible.

| Field | Type | Notes |
| --- | --- | --- |
| `id` | ULID | Primary key |
| `vault_document_id` | UUID | FK → vault_document |
| `organization_id` | UUID | Denormalized tenant scope |
| `version_number` | INT | Monotonic per vault_document; starts at 1 |
| `file_path` | VARCHAR | Relative to storage root; never exposed in API |
| `file_sha256` | VARCHAR(64) | Hex; verified on every download |
| `mime_type` | VARCHAR | Validated against allowlist at upload |
| `original_filename` | VARCHAR | As supplied by upload client |
| `file_size_bytes` | INT | Stored at upload |
| `source` | ENUM | `web_upload` \| `email_inbound` \| `api` \| `scan_upload` |
| `uploaded_at` | TIMESTAMP | When this version was received |
| `uploaded_by` | UUID | FK → user |

Storage path layout (ADR 0012):
`{NENE_VAULT_STORAGE_PATH}/vault/{organization_id}/{document_id}/v{version_number}/{sanitized_filename}`

File bytes are **never overwritten** after creation. Replacing a document
creates `version_number + 1`.

---

## document_link

Optional reference to a sibling product entity. Non-authoritative — Vault is
never the SSOT for Invoice or Clear data (ADR 0002, ADR 0009).

| Field | Type | Notes |
| --- | --- | --- |
| `id` | ULID | Primary key |
| `vault_document_id` | UUID | FK → vault_document |
| `organization_id` | UUID | Tenant scope |
| `sibling_product` | ENUM | `nene_invoice` \| `nene_clear` |
| `entity_type` | VARCHAR | e.g. `invoice`, `client`, `bank_transaction` |
| `entity_id` | VARCHAR | ID as known to the sibling product's API |
| `created_at` | TIMESTAMP | |
| `created_by` | UUID | FK → user |

Create and delete are both audited. No amount sync from siblings into Vault
metadata.

---

## vault_settings

One row per organization. Configures retention and optional sibling integration.

| Field | Type | Notes |
| --- | --- | --- |
| `organization_id` | UUID | PK + FK → organization |
| `retention_years` | INT | Default 10; minimum 7 (with warning); see ADR 0004 |
| `storage_path_override` | VARCHAR nullable | Override for NENE_VAULT_STORAGE_PATH per org |
| `invoice_api_base_url` | VARCHAR nullable | Optional; for link validation only |
| `invoice_api_token` | VARCHAR nullable | Encrypted; for link validation only |
| `clear_api_base_url` | VARCHAR nullable | Optional; for link validation only |
| `clear_api_token` | VARCHAR nullable | Encrypted; for link validation only |
| `updated_at` | TIMESTAMP | |
| `updated_by` | UUID | FK → user |

Changes to `vault_settings` are audited via `vault_settings.changed` events.

---

## audit_events

Append-only compliance log. Schema and rules: [ADR 0014](../adr/0014-audit-event-schema.md).

**Immutability:** No UPDATE or DELETE is permitted on this table by application
code or DB user role. Audit records outlive the documents they describe.

---

## Organization and user

Shared entities (NeNe ecosystem pattern from ADR 0006):

- **organization** — the tenant. `id`, `name`, `slug`, `plan`, `is_active`,
  `created_at`.
- **user** — operator account. `id`, `organization_id` (NULL for superadmin),
  `email`, `password_hash`, `role`, `status` (`active` \| `invited`),
  `created_at`.

---

## Planned modules (`src/`)

| Module | Responsibility |
| --- | --- |
| `Organization/` | Tenants + per-request resolution |
| `Auth/` | JWT, Role, Capability, middleware |
| `User/` | Operator accounts within organization |
| `VaultSettings/` | Retention and sibling link config |
| `Document/` | vault_document CRUD, upload, void/restore, search |
| `DocumentVersion/` | File storage, version creation, hash verification |
| `DocumentLink/` | Optional sibling links |
| `Audit/` | AuditRecorder, audit_events write; history read |
| `Export/` | Manifest CSV + ZIP export |
| `Integration/SiblingLink/` | Optional HTTP clients for Invoice/Clear link validation |

---

## Related

- Requirements: [`requirements.md`](./requirements.md)
- Compliance (binding): [`received-document-compliance.md`](./received-document-compliance.md)
- ADR 0004: Retention period calculation
- ADR 0006: Multi-tenancy and roles
- ADR 0011: Integrity method
- ADR 0012: File storage architecture
- ADR 0014: Audit event schema
