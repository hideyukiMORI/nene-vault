# Backup Guide

NeNe Vault has two data stores that must both be backed up: the **database** and
the **file storage directory**.

---

## What to back up

| Store | Contains | Loss impact |
|---|---|---|
| Database | Document metadata, version records, audit log, user/org data | Cannot search, access history, or verify file integrity |
| File storage (`NENE_VAULT_STORAGE_PATH`) | Actual PDF/image files | Cannot download or re-export documents |

Both stores must be backed up together and kept in sync. A database backup
without the corresponding files is incomplete; files without a database cannot
be associated with metadata or audit events.

---

## SQLite (default)

The SQLite database is a single file at `DB_NAME` (default `var/nene_vault.sqlite`).

### Online backup (no downtime)

```sh
# Atomic copy via SQLite backup API
sqlite3 var/nene_vault.sqlite ".backup /backups/nene_vault_$(date +%Y%m%d).sqlite"
```

Or using PHP:

```sh
php -r "
\$src = new PDO('sqlite:var/nene_vault.sqlite');
\$src->exec(\"VACUUM INTO '/backups/nene_vault_\$(date +%Y%m%d).sqlite'\");
"
```

### File backup

Copy the entire storage directory:

```sh
rsync -av --progress \
  /var/nene-vault/files/ \
  /backups/nene-vault-files-$(date +%Y%m%d)/
```

### Recommended schedule

| Frequency | Retention |
|---|---|
| Daily incremental | 30 days |
| Weekly full | 6 months |
| Monthly offsite | 7–10 years (matches retention_years) |

---

## MySQL

Use `mysqldump` or `mysqlpump`:

```sh
mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" \
  "$DB_NAME" \
  > /backups/nene_vault_$(date +%Y%m%d).sql
```

For Docker Compose:

```sh
docker compose exec mysql \
  mysqldump --single-transaction -u nene_vault -pnene_vault nene_vault \
  > /backups/nene_vault_$(date +%Y%m%d).sql
```

---

## Docker Compose volume backup

If you use Docker volumes for the database or storage, back up the volume data:

```sh
# Stop the container first for a consistent snapshot
docker compose stop app

# Backup SQLite via volume
docker run --rm \
  -v nene_vault_data:/data \
  -v /backups:/backups \
  alpine tar czf /backups/nene-vault-data-$(date +%Y%m%d).tar.gz /data

docker compose start app
```

---

## Restore

### SQLite

```sh
# Stop the app
docker compose stop app

# Restore the backup
cp /backups/nene_vault_20260101.sqlite var/nene_vault.sqlite

# Restore files
rsync -av /backups/nene-vault-files-20260101/ /var/nene-vault/files/

docker compose start app
```

### MySQL

```sh
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" \
  < /backups/nene_vault_20260101.sql
```

---

## Retention window and hard delete

NeNe Vault **never hard-deletes document files** during the retention window
(`retention_expires_at`). The backup strategy must preserve files for the full
retention period (≥ 7 years; default 10 years).

After `retention_expires_at`, documents may be purged by an authorized operator
action (not yet implemented — Phase 4+). Until then, retain all backups.
