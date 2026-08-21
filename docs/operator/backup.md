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

Dump to a **temporary file, verify it, and only then promote it** to its final
name. Writing straight to the final name lets a dump that died halfway take the
place of "the latest backup" — and nothing downstream would notice.

```sh
set -euo pipefail

out="/backups/nene_vault_$(date +%Y%m%d).sql"
tmp="$out.part"

mysqldump \
  --single-transaction \
  --routines \
  --triggers \
  --no-tablespaces \
  -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" \
  "$DB_NAME" \
  > "$tmp"

[ -s "$tmp" ] || { echo "backup FAILED: $tmp is empty" >&2; rm -f "$tmp"; exit 1; }
case "$(tail -1 "$tmp")" in
  '-- Dump completed'*) : ;;
  *) echo "backup FAILED: no completion marker in $tmp (truncated dump)" >&2
     rm -f "$tmp"; exit 1 ;;
esac

mv "$tmp" "$out"
echo "backup OK: $out ($(du -h "$out" | cut -f1))"
```

For Docker Compose:

```sh
set -euo pipefail

out="/backups/nene_vault_$(date +%Y%m%d).sql"
tmp="$out.part"

docker compose exec -T mysql \
  mysqldump --single-transaction --routines --triggers --no-tablespaces \
  -u nene_vault -pnene_vault nene_vault \
  > "$tmp"

[ -s "$tmp" ] || { echo "backup FAILED: $tmp is empty" >&2; rm -f "$tmp"; exit 1; }
case "$(tail -1 "$tmp")" in
  '-- Dump completed'*) : ;;
  *) echo "backup FAILED: no completion marker in $tmp (truncated dump)" >&2
     rm -f "$tmp"; exit 1 ;;
esac

mv "$tmp" "$out"
echo "backup OK: $out ($(du -h "$out" | cut -f1))"
```

`-T` is required: without it `docker compose exec` allocates a TTY and mangles
the dump on its way to the redirect.

### Verifying a dump

`mysqldump` writes `-- Dump completed on <timestamp>` as the last line when — and
only when — it finished. That single line is what makes a dump verifiable, at two
different moments.

**1. Immediately after taking it.** Shown in both commands above: the dump lands
on `$tmp`, the marker is asserted, and only a dump that passes gets `mv`'d into
place. A failure leaves the previous backup untouched and removes the partial
file.

**2. After the fact, across dumps you already hold.** This is the marker's real
value — it lets you audit backups taken before anyone thought to check them:

```sh
set -euo pipefail

for f in /backups/nene_vault_*.sql; do
  case "$(tail -1 "$f")" in
    '-- Dump completed'*) echo "OK        $f" ;;
    *)                    echo "TRUNCATED $f" >&2 ;;
  esac
done
```

For gzipped dumps, read the last line through `gunzip` instead:

```sh
case "$(gunzip -c "$f" | tail -1)" in
```

### Why these three things

**`--no-tablespaces`** — MySQL 8's `mysqldump` issues `SHOW CREATE TABLESPACE`,
which requires the `PROCESS` privilege. On shared hosting, or with a
deliberately scoped application user, the dump fails outright with
`Access denied ... PROCESS privilege ... tablespaces`. Vault needs no tablespace
DDL in its dumps, so the flag costs nothing and removes the failure mode.

**The completion marker, not a size check** — 🔑 **a truncated dump is not an
empty file.** `[ -s "$file" ]` passes happily on a dump that died at 40 %, so
size alone will hand you a backup that only fails when you try to restore it.
The `-s` test above is kept as a cheap first gate, but the marker is what decides.

**`set -euo pipefail`** — the commands above redirect with `>` rather than piping,
so nothing is currently hidden by a pipe. The moment you add one — compressing
with `mysqldump ... | gzip > "$tmp"` is the obvious next step — the exit status
becomes **gzip's**, and a failed `mysqldump` disappears into a successful-looking
pipeline. `pipefail` is what keeps that failure visible.

> ⚠️ **Never write `... || true` in a backup script.** It converts "the command
> was not found" and "the container is not running" into exit 0, so cron reports
> success every night while producing nothing at all. A backup that fails loudly
> is recoverable; a backup that does nothing and calls it success is not
> discovered until a restore. Assert on the **artifact**, not on the exit status.

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
