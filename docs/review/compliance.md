# Compliance Self-Review Checklist

**Binding.** Use this checklist for **any change** that touches:

- Document storage, file serving, or download
- Metadata creation or editing
- Search functionality
- Audit trail / audit_events
- Retention policy or enforcement
- Void / restore
- Export (manifest CSV, ZIP)
- Retention period configuration

A compliance review is **not optional** for these areas. If any item fails, block the PR until resolved. Deviations from `received-document-compliance.md` require an ADR with professional sign-off.

---

## File integrity (§3.1)

- [ ] File bytes are never overwritten. Replacement creates a new `document_version`.
- [ ] `file_sha256` is computed at upload and stored in `document_version`.
- [ ] Hash is verified on every authenticated download. Hash mismatch → 500, logged.
- [ ] Storage path is never exposed in API responses.
- [ ] MIME type is validated against the allowlist (`application/pdf`, `image/jpeg`, `image/png`).
- [ ] File size is validated against the org's configured max.

## Metadata audit trail (§3.2)

- [ ] Changes to `transaction_date`, `amount_cents`, `counterparty_name`, `category`, `tags` each create an `audit_event` with old and new values.
- [ ] `audit_event` is written in the **same DB transaction** as the metadata mutation.
- [ ] No in-place overwrite of authoritative metadata without an audit event.

## Void semantics (§3.3)

- [ ] Void records `voided_at`, `voided_by`, `void_reason` (mandatory).
- [ ] Voided documents remain in the database with `status = 'voided'`.
- [ ] Voided documents are excluded from default search (but accessible via `include_voided=true`).
- [ ] Voided documents still enforce the retention period.
- [ ] Restore (`document.restored`) requires admin capability and creates an audit event.
- [ ] Hard delete of file bytes during the retention window is not possible.

## Provenance (§3.4)

- [ ] Upload records: `file_sha256`, `original_filename`, `mime_type`, `version_number`, `uploaded_at`, `uploaded_by`, `source`.
- [ ] `source = scan_upload` triggers a スキャナ保存 advisory warning in the response.

## Duplicate detection (§3.5)

- [ ] Same `file_sha256` within `organization_id` → warn operator; do not silently auto-accept or reject.

## Search requirements (§4.2)

- [ ] Search by `transaction_date` range works correctly.
- [ ] Search by `amount_cents` range works correctly.
- [ ] Search by `counterparty_name` partial match works correctly.
- [ ] Two-field AND combinations work (e.g. date + counterparty).
- [ ] Three-field AND combination works.
- [ ] Search is NOT broken by this change. (Run search tests.)

## Retention enforcement (§5)

- [ ] `retention_expires_at` is computed correctly: `transaction_date + retention_years`.
- [ ] If `transaction_date` is null, `uploaded_at + retention_years` is used and `date_uncertain = true` is set.
- [ ] No document can be purged before `retention_expires_at <= now()`.
- [ ] Changing `vault_settings.retention_years` does NOT shorten retention on existing documents.
- [ ] Default `retention_years = 10` is not changed without ADR 0004 review.

## Audit events (§7 / ADR 0014)

- [ ] Every mutation that requires an audit event has one (see event type table in `terminology.md`).
- [ ] `audit_events` are written in the same transaction as the mutation.
- [ ] `audit_events` are never updated or deleted by application code in this change.
- [ ] Before/after snapshots do not contain secrets (passwords, tokens, storage paths).

## Organization scoping

- [ ] Every query on a tenant-scoped table includes `organization_id`.
- [ ] Cross-tenant reads are impossible via this change.

## OCR policy (§8)

- [ ] If OCR is involved: suggested values go to `suggested_*` fields only.
- [ ] No OCR value is auto-promoted to authoritative without explicit operator confirmation.

## Export (§10)

- [ ] Export is read-only — it does not modify any stored data.
- [ ] Manifest CSV includes: `document_id`, `version`, `transaction_date`, `amount_cents`, `counterparty_name`, `category`, `file_sha256`, `uploaded_at`, `voided_at`.

## Scope (§11 / scope-contract)

- [ ] This change does NOT add: invoice issuance, reconciliation, dunning, CSV mapping, journal entries, tax computation, or expense approval.
- [ ] If in doubt, check `docs/explanation/scope-contract.md` DON'T list.

---

## Verification command

```bash
composer check
```

Run compliance-critical test suite:

```bash
composer test:compliance
```

(When defined in Phase 1.)
