# ADR 0012: File Storage Architecture

## Status

accepted

## Context

Vault stores binary files on operator infrastructure (Tier A shared hosting or Tier B Docker).
Must be secure, backup-friendly, and outside web root.

## Decision

1. **Path layout:** `{storage_root}/vault/{organization_id}/{document_id}/v{version}/{sanitized_filename}`
2. **Storage root:** env `NENE_VAULT_STORAGE_PATH` (default `storage/vault` relative to app root)
3. **Serving files:** authenticated download endpoint streams from disk — no direct public URL to blob path
4. **Backups:** operator responsibility; docs mandate filesystem backup + DB backup together
5. **Tier A:** local disk on shared host; **Tier B:** mounted volume in Docker

Object storage (S3-compatible) is **Phase 4+** via adapter interface — not MVP.

## Consequences

- Implement `DocumentStorageInterface` with local filesystem adapter first.
- Max file size and MIME allowlist enforced at upload boundary.

## Related

- [`../explanation/requirements.md`](../explanation/requirements.md) §3
