# File Storage

## Layout

Uploaded document files are stored at:

```
{NENE_VAULT_STORAGE_PATH}/vault/{organization_id}/{document_id}/v{version_number}/{sanitized_filename}
```

Example:

```
storage/vault/
  1/                                          ← organization_id
    01JXXXXXXXXXXXXXXXXXXXXX/                 ← document_id (ULID)
      v1/
        invoice_2026_03.pdf
      v2/                                     ← replacement version (if added)
        invoice_2026_03_corrected.pdf
    01JYYYYY.../
      v1/
        receipt.jpg
```

Key properties (ADR 0012):

- **Immutable** — files are never overwritten. A replacement creates a new version
  directory (`v2/`, `v3/`, …).
- **Never exposed** — the storage path does not appear in API responses or the UI.
- **SHA-256 verified** — the hash is computed at upload and re-verified on every
  download. A mismatch is a P0 defect.

---

## Configuration

Set `NENE_VAULT_STORAGE_PATH` in `.env`:

```env
# Relative to project root (default)
NENE_VAULT_STORAGE_PATH=storage/vault

# Absolute path (recommended for production)
NENE_VAULT_STORAGE_PATH=/var/nene-vault/files
```

The directory is created automatically if it does not exist.

**Permissions:** The PHP process (`www-data` in Docker) must have read-write
access to this directory.

```sh
chown -R www-data:www-data /var/nene-vault/files
chmod -R 750 /var/nene-vault/files
```

---

## Disk sizing

| File type | Typical size |
|---|---|
| PDF invoice / receipt | 200 KB – 2 MB |
| JPEG / PNG scan | 500 KB – 5 MB |

At the default 20 MB cap per file, 10 years of 200 documents/month = ~24,000
files. Plan for at least:

- **Low volume** (< 50 docs/month): 50 GB
- **Medium volume** (50–200 docs/month): 200 GB
- **High volume** (200+ docs/month): scale to need, consider S3 (Phase 4)

---

## S3 / cloud storage (Phase 4)

A cloud storage adapter (`DocumentStorageInterface` implementation for S3-compatible
APIs) is planned for Phase 4. Until then, only the local filesystem adapter is
available (ADR 0012).

---

## Allowed MIME types

Only the following types are accepted at upload:

| MIME type | Formats |
|---|---|
| `application/pdf` | PDF |
| `image/jpeg` | JPEG |
| `image/png` | PNG |

Other types are rejected with HTTP 415.
