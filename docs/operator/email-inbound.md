# Email Inbound

NeNe Vault can automatically receive and archive vendor invoices and receipts
delivered by email. PDF, JPEG, and PNG attachments are extracted and uploaded
to the vault with `source: email_inbound`.

---

## How it works

1. Your MTA (mail server) delivers incoming emails as `.eml` files to a watched
   directory (maildir dropbox).
2. `tools/email-inbound.php` scans the directory, parses MIME, and uploads
   allowed attachments via the REST API.
3. Processed files are moved to `{maildir}/processed/`.

The processor runs on demand (CLI) or via cron. No `ext-imap` dependency.

---

## Setup

### 1. Create a dedicated email address

Create a mailbox like `vault@yourdomain.example` or a simple alias that
delivers to the dropbox directory.

### 2. Configure MTA delivery

**Postfix example** — deliver to a directory using `local_destination_concurrency_limit`
and a `.forward` or `master.cf` pipe rule:

```
# /etc/aliases (or ~/.forward for user delivery)
vault: "|/usr/bin/tee /var/mail/vault/$(date +%s%N).eml > /dev/null"
```

Or use `procmail`:
```
# ~/.procmailrc
:0c
/var/mail/vault/${UNIQUE}.eml
```

Make the directory writable by the mail user:
```sh
mkdir -p /var/mail/vault
chown mail:mail /var/mail/vault
chmod 770 /var/mail/vault
```

### 3. Generate a bearer token

```sh
php vendor/hideyukimori/nene2/tools/issue-jwt.php \
  --secret "$NENE2_LOCAL_JWT_SECRET" \
  --sub email-inbound \
  --role admin \
  --org-id 1 \
  --ttl 315360000
```

This generates a long-lived token (10 years) for the inbound processor. Store
it securely (not in `.env` if shared).

### 4. Configure environment

```env
NENE_VAULT_EMAIL_MAILDIR=/var/mail/vault
NENE_VAULT_EMAIL_API_BASE_URL=http://localhost:8080
NENE_VAULT_EMAIL_API_TOKEN=your-bearer-token-here
NENE_VAULT_EMAIL_CATEGORY=invoice_received
```

### 5. Test with dry run

```sh
NENE_VAULT_EMAIL_DRY_RUN=true php tools/email-inbound.php
```

Output:
```
[email-inbound] Found 2 email(s) to process.
[email-inbound] Processing: 1748672400123456789.eml (from=billing@vendor.example, attachments=1, allowed=1)
[email-inbound]   [DRY-RUN] Would upload: invoice_2026_05.pdf (application/pdf, 45123 bytes)
[email-inbound] Done. uploaded=1 skipped=0 errors=0
```

### 6. Run manually or via cron

```sh
# Manual
php tools/email-inbound.php

# Cron — every 5 minutes
*/5 * * * * /usr/bin/php /path/to/nene-vault/tools/email-inbound.php \
  >> /var/log/nene-vault-email.log 2>&1
```

---

## Metadata extraction

| Field | Source |
|---|---|
| `counterparty_name` | Sender display name (`From: ACME Corp <...>`) or email address |
| `transaction_date` | Email `Date:` header (sender's local date) |
| `category` | `NENE_VAULT_EMAIL_CATEGORY` (default `invoice_received`) |
| `source` | Always `email_inbound` |

The operator should review and confirm the metadata in the admin UI after
import. Documents uploaded via email have `is_metadata_confirmed: false`.

---

## Supported attachment types

| MIME type | Extensions |
|---|---|
| `application/pdf` | `.pdf` |
| `image/jpeg` | `.jpg`, `.jpeg` |
| `image/png` | `.png` |

Other attachment types (`.xlsx`, `.csv`, etc.) are silently skipped. Emails
with no allowed attachments are moved to `processed/` without uploading.

---

## Duplicate detection

If the same file (same SHA-256) has already been uploaded within the
organization, the API returns `409`. The CLI logs the conflict and continues
with remaining attachments. Duplicates do NOT block processing of other files.

---

## Troubleshooting

| Problem | Check |
|---|---|
| No .eml files found | Confirm MTA delivery is writing to `NENE_VAULT_EMAIL_MAILDIR` |
| HTTP 401 | `NENE_VAULT_EMAIL_API_TOKEN` missing or expired |
| HTTP 403 | Token's org_id does not match the API's resolved organization |
| No attachments extracted | Attachment MIME type not in the allowed list; check email source |
