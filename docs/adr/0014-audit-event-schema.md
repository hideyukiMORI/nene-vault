# ADR 0014: Audit Event Schema — UseCase-Layer Recording with Before/After

## Status

accepted

## Context

`received-document-compliance.md` §3 (真実性の確保 under 電帳法) and §7
(audit trail) require that every mutating operation on a vault document or vault
settings record who changed what, including the **before and after state**.
A tax inspector (税務調査官) or auditor must be able to reconstruct the full
history of any document.

We need a consistent mechanism that works across all current and future vault
domains (documents, settings, links) without ad-hoc per-entity logging.

Three implementation layers to consider:

1. **Middleware-level logging** — rejected; middleware sees the HTTP request but
   not the domain before/after state; cannot name the business action.
2. **Repository-level logging** — rejected; repositories know the row but not the
   actor (authentication context) nor the business action name.
3. **UseCase-level recording via an `AuditRecorder`** (chosen, mirroring NeNe
   Invoice ADR 0008) — the use case has the actor/tenant context, the
   before/after entity state, and the business action name. This is the only
   layer where the mutation is meaningful.

## Decision

A dedicated `audit_events` table records one row per mutating operation.

### Schema

| Column | Type | Meaning |
| --- | --- | --- |
| `id` | ULID | Primary key |
| `organization_id` | UUID | Tenant; NULL only for superadmin system events |
| `actor_user_id` | UUID nullable | Authenticated user; NULL for system-generated events |
| `action` | VARCHAR | `{entity}.{verb}` — see §Event types |
| `entity_type` | VARCHAR | `vault_document` \| `document_version` \| `document_link` \| `vault_settings` |
| `entity_id` | UUID | ID of the affected entity |
| `before_json` | JSON nullable | Sanitized snapshot of entity **before** mutation; NULL for create events |
| `after_json` | JSON nullable | Sanitized snapshot of entity **after** mutation; NULL for delete/void events |
| `created_at` | TIMESTAMP | When the event was recorded |
| `source` | VARCHAR | `web_upload` \| `email_inbound` \| `api` \| `scan_upload` \| `system` |
| `metadata_json` | JSON nullable | Event-specific extra data (e.g. `void_reason`, `export_filter`) |

### Event types (required for MVP)

| Action | entity_type | before_json | after_json | Notes |
| --- | --- | --- | --- | --- |
| `document.uploaded` | `vault_document` | NULL | full snapshot | Also records document_version created |
| `document.metadata_changed` | `vault_document` | snapshot before | snapshot after | Any change to transaction_date, amount_cents, counterparty_name, category, tags |
| `document.voided` | `vault_document` | active snapshot | voided snapshot | `metadata_json.void_reason` required |
| `document.restored` | `vault_document` | voided snapshot | restored snapshot | Requires admin capability |
| `document.version_added` | `document_version` | NULL | version snapshot | Replacement file upload |
| `document.exported` | `vault_document` | — | — | `metadata_json.export_filter` (date range, counterparty, etc.) |
| `document.purged` | `vault_document` | final snapshot | NULL | Post-retention; requires §5.4 procedure |
| `document.link_created` | `document_link` | NULL | link snapshot | Optional Invoice/Clear link added |
| `document.link_deleted` | `document_link` | link snapshot | NULL | Link removed |
| `vault_settings.changed` | `vault_settings` | settings before | settings after | Includes retention_years changes |

### Sanitization rules

- `before_json` and `after_json` are produced by the same presenters used for
  API output — **secrets are never written to the audit log**.
- File bytes are **never** written to audit events; only metadata and hash.
- Internal system fields not exposed in the API (DB row IDs used as PKs, etc.)
  may be included in sanitized snapshots for traceability.

### Recording location

- Recording happens in the **UseCase layer** via `Audit\AuditRecorder`.
- Use cases receive tenant context and actor from `AuthContext`; they fetch the
  "before" state before executing the mutation; they pass both to `AuditRecorder`
  after the mutation commits.
- Recording is in the **same DB transaction** as the mutation — the audit event
  row must not be written without the mutation, and vice versa. A transaction
  boundary wrapping mutation + audit is required from Phase 1.

### Immutability of audit records

- `audit_events` rows **MUST NOT** be updated or deleted by application code
  after creation.
- No soft-delete or status column on `audit_events`.
- Audit records are subject to the same 10-year retention default as the
  documents they describe (ADR 0004).
- Database-level permissions: the application user for normal operations has
  INSERT on `audit_events` but NOT UPDATE or DELETE. Enforcement is at the DB
  permission layer, not only application code.

## Consequences

**Benefits**

- Satisfies 電帳法 真実性の確保 (訂正削除の履歴方式) and the audit trail
  requirement from `received-document-compliance.md` §3 and §7.
- Uniform trail across all vault mutations; auditors and tax inspectors have a
  single place to check history.
- Before/after snapshots allow field-level diff without a separate diff table.
- Transactional consistency: the mutation and its audit record commit together.

**Costs / limitations**

- Every mutating use case gains an `AuditRecorder` dependency and must fetch the
  "before" state.
- `audit_events` table grows continuously; no purge until document retention
  expires (§5.4).
- Snapshot JSON size grows with entity complexity — avoid embedding large blobs.

**Follow-up**

- Implement `Audit\AuditRecorder` in Phase 1 runtime (Issue #4+).
- Add `GET /admin/vault/documents/{id}/history` and
  `GET /admin/audit-events` (admin/superadmin) in Phase 1.
- Wrap mutation + audit in a single DB transaction from Phase 1 (not a follow-up
  retrofit — this is a Phase 1 requirement).

## Related

- Compliance: [`../explanation/received-document-compliance.md`](../explanation/received-document-compliance.md) §3, §7
- Domain model: [`../explanation/domain-model.md`](../explanation/domain-model.md)
- Requirements: [`../explanation/requirements.md`](../explanation/requirements.md) §4 (history endpoint)
- Reference: NeNe Invoice ADR 0008 (same pattern)
- Supersedes: none
- Superseded by: none
